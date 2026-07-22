<?php

namespace Laraditz\Courier\Contracts;

use Illuminate\Http\Request;

interface ExtractsWebhookReference
{
    /**
     * @return array{reference: ?string, waybillNumber: ?string}
     */
    public function extractWebhookReference(Request $request): array;
}
