<?php

namespace Laraditz\Courier\Contracts;

use Illuminate\Http\Request;

interface HandlesWebhooks
{
    public function verifyWebhook(Request $request): bool;
    public function handleWebhook(Request $request): void;
}
