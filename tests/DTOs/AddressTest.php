<?php

namespace Laraditz\Courier\Tests\DTOs;

use Laraditz\Courier\DTOs\Shared\Address;
use Laraditz\Courier\DTOs\Shared\Location;
use Laraditz\Courier\Tests\TestCase;

class AddressTest extends TestCase
{
    public function test_creates_address_with_required_fields(): void
    {
        $address = new Address(
            name: 'Ahmad Farhan',
            phone: '+60123456789',
            email: null,
            line1: 'No 12, Jalan Bukit',
            line2: null,
            line3: null,
            city: 'Kuala Lumpur',
            state: 'Wilayah Persekutuan',
            postcode: '50000',
            country: 'MY',
        );

        $this->assertSame('Ahmad Farhan', $address->name);
        $this->assertSame('+60123456789', $address->phone);
        $this->assertNull($address->email);
        $this->assertSame('No 12, Jalan Bukit', $address->line1);
        $this->assertNull($address->line2);
        $this->assertNull($address->line3);
        $this->assertSame('Kuala Lumpur', $address->city);
        $this->assertSame('Wilayah Persekutuan', $address->state);
        $this->assertSame('50000', $address->postcode);
        $this->assertSame('MY', $address->country);
    }

    public function test_phone_and_email_are_nullable(): void
    {
        $address = new Address(
            name: 'Recipient',
            phone: null,
            email: null,
            line1: 'Line 1',
            line2: null,
            line3: null,
            city: 'City',
            state: 'State',
            postcode: '12345',
            country: 'MY',
        );

        $this->assertNull($address->phone);
        $this->assertNull($address->email);
    }

    public function test_is_readonly(): void
    {
        $address = new Address(
            name: 'Test',
            phone: null,
            email: null,
            line1: 'Line 1',
            line2: null,
            line3: null,
            city: 'City',
            state: 'State',
            postcode: '12345',
            country: 'MY',
        );

        $this->expectException(\Error::class);
        $address->name = 'Mutated'; // should throw — readonly
    }

    public function test_lat_lng_default_null(): void
    {
        $address = new Address('Name', null, null, 'Line 1', null, null, 'KL', 'WP', '50000', 'MY');
        $this->assertNull($address->lat);
        $this->assertNull($address->lng);
    }

    public function test_lat_lng_can_be_set(): void
    {
        $address = new Address('Name', null, null, 'Line 1', null, null, 'KL', 'WP', '50000', 'MY', lat: 3.1390, lng: 101.6869);
        $this->assertSame(3.1390, $address->lat);
        $this->assertSame(101.6869, $address->lng);
    }

    public function test_location_lat_lng_default_null(): void
    {
        $loc = new Location('50000', 'KL', 'WP', 'MY');
        $this->assertNull($loc->lat);
        $this->assertNull($loc->lng);
    }

    public function test_location_lat_lng_can_be_set(): void
    {
        $loc = new Location('50000', 'KL', 'WP', 'MY', lat: 3.1390, lng: 101.6869);
        $this->assertSame(3.1390, $loc->lat);
        $this->assertSame(101.6869, $loc->lng);
    }
}
