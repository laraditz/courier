<?php

namespace Laraditz\Courier\Logging;

use Illuminate\Support\Facades\Log;
use Laraditz\Courier\Models\CourierWebhookLog;
use Laraditz\Courier\Support\Redactor;
use Throwable;

class WebhookLogWriter
{
    private ?CourierWebhookLog $lastRecord = null;

    /**
     * The row created by the most recent record() call on this instance.
     *
     * Null when the write failed and was swallowed, when record() has not been
     * called, or when a subclass overrides record() without populating it — all
     * three are equivalent to callers, which treat null as "no row to reference".
     */
    public function lastRecord(): ?CourierWebhookLog
    {
        return $this->lastRecord;
    }

    /**
     * Returns void deliberately. Widening this to ?CourierWebhookLog would be an
     * incompatible override for any subclass declaring record(): void, and the
     * writer is container-resolvable through WebhookController's constructor.
     */
    public function record(array $data): void
    {
        $this->lastRecord = null;

        $redactKeys = config('courier.logging.redact', []);

        foreach (['headers', 'payload'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = Redactor::redact($data[$field], $redactKeys);
            }
        }

        try {
            $this->lastRecord = CourierWebhookLog::create($data);
        } catch (Throwable $e) {
            Log::error('Failed to write courier webhook log', ['exception' => $e]);
        }
    }
}
