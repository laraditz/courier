<?php

namespace Laraditz\Courier\Enums;

enum DeliveryMode: string
{
    case OnDemand = 'on_demand';
    case Scheduled = 'scheduled';
}
