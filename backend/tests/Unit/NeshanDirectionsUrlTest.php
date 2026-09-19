<?php

namespace Tests\Unit;

use App\Domain\Discovery\NeshanDirectionsUrl;
use Tests\TestCase;

final class NeshanDirectionsUrlTest extends TestCase
{
    public function test_drive_url_uses_neighbourhood_origin_and_clinic_destination(): void
    {
        $url = NeshanDirectionsUrl::drive(35.7572, 51.4103, 35.8044, 51.4256);

        $this->assertSame(
            'https://nshn.ir/maps?origin=35.7572,51.4103&destination=35.8044,51.4256&type=drive',
            $url,
        );
        $this->assertStringNotContainsString('available', $url);
    }
}
