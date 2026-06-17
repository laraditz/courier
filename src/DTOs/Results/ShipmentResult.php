<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class ShipmentResult
{
    public function __construct(
        public string $waybillNumber,
        public string $status,
        public ?Carbon $estimatedDelivery,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
