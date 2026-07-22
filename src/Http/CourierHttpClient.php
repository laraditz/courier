<?php

namespace Laraditz\Courier\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LogicException;

class CourierHttpClient
{
    private bool $configured = false;

    private ?string $driver = null;

    private ?string $action = null;

    private ?string $reference = null;

    private ?string $waybillNumber = null;

    public function forLog(string $driver, string $action, ?string $reference = null, ?string $waybillNumber = null): static
    {
        $this->configured = true;
        $this->driver = $driver;
        $this->action = $action;
        $this->reference = $reference;
        $this->waybillNumber = $waybillNumber;

        return $this;
    }

    public function get(string $url, array $query = []): Response
    {
        $this->guard();

        return Http::get($url, $query);
    }

    public function post(string $url, array $data = []): Response
    {
        $this->guard();

        return Http::post($url, $data);
    }

    public function put(string $url, array $data = []): Response
    {
        $this->guard();

        return Http::put($url, $data);
    }

    public function patch(string $url, array $data = []): Response
    {
        $this->guard();

        return Http::patch($url, $data);
    }

    public function delete(string $url, array $data = []): Response
    {
        $this->guard();

        return Http::delete($url, $data);
    }

    private function guard(): void
    {
        if (! $this->configured) {
            throw new LogicException('CourierHttpClient::forLog() must be called before making a request.');
        }
    }
}
