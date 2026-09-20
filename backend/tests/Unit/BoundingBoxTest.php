<?php

namespace Tests\Unit;

use App\Domain\Discovery\Geo\BoundingBox;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BoundingBoxTest extends TestCase
{
    #[Test]
    public function origin_is_inside_and_far_point_is_excluded(): void
    {
        $box = BoundingBox::fromOrigin(35.7572, 51.4103, 15.0);
        $this->assertTrue($box->contains(35.7572, 51.4103));
        $this->assertTrue($box->contains(35.8044, 51.4256)); // Tajrish ~5 km
        $this->assertFalse($box->contains(32.6546, 51.6680)); // Isfahan
        $this->assertFalse($box->contains(35.7572, 52.8)); // far east, well outside 15 km
    }

    #[Test]
    public function box_is_a_superset_of_the_radius_along_the_axes(): void
    {
        $box = BoundingBox::fromOrigin(35.7572, 51.4103, 10.0);
        $latDelta = 10.0 / BoundingBox::KM_PER_DEGREE_LATITUDE;
        $this->assertEqualsWithDelta(35.7572 - $latDelta, $box->minLat, 0.000001);
        $this->assertEqualsWithDelta(35.7572 + $latDelta, $box->maxLat, 0.000001);
    }
}
