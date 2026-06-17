<?php

namespace Laraditz\Courier\DTOs\Shared;

readonly class Parcel
{
    public function __construct(
        public float $weight,        // kg
        public float $length,        // cm
        public float $width,         // cm
        public float $height,        // cm
        public float $declaredValue,
        public string $description,
        public int $quantity,
    ) {}
}
