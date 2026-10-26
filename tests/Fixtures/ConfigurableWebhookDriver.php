<?php

namespace Laraditz\Courier\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Laraditz\Courier\Contracts\ProvidesWebhookResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A webhook driver whose behaviour is supplied per test via closures.
 *
 * Extends PlainWebhookDriver so the eight CourierDriver stubs live in one
 * place, and adds ProvidesWebhookResponse. The $calls list records which
 * contract method ran, so tests can assert path isolation.
 */
class ConfigurableWebhookDriver extends PlainWebhookDriver implements ProvidesWebhookResponse
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        bool $verifies = true,
        ?Closure $onHandle = null,
        private ?Closure $accepted = null,
        private ?Closure $rejected = null,
    ) {
        parent::__construct($verifies, $onHandle);
    }

    public function webhookAcceptedResponse(Request $request): Response
    {
        $this->calls[] = 'accepted';

        return $this->accepted
            ? ($this->accepted)($request)
            : response()->json(['code' => '1', 'msg' => 'success']);
    }

    public function webhookRejectedResponse(Request $request): Response
    {
        $this->calls[] = 'rejected';

        return $this->rejected
            ? ($this->rejected)($request)
            : response()->json(['code' => '145003030', 'msg' => 'headers signature verification failed'], 401);
    }
}
