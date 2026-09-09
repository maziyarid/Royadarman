<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_to_the_default_locale(): void
    {
        $this->get('/')->assertRedirect('/fa/');
    }

    public function test_health_endpoint_is_minimal_json(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('X-Request-ID');
    }
}
