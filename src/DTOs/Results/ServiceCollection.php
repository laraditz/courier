<?php

namespace Laraditz\Courier\DTOs\Results;

readonly class ServiceCollection
{
    /** @param ServiceOption[] $items */
    public function __construct(public array $items) {}
}
