<?php

namespace Laraditz\Courier\DTOs\Shared;

readonly class Location
{
    public function __construct(
        public string $postcode,
        public string $city,
        public string $state,
        public string $country,
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}
}
