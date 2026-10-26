<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class DriverLocationResult
{
    public function __construct(
        public string $driverId,
        public float $lat,
        public float $lng,
        public ?Carbon $updatedAt,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
