<?php

namespace Laraditz\Courier;

use Illuminate\Support\Manager;

class CourierManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('courier.default', 'sfexpress');
    }

    protected function callCustomCreator($driver): mixed
    {
        $config = $this->config->get("courier.drivers.{$driver}", []);

        return $this->customCreators[$driver]($this->container, $config);
    }
}
