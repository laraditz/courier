<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class TrackingResult
{
    /**
     * @param TrackingEvent[] $events
     */
    public function __construct(
        public string $waybillNumber,
        public string $status,
        public ?Carbon $estimatedDelivery,
        public array $events,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
