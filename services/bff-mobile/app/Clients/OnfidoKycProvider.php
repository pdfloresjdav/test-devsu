<?php

namespace App\Clients;

use App\Contracts\KycProvider;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Implementacion real contra un proveedor KYC tipo Onfido/iProov (decision
 * 3.7). Se activa con KYC_DRIVER=http + KYC_BASE_URL/KYC_API_KEY. No se
 * prueba contra un sandbox real (requeriria cuenta paga) -- queda como
 * codigo listo para produccion, verificado solo por lectura.
 */
class OnfidoKycProvider implements KycProvider
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
    }

    public function verificar(string $documentoIdentidad, string $selfie): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/verifications', [
                'headers' => ['Authorization' => "Token token={$this->apiKey}"],
                'json' => ['documento_identidad' => $documentoIdentidad, 'selfie' => $selfie],
            ]);
        } catch (Throwable $e) {
            return ['aprobado' => false, 'score' => 0.0, 'motivo' => "No se pudo contactar al proveedor KYC: {$e->getMessage()}"];
        }

        $body = json_decode((string) $response->getBody(), true);

        return [
            'aprobado' => ($body['result'] ?? null) === 'clear',
            'score' => (float) ($body['score'] ?? 0),
            'motivo' => $body['reason'] ?? null,
        ];
    }
}
