<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Results\QuotationResult;

interface LooksUpQuotations
{
    public function getQuotation(string $quotationId): QuotationResult;
}
