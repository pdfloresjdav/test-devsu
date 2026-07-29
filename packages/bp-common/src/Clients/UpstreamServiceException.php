<?php

namespace BP\Common\Clients;

use RuntimeException;
use Throwable;

/**
 * Envuelve una falla de un servicio de negocio interno, preservando el
 * status code para que el BFF que la reciba pueda decidir como
 * traducirla al cliente (en vez de siempre devolver un 502 generico).
 */
class UpstreamServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
