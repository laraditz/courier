<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class QuotationResult
{
    public function __construct(
        public string $quotationId,
        public float $price,
        public string $currency,
        public ?Carbon $expiresAt,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
