<?php

namespace Laraditz\Courier\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a driver shape the HTTP response returned by the webhook endpoint.
 *
 * Optional — a driver that does not implement this keeps the default
 * responses: 204-style empty 200 on success, 401 on failed verification.
 *
 * Responses are typed to Symfony's base Response, not Illuminate\Http\Response,
 * so a driver can return response()->json() — JsonResponse is a sibling of
 * Illuminate\Http\Response, not a subclass.
 */
interface ProvidesWebhookResponse
{
    public function webhookAcceptedResponse(Request $request): Response;

    public function webhookRejectedResponse(Request $request): Response;
}
