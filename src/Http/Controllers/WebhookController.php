<?php

namespace Laraditz\Courier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laraditz\Courier\Contracts\ExtractsWebhookReference;
use Laraditz\Courier\Contracts\HandlesWebhooks;
use Laraditz\Courier\Events\WebhookReceived;
use Laraditz\Courier\Exceptions\CourierException;
use Laraditz\Courier\Logging\WebhookLogWriter;

class WebhookController extends Controller
{
    public function __construct(private ?WebhookLogWriter $logWriter = null)
    {
        $this->logWriter ??= new WebhookLogWriter();
    }

    public function handle(Request $request, string $driver): Response
    {
        try {
            $instance = app('courier')->driver($driver);
        } catch (CourierException|\InvalidArgumentException) {
            abort(404);
        }

        if (! $instance instanceof HandlesWebhooks) {
            abort(404);
        }

        $reference = ['reference' => null, 'waybillNumber' => null];

        if ($instance instanceof ExtractsWebhookReference) {
            try {
                $reference = $instance->extractWebhookReference($request);
            } catch (\Throwable) {
                // Extraction is best-effort; a failure here must not block logging or the response.
            }
        }

        if (! $instance->verifyWebhook($request)) {
            $this->logWriter->record([
                'driver' => $driver,
                'reference' => $reference['reference'],
                'waybill_number' => $reference['waybillNumber'],
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
                'verified' => false,
                'status' => 'rejected',
            ]);

            abort(401);
        }

        event(new WebhookReceived($driver, $request->all()));

        try {
            $instance->handleWebhook($request);
        } catch (\Throwable $e) {
            $this->logWriter->record([
                'driver' => $driver,
                'reference' => $reference['reference'],
                'waybill_number' => $reference['waybillNumber'],
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
                'verified' => true,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logWriter->record([
            'driver' => $driver,
            'reference' => $reference['reference'],
            'waybill_number' => $reference['waybillNumber'],
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'verified' => true,
            'status' => 'processed',
        ]);

        return response()->noContent(200);
    }
}
