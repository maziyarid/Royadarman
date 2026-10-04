<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OperationsCalendarInputValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
    }

    public static function nonScalarMonths(): array
    {
        return [
            'Persian list' => ['/fa/panel/calendar?jmonth%5B%5D=1405-01'],
            'Persian nested' => ['/fa/panel/calendar?jmonth%5Byear%5D=1405'],
            'English list' => ['/en/panel/calendar?month%5B%5D=2026-03'],
            'English nested' => ['/en/panel/calendar?month%5Byear%5D=2026'],
            'Arabic list' => ['/ar/panel/calendar?month%5B%5D=2026-03'],
            'Arabic nested' => ['/ar/panel/calendar?month%5Byear%5D=2026'],
        ];
    }

    #[DataProvider('nonScalarMonths')]
    public function test_non_scalar_month_is_rejected_before_string_conversion(string $uri): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator)->get($uri)->assertUnprocessable();
    }

    public static function validMonths(): array
    {
        return [
            'Persian leap Esfand' => ['/fa/panel/calendar?jmonth=1403-12', '1403-12', '2025-02-18 20:30:00', '2025-03-20 20:30:00', 30, true],
            'Persian normal Esfand' => ['/fa/panel/calendar?jmonth=1404-12', '1404-12', '2026-02-19 20:30:00', '2026-03-20 20:30:00', 29, true],
            'Persian Saturday start' => ['/fa/panel/calendar?jmonth=1405-01', '1405-01', '2026-03-20 20:30:00', '2026-04-20 20:30:00', 31, true],
            'English Gregorian' => ['/en/panel/calendar?month=2026-03', '2026-03', '2026-02-28 20:30:00', '2026-03-31 20:30:00', 31, false],
            'Arabic Gregorian' => ['/ar/panel/calendar?month=2026-03', '2026-03', '2026-02-28 20:30:00', '2026-03-31 20:30:00', 31, false],
            'Persian ignores other selector' => ['/fa/panel/calendar?jmonth=1405-01&month%5B%5D=2026-03', '1405-01', '2026-03-20 20:30:00', '2026-04-20 20:30:00', 31, true],
            'English ignores other selector' => ['/en/panel/calendar?month=2026-03&jmonth%5B%5D=1405-01', '2026-03', '2026-02-28 20:30:00', '2026-03-31 20:30:00', 31, false],
        ];
    }

    #[DataProvider('validMonths')]
    public function test_valid_calendar_selection_preserves_window_and_locale(string $uri, string $key, string $start, string $end, int $days, bool $jalali): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $response = $this->actingAs($coordinator)->get($uri)->assertOk();
        $response->assertViewHas('monthInput', $key)->assertViewHas('jalaliMode', $jalali);
        $response->assertViewHas('serverWindow', [
            'start_utc' => $start,
            'end_utc' => $end,
            'day_count' => $days,
            'half_open' => true,
            'client_must_not_recompute_bounds' => true,
        ]);
        $this->assertCount($days, $response->viewData('days'));
    }

    public function test_omitted_month_uses_tehran_current_month_in_each_locale(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-20 21:00:00', 'UTC'));
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator);
        $this->get('/fa/panel/calendar')->assertOk()->assertViewHas('monthInput', '1405-01');
        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/panel/calendar')->assertOk()->assertViewHas('monthInput', '2026-03');
        }
        $this->travelBack();
    }

    public function test_malformed_input_does_not_bypass_role_or_demo_restrictions(): void
    {
        $uri = '/fa/panel/calendar?jmonth%5B%5D=1405-01';
        $this->get($uri)->assertRedirect('/fa/login');
        foreach (['patient', 'owner', 'tech_admin', 'clinician', 'clinic_rep'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($uri)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'coordinator', 'is_active' => false]))
            ->get($uri)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'coordinator']))
            ->withSession(['panel_demo' => true])->get($uri)->assertForbidden();
    }
}
