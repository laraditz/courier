<?php

namespace Laraditz\Courier\DTOs\Results;

readonly class RateOption
{
    public function __construct(
        public string $serviceCode,
        public string $serviceName,
        public float $price,
        public string $currency,
        public ?int $estimatedDays,
    ) {}
}
