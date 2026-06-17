<?php

namespace Laraditz\Courier\DTOs\Results;

readonly class CancelResult
{
    public function __construct(
        public bool $success,
        public string $message,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
