<?php

namespace Laraditz\Courier\Events;

readonly class WebhookReceived
{
    public function __construct(
        public string $driver,
        public array  $payload,
    ) {}
}
