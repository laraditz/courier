<?php

namespace Laraditz\Courier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laraditz\Courier\Contracts\HandlesWebhooks;
use Laraditz\Courier\Events\WebhookReceived;
use Laraditz\Courier\Exceptions\CourierException;

class WebhookController extends Controller
{
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

        if (! $instance->verifyWebhook($request)) {
            abort(401);
        }

        event(new WebhookReceived($driver, $request->all()));

        $instance->handleWebhook($request);

        return response()->noContent(200);
    }
}
