<?php

namespace Tests\Unit;

use App\Domain\Discovery\Geo\Haversine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HaversineTest extends TestCase
{
    #[Test]
    public function same_point_is_zero(): void
    {
        $this->assertSame(0.0, Haversine::distanceKm(35.7572, 51.4103, 35.7572, 51.4103));
        $this->assertSame(6371.0, Haversine::EARTH_RADIUS_KM);
    }

    #[Test]
    public function nearby_tehran_points_are_a_few_kilometres(): void
    {
        // Vanak → Tajrish is on the order of 5–6 km, never tens of km.
        $km = Haversine::distanceKm(35.7572, 51.4103, 35.8044, 51.4256);
        $this->assertGreaterThan(4.0, $km);
        $this->assertLessThan(8.0, $km);
    }

    #[Test]
    public function isfahan_is_hundreds_of_kilometres_from_vanak(): void
    {
        $km = Haversine::distanceKm(35.7572, 51.4103, 32.6546, 51.6680);
        $this->assertGreaterThan(300.0, $km);
        $this->assertLessThan(400.0, $km);
    }
}
