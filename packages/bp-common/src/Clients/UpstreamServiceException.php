<?php

namespace BP\Common\Clients;

use RuntimeException;
use Throwable;

/**
 * Envuelve una falla de un servicio de negocio interno, preservando el
 * status code para que el BFF que la reciba pueda decidir como
 * traducirla al cliente (en vez de siempre devolver un 502 generico).
 *
 * Tambien preserva el `errorCode` de negocio del servicio interno (ej.
 * "step_up_required" de svc-transferencias) cuando el body de error lo
 * trae -- sin esto, HandlesUpstreamErrors lo aplanaba a un generico
 * "upstream_error" y el unico dato que le quedaba al frontend para
 * distinguir un rechazo por step-up de cualquier otro error 403/422 era
 * el texto del mensaje, algo fragil para armar logica de negocio encima.
 * Se llama `errorCode` (no `code`) porque `Exception::$code` ya existe en
 * la clase base -- redeclararlo como readonly rompe con un fatal error.
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
