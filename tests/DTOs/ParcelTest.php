<?php

namespace Laraditz\Courier\Tests\DTOs;

use Laraditz\Courier\DTOs\Shared\Parcel;
use Laraditz\Courier\Tests\TestCase;

class ParcelTest extends TestCase
{
    public function test_creates_parcel(): void
    {
        $parcel = new Parcel(
            weight: 1.5,
            length: 20.0,
            width: 15.0,
            height: 10.0,
            declaredValue: 100.00,
            description: 'Electronic goods',
            quantity: 1,
        );

        $this->assertSame(1.5, $parcel->weight);
        $this->assertSame(20.0, $parcel->length);
        $this->assertSame(15.0, $parcel->width);
        $this->assertSame(10.0, $parcel->height);
        $this->assertSame(100.00, $parcel->declaredValue);
        $this->assertSame('Electronic goods', $parcel->description);
        $this->assertSame(1, $parcel->quantity);
    }
}
