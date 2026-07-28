<?php

namespace App\Clients;

use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Implementacion real: llama a la red/switch interbancario por HTTP (mTLS
 * en produccion, terminado en el balanceador/gateway de salida). Se activa
 * con INTERBANK_DRIVER=http + INTERBANK_BASE_URL en el .env.
 */
class HttpInterbankClient implements InterbankClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function ejecutar(string $cuentaDestino, float $monto): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/transfer', [
                'json' => ['cuenta_destino' => $cuentaDestino, 'monto' => $monto],
            ]);
        } catch (Throwable $e) {
            throw new InterbankException("El banco destino no respondio: {$e->getMessage()}", previous: $e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
