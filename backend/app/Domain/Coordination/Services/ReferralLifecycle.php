<?php

namespace App\Domain\Coordination\Services;

use App\Domain\Coordination\Enums\ReferralLifecycleEventType;
use App\Models\ReferralLifecycleEvent;
use App\Models\ReferralProposal;
use App\Models\User;
use App\Support\WaitClock;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReferralLifecycle
{
    public function record(
        ReferralProposal $proposal,
        ReferralLifecycleEventType $type,
        ?User $actor,
        ?string $reason = null,
        ?DateTimeInterface $at = null,
    ): ReferralLifecycleEvent {
        $trimmed = is_string($reason) ? trim($reason) : '';
        if ($type->requiresReason() && $trimmed === '') {
            throw new DomainException('referral.reason_required');
        }

        return ReferralLifecycleEvent::query()->create([
            'proposal_id' => $proposal->id,
            'case_id' => $proposal->case_id,
            'clinic_id' => $proposal->clinic_id,
            'event_type' => $type,
            'actor_user_id' => $actor?->id,
            'reason' => $trimmed === '' ? null : $trimmed,
            'created_at' => $at ?? now(),
        ]);
    }

    public function recordProposed(ReferralProposal $proposal, User $actor): void
    {
        $this->record($proposal, ReferralLifecycleEventType::Proposed, $actor, null, $proposal->proposed_at);
        $this->record($proposal, ReferralLifecycleEventType::Offered, $actor, null, $proposal->proposed_at);
    }

    public function recordViewed(ReferralProposal $proposal, User $actor): void
    {
        DB::transaction(function () use ($proposal, $actor): void {
            /** @var ReferralProposal $locked */
            $locked = ReferralProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'proposed' || $locked->withdrawn_at) {
                return;
            }
            if ($this->hasEvent($locked, ReferralLifecycleEventType::Viewed)) {
                return;
            }
            $this->record($locked, ReferralLifecycleEventType::Viewed, $actor);
        });
    }

    public function recordDecision(ReferralProposal $proposal, User $actor, string $decision): void
    {
        $type = match ($decision) {
            'accepted' => ReferralLifecycleEventType::Accepted,
            'declined' => ReferralLifecycleEventType::Declined,
            default => throw new DomainException('referral.not_available'),
        };
        $this->record($proposal, $type, $actor);
    }

    public function reassign(ReferralProposal $proposal, User $actor, string $clinicId, string $reason): ReferralProposal
    {
        return DB::transaction(function () use ($proposal, $actor, $clinicId, $reason): ReferralProposal {
            /** @var ReferralProposal $locked */
            $locked = ReferralProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'proposed' || $locked->withdrawn_at) {
                throw new DomainException('referral.not_available');
            }
            $locked->update(['clinic_id' => $clinicId]);
            $this->record($locked, ReferralLifecycleEventType::Reassigned, $actor, $reason);

            return $locked->refresh();
        });
    }

    public function overrideWithdraw(ReferralProposal $proposal, User $actor, string $reason): ReferralProposal
    {
        return DB::transaction(function () use ($proposal, $actor, $reason): ReferralProposal {
            /** @var ReferralProposal $locked */
            $locked = ReferralProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'proposed' || $locked->withdrawn_at) {
                throw new DomainException('referral.not_available');
            }
            $this->record($locked, ReferralLifecycleEventType::CoordinatorOverride, $actor, $reason);
            $locked->update(['withdrawn_at' => now()]);

            return $locked->refresh();
        });
    }

    public function surfaceExpiry(ReferralProposal $proposal): ?ReferralLifecycleEvent
    {
        return DB::transaction(function () use ($proposal): ?ReferralLifecycleEvent {
            /** @var ReferralProposal $locked */
            $locked = ReferralProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'proposed' || $locked->withdrawn_at) {
                return null;
            }
            if ($this->hasEvent($locked, ReferralLifecycleEventType::Expired)
                || $this->hasEvent($locked, ReferralLifecycleEventType::SilentLoss)) {
                return null;
            }

            $origin = $this->slaOrigin($locked);
            if ($origin === null) {
                return null;
            }

            $sla = (int) config('royadarman.referral.proposal_sla_minutes', 1440);
            if ($sla <= 0) {
                return null;
            }

            $wait = WaitClock::minutes($origin);
            if ($wait === null || $wait < $sla) {
                return null;
            }

            $type = $this->hasEvent($locked, ReferralLifecycleEventType::Viewed)
                ? ReferralLifecycleEventType::Expired
                : ReferralLifecycleEventType::SilentLoss;

            return $this->record($locked, $type, null);
        });
    }

    public function slaOrigin(ReferralProposal $proposal): ?DateTimeInterface
    {
        $proposed = ReferralLifecycleEvent::query()
            ->where('proposal_id', $proposal->id)
            ->where('event_type', ReferralLifecycleEventType::Proposed->value)
            ->orderBy('created_at')
            ->first();

        return $proposed?->created_at ?? $proposal->proposed_at;
    }

    /** @return array{proposal_id: string, case_id: string, public_reference: ?string, sla_started_at: ?DateTimeInterface, wait_minutes: ?int, sla_band: string, expiry_state: ?string} */
    public function dashboardRow(ReferralProposal $proposal): array
    {
        $this->surfaceExpiry($proposal);
        $origin = $this->slaOrigin($proposal);
        $waiting = WaitClock::waiting($origin);
        $expiry = null;
        if ($this->hasEvent($proposal, ReferralLifecycleEventType::SilentLoss)) {
            $expiry = ReferralLifecycleEventType::SilentLoss->value;
        } elseif ($this->hasEvent($proposal, ReferralLifecycleEventType::Expired)) {
            $expiry = ReferralLifecycleEventType::Expired->value;
        }

        return [
            'proposal_id' => $proposal->id,
            'case_id' => $proposal->case_id,
            'public_reference' => $proposal->case?->public_reference,
            'sla_started_at' => $origin,
            'wait_minutes' => $waiting['wait_minutes'],
            'sla_band' => $waiting['sla_band'],
            'expiry_state' => $expiry,
        ];
    }

    public function hasEvent(ReferralProposal $proposal, ReferralLifecycleEventType $type): bool
    {
        return ReferralLifecycleEvent::query()
            ->where('proposal_id', $proposal->id)
            ->where('event_type', $type->value)
            ->exists();
    }
}
