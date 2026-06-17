<?php

namespace Laraditz\Courier\DTOs\Results;

readonly class RateCollection
{
    /** @param RateOption[] $items */
    public function __construct(public array $items) {}
}
