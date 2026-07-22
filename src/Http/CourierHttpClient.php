<?php

namespace Laraditz\Courier\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laraditz\Courier\Logging\ApiLogWriter;
use LogicException;

class CourierHttpClient
{
    private bool $configured = false;

    private ?string $driver = null;

    private ?string $action = null;

    private ?string $reference = null;

    private ?string $waybillNumber = null;

    public function __construct(private ?ApiLogWriter $writer = null)
    {
        $this->writer ??= new ApiLogWriter();
    }

    public function forLog(string $driver, string $action, ?string $reference = null, ?string $waybillNumber = null): static
    {
        $this->configured = true;
        $this->driver = $driver;
        $this->action = $action;
        $this->reference = $reference;
        $this->waybillNumber = $waybillNumber;

        return $this;
    }

    public function get(string $url, array $query = [], array $headers = []): Response
    {
        return $this->send('get', $url, $query, $headers);
    }

    public function post(string $url, array $data = [], array $headers = []): Response
    {
        return $this->send('post', $url, $data, $headers);
    }

    public function put(string $url, array $data = [], array $headers = []): Response
    {
        return $this->send('put', $url, $data, $headers);
    }

    public function patch(string $url, array $data = [], array $headers = []): Response
    {
        return $this->send('patch', $url, $data, $headers);
    }

    public function delete(string $url, array $data = [], array $headers = []): Response
    {
        return $this->send('delete', $url, $data, $headers);
    }

    private function send(string $method, string $url, array $data, array $headers): Response
    {
        $this->guard();

        $start = microtime(true);

        $response = Http::withHeaders($headers)->{$method}($url, $data);

        $this->log($method, $url, $headers, $data, $response, (int) round((microtime(true) - $start) * 1000));

        return $response;
    }

    private function log(string $method, string $url, array $headers, array $data, Response $response, int $durationMs): void
    {
        if (! config('courier.logging.enabled', true)) {
            return;
        }

        $this->writer->record([
            'driver' => $this->driver,
            'action' => $this->action,
            'reference' => $this->reference,
            'waybill_number' => $this->waybillNumber,
            'method' => strtoupper($method),
            'url' => $url,
            'request_headers' => $headers,
            'request_body' => $data,
            'status_code' => $response->status(),
            'response_headers' => $response->headers(),
            'response_body' => $response->body(),
            'duration_ms' => $durationMs,
            'successful' => $response->successful(),
        ]);
    }

    private function guard(): void
    {
        if (! $this->configured) {
            throw new LogicException('CourierHttpClient::forLog() must be called before making a request.');
        }
    }
}
