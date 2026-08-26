<?php

namespace Laraditz\Courier\Tests\Enums;

use Laraditz\Courier\Enums\DeliveryMode;
use Laraditz\Courier\Tests\TestCase;

class DeliveryModeTest extends TestCase
{
    public function test_has_exactly_two_cases(): void
    {
        $this->assertCount(2, DeliveryMode::cases());
    }

    public function test_case_values(): void
    {
        $this->assertSame('on_demand', DeliveryMode::OnDemand->value);
        $this->assertSame('scheduled', DeliveryMode::Scheduled->value);
    }
}
