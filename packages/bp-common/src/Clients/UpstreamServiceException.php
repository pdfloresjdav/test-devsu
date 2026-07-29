<?php

namespace BP\Common\Clients;

use RuntimeException;
use Throwable;

/**
 * Wraps a failure from an internal business service, preserving the
 * status code so the BFF that receives it can decide how to translate it
 * to the client (instead of always returning a generic 502).
 *
 * Also preserves the internal service's business `errorCode` (e.g.
 * "step_up_required" from svc-transfers) when the error body carries
 * one -- without this, HandlesUpstreamErrors flattened it to a generic
 * "upstream_error" and the only thing left for the frontend to
 * distinguish a step-up rejection from any other 403/422 error was the
 * message text, which is fragile to build business logic on top of.
 * It's called `errorCode` (not `code`) because `Exception::$code` already
 * exists on the base class -- redeclaring it as readonly causes a fatal
 * error.
 */
class UpstreamServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 502,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
