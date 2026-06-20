<?php

namespace Laraditz\Courier\DTOs\Shared;

readonly class Address
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $email,
        public string $line1,
        public ?string $line2,
        public ?string $line3,
        public string $city,
        public string $state,
        public string $postcode,
        public string $country,
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}
}
