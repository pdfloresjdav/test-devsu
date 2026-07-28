<?php

namespace App\Contracts;

interface ClienteComplementarioClient
{
    /**
     * Informacion detallada/extendida del cliente que no vive en el Core
     * (por ejemplo, segmento comercial, preferencias, contacto ampliado).
     *
     * @return array{
     *     cliente_id: string,
     *     segmento: string,
     *     email: string,
     *     telefono: string,
     *     preferencias: array<string, mixed>
     * }
     */
    public function getDetalle(string $clienteId): array;
}
