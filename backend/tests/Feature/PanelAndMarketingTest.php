<?php

namespace Tests\Feature;

use App\Models\MarketingPage;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PanelAndMarketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_panel_shows_only_own_case_references(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'en']);
        $other = User::factory()->create(['role' => 'patient', 'locale' => 'en']);
        $own = $this->makeCase($patient, 'RD-OWNCASE');
        $foreign = $this->makeCase($other, 'RD-FOREIGN');

        $this->actingAs($patient)->get('/en/panel')
            ->assertOk()
            ->assertSee($own->public_reference)
            ->assertDontSee($foreign->public_reference);
    }

    public function test_owner_panel_is_aggregate_only_and_does_not_render_case_reference(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'en']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient, 'RD-PRIVATE1');

        $this->actingAs($owner)->get('/en/panel')
            ->assertOk()
            ->assertSee('Owner operations overview')
            ->assertDontSee($case->public_reference);
    }

    public function test_technical_admin_panel_is_system_health_only(): void
    {
        $admin = User::factory()->create(['role' => 'tech_admin', 'locale' => 'en']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = $this->makeCase($patient, 'RD-PRIVATE2');

        $this->actingAs($admin)->get('/en/panel')
            ->assertOk()
            ->assertSee('Technical operations')
            ->assertDontSee($case->public_reference);
    }

    public function test_non_owner_cannot_open_marketing_cms(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($coordinator)->get('/en/panel/marketing')->assertForbidden();
    }

    public function test_owner_can_create_and_publish_localised_marketing_page_with_revision_history(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'en']);

        $this->actingAs($owner)->post('/en/panel/marketing', [
            'slug' => 'contact',
            'locale' => 'en',
            'title' => 'Client contact page',
            'excerpt' => 'Public contact excerpt',
            'body' => 'Safe public contact body',
            'meta_title' => 'Contact Royadarman',
            'meta_description' => 'Contact metadata',
        ])->assertRedirect();

        $page = MarketingPage::query()->where('slug', 'contact')->where('locale', 'en')->firstOrFail();
        $this->assertSame('draft', $page->status);
        $this->assertDatabaseHas('marketing_page_revisions', ['marketing_page_id' => $page->id, 'version' => 1, 'status' => 'draft']);

        $this->actingAs($owner)->post("/en/panel/marketing/{$page->id}/publish", ['version' => 1])->assertRedirect();

        $page->refresh();
        $this->assertSame('published', $page->status);
        $this->assertSame(2, $page->version);
        $this->assertNotNull($page->published_at);
        $this->assertDatabaseHas('marketing_page_revisions', ['marketing_page_id' => $page->id, 'version' => 2, 'status' => 'published']);

        $this->get('/en/contact')->assertOk()->assertSee('Client contact page')->assertSee('Safe public contact body');
    }

    public function test_editing_published_marketing_page_returns_it_to_draft_until_republished(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'en']);
        $page = MarketingPage::query()->create([
            'slug' => 'referrals', 'locale' => 'en', 'title' => 'Published title', 'body' => 'Published body',
            'status' => 'published', 'version' => 1, 'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id, 'published_at' => now(),
        ]);

        $this->actingAs($owner)->put("/en/panel/marketing/{$page->id}", [
            'version' => 1,
            'slug' => 'referrals',
            'locale' => 'en',
            'title' => 'Unreviewed edit',
            'excerpt' => null,
            'body' => 'Changed draft body',
            'meta_title' => null,
            'meta_description' => null,
        ])->assertRedirect();

        $page->refresh();
        $this->assertSame('draft', $page->status);
        $this->assertNull($page->published_at);
        $this->get('/en/referrals')->assertOk()->assertDontSee('Unreviewed edit');
    }

    public function test_public_marketing_pages_have_cache_and_sitemap_is_public_only(): void
    {
        $response = $this->get('/services/opg')->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/services/opg', false)->assertDontSee('/fa/services/opg', false)->assertDontSee('/panel', false)->assertDontSee('/api/', false);
    }

    private function makeCase(User $patient, string $reference): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => $reference,
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'submitted',
            'priority' => 'normal',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'en',
            'budget_input_unit' => 'toman',
            'currency' => 'IRR',
            'version' => 1,
        ]);
    }
}
