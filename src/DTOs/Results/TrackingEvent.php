<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class TrackingEvent
{
    public function __construct(
        public Carbon $timestamp,
        public string $location,
        public string $description,
        public string $status,
    ) {}
}
