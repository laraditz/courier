<?php

namespace Laraditz\Courier\DTOs\Results;

readonly class ServiceOption
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public ?int $estimatedDays,
    ) {}
}
