<?php

namespace Laraditz\Courier\Logging;

use Illuminate\Support\Facades\Log;
use Laraditz\Courier\Models\CourierApiLog;
use Laraditz\Courier\Support\Redactor;
use Throwable;

class ApiLogWriter
{
    public function record(array $data): void
    {
        $redactKeys = config('courier.logging.redact', []);

        foreach (['request_headers', 'request_body', 'response_headers', 'response_body'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = Redactor::redact($data[$field], $redactKeys);
            }
        }

        try {
            CourierApiLog::create($data);
        } catch (Throwable $e) {
            Log::error('Failed to write courier API log', ['exception' => $e]);
        }
    }
}
