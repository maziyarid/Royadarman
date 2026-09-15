<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Belt-and-suspenders isolation: never inherit a production APP_URL into generated links.
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        URL::forceScheme('http');
    }
}
