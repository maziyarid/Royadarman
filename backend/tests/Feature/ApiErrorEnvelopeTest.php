<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_have_a_stable_code_and_request_id(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/challenge', ['phone' => 'invalid']);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'error.validation')
            ->assertJsonStructure(['error' => ['message', 'details'], 'request_id'])
            ->assertHeader('X-Request-ID');
    }

    public function test_unauthenticated_api_errors_have_a_stable_code(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'error.unauthenticated')
            ->assertJsonStructure(['request_id']);

        $plainResponse = $this->get('/api/v1/me');
        $plainResponse
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'error.unauthenticated')
            ->assertJsonStructure(['request_id'])
            ->assertHeader('X-Request-ID');
        $this->assertNotNull($plainResponse->json('request_id'));
    }

    public function test_authorization_errors_have_a_stable_code(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->getJson('/api/v1/cases/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'error.not_found');
    }
}
