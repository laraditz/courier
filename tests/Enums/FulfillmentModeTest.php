<?php

namespace Laraditz\Courier\Tests\Enums;

use Laraditz\Courier\Enums\FulfillmentMode;
use Laraditz\Courier\Tests\TestCase;

class FulfillmentModeTest extends TestCase
{
    public function test_has_exactly_two_cases(): void
    {
        $this->assertCount(2, FulfillmentMode::cases());
    }

    public function test_case_values(): void
    {
        $this->assertSame('pickup', FulfillmentMode::Pickup->value);
        $this->assertSame('dropoff', FulfillmentMode::Dropoff->value);
    }
}
