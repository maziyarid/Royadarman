<?php

namespace Tests\Feature;

use App\Models\PolicyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PolicyVersionImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_policy_can_be_edited_and_then_published(): void
    {
        $policy = PolicyVersion::query()->create([
            'policy_key' => 'case_coordination',
            'version' => 'draft-v1',
            'locale' => 'en',
            'content' => 'Draft text',
            'content_hash' => hash('sha256', 'Draft text'),
            'published_at' => null,
        ]);

        $policy->update([
            'content' => 'Approved text',
            'content_hash' => hash('sha256', 'Approved text'),
            'published_at' => now(),
        ]);

        $this->assertSame('Approved text', $policy->refresh()->content);
        $this->assertNotNull($policy->published_at);
    }

    public function test_published_policy_cannot_be_modified(): void
    {
        $policy = $this->publishedPolicy();

        $this->expectException(LogicException::class);
        $policy->update([
            'content' => 'Substituted text',
            'content_hash' => hash('sha256', 'Substituted text'),
        ]);
    }

    public function test_published_policy_cannot_be_deleted(): void
    {
        $policy = $this->publishedPolicy();

        $this->expectException(LogicException::class);
        $policy->delete();
    }

    private function publishedPolicy(): PolicyVersion
    {
        $content = 'Approved immutable consent text';

        return PolicyVersion::query()->create([
            'policy_key' => 'case_coordination',
            'version' => 'approved-v1',
            'locale' => 'en',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'published_at' => now(),
        ]);
    }
}
