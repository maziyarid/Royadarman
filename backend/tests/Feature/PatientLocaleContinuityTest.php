<?php

namespace Tests\Feature;

use App\Http\Responses\PasskeyLoginResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PatientLocaleContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_persian_workspace_language_survives_unprefixed_cms_navigation(): void
    {
        config(['app.locale' => 'en']);
        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'en', 'is_active' => true]);
        $this->actingAs($owner)->get('/fa/panel')->assertOk()->assertSessionHas('ui_locale', 'fa');
        app()->setLocale('en');
        $this->get('/admin/cms/posts')->assertOk()->assertSee('lang="fa"', false)->assertSee('dir="rtl"', false);
    }

    public function test_passkey_uses_language_selected_on_login_not_older_account_preference(): void
    {
        $this->get('/fa/login')->assertOk()->assertSessionHas('ui_locale', 'fa');
        $this->actingAs(User::factory()->create(['locale' => 'en', 'is_active' => true]));
        $request = Request::create('/passkeys/login', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession(app('session.store'));
        $response = app(PasskeyLoginResponse::class)->toResponse($request);
        $this->assertSame('http://localhost/fa/panel', $response->getData(true)['redirect']);
    }

    public function test_explicit_english_selection_remains_supported(): void
    {
        $this->get('/en/login')->assertOk()->assertSessionHas('ui_locale', 'en');
        $this->actingAs(User::factory()->create(['locale' => 'fa', 'is_active' => true]));
        $request = Request::create('/passkeys/login', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession(app('session.store'));
        $this->assertSame('http://localhost/en/panel', app(PasskeyLoginResponse::class)->toResponse($request)->getData(true)['redirect']);
    }

    public function test_api_content_language_does_not_overwrite_browser_language(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'is_active' => true, 'locale' => 'fa']);
        $this->actingAs($patient)->withSession(['ui_locale' => 'fa'])->getJson('/api/v1/me', ['X-Locale' => 'en'])->assertOk();
        $this->assertSame('fa', session('ui_locale'));
    }
}
