<?php

namespace App\Services;

use Aws\S3\S3Client;

/**
 * Archiva cada registro de auditoria en S3 como copia de largo plazo.
 *
 * IMPORTANTE: en AWS real, el bucket configurado debe tener Object Lock en
 * modo Compliance habilitado (ver decision 3.9 del documento de
 * arquitectura) -- eso es lo que da inmutabilidad real (ni el root de la
 * cuenta puede borrar el objeto antes de vencer la retencion). LocalStack
 * no soporta Object Lock real, asi que en local esta clase solo es un
 * stand-in que demuestra el flujo (escribir el objeto), sin garantizar
 * inmutabilidad. No confundir "ya funciona en local" con "ya es inmutable".
 */
class WormArchiver
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
    ) {
    }

    /**
     * @param array<string, mixed> $registro
     */
    public function archivar(array $registro): void
    {
        $key = "auditoria/{$registro['actor']}/{$registro['audit_id']}.json";

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ContentType' => 'application/json',
        ]);
    }
}
