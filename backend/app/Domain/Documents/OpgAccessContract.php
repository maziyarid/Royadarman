<?php

namespace App\Domain\Documents;

use App\Domain\Identity\Authorization\MembershipPermission;

/**
 * Records the current OPG file and review gates. It does not grant a read,
 * a diagnosis, a signer, or a retention period.
 */
final class OpgAccessContract
{
    public const STATUS = 'proposed_not_wired';

    /**
     * @return list<string>
     */
    public static function documentStatuses(): array
    {
        return ['quarantined', 'scanning', 'approved', 'rejected', 'scan_failed', 'deleted'];
    }

    /**
     * @return list<string>
     */
    public static function privateDisks(): array
    {
        return ['opg-quarantine', 'private-opg'];
    }

    /**
     * @return list<string>
     */
    public static function malwareVerdicts(): array
    {
        return ['clean', 'infected', 'scanner_unavailable'];
    }

    public static function acceptedRetentionDays(): ?int
    {
        return null;
    }

    /**
     * @return list<string>|null
     */
    public static function toothTaxonomy(): ?array
    {
        return null;
    }

    public static function scanIsDiagnosis(string $verdict): bool
    {
        return false;
    }

    public static function titleGrantsDocumentRead(string $title): MembershipPermission
    {
        return new MembershipPermission(false, 'title_does_not_grant_document_read');
    }

    public static function interpretScan(string $verdict): MembershipPermission
    {
        if (! in_array($verdict, self::malwareVerdicts(), true)) {
            return new MembershipPermission(false, 'unknown_scan_verdict');
        }

        return new MembershipPermission(false, 'malware_verdict_not_a_diagnosis');
    }

    public static function bytesOnDisk(string $status, string $disk): MembershipPermission
    {
        if (in_array($disk, ['public', 'public-cms'], true)) {
            return new MembershipPermission(false, 'public_disk_not_for_opg');
        }

        if (! in_array($disk, self::privateDisks(), true)) {
            return new MembershipPermission(false, 'unknown_document_disk');
        }

        if ($status !== 'approved' || $disk !== 'private-opg') {
            return new MembershipPermission(false, 'bytes_not_approved');
        }

        return new MembershipPermission(false, 'bytes_not_granted_by_this_contract');
    }

    public static function releasedText(bool $signed, bool $publicationEvent): MembershipPermission
    {
        if (! $signed) {
            return new MembershipPermission(false, 'review_unsigned');
        }

        if (! $publicationEvent) {
            return new MembershipPermission(false, 'publication_event_missing');
        }

        return new MembershipPermission(false, 'released_text_not_a_byte_grant');
    }
}
