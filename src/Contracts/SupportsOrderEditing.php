<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Shared\Address;

interface SupportsOrderEditing
{
    /**
     * @param Address[] $stops
     */
    public function editOrder(string $orderId, array $stops): ShipmentResult;
}
