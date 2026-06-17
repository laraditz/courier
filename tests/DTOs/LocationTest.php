<?php

namespace Laraditz\Courier\Tests\DTOs;

use Laraditz\Courier\DTOs\Shared\Location;
use Laraditz\Courier\Tests\TestCase;

class LocationTest extends TestCase
{
    public function test_creates_location(): void
    {
        $location = new Location(
            postcode: '50000',
            city: 'Kuala Lumpur',
            state: 'Wilayah Persekutuan',
            country: 'MY',
        );

        $this->assertSame('50000', $location->postcode);
        $this->assertSame('Kuala Lumpur', $location->city);
        $this->assertSame('Wilayah Persekutuan', $location->state);
        $this->assertSame('MY', $location->country);
    }

    public function test_is_readonly(): void
    {
        $location = new Location('50000', 'KL', 'WP', 'MY');

        $this->expectException(\Error::class);
        $location->postcode = '99999';
    }
}
