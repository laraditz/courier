<?php

namespace Laraditz\Courier\Logging;

use Illuminate\Support\Facades\Log;
use Laraditz\Courier\Models\CourierWebhookLog;
use Laraditz\Courier\Support\Redactor;
use Throwable;

class WebhookLogWriter
{
    public function record(array $data): void
    {
        $redactKeys = config('courier.logging.redact', []);

        foreach (['headers', 'payload'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = Redactor::redact($data[$field], $redactKeys);
            }
        }

        try {
            CourierWebhookLog::create($data);
        } catch (Throwable $e) {
            Log::error('Failed to write courier webhook log', ['exception' => $e]);
        }
    }
}
