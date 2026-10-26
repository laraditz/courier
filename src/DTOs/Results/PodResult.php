<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class PodResult
{
    public function __construct(
        public string $status,
        public ?string $imageUrl = null,
        public ?Carbon $deliveredAt = null,
    ) {}
}
