<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_serves_persian_at_the_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="fa" dir="rtl">', false);
    }

    public function test_health_endpoint_is_minimal_json(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('X-Request-ID');
    }
}
