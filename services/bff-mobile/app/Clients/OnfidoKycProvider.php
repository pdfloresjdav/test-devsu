<?php

namespace App\Clients;

use App\Contracts\KycProvider;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Real implementation against an Onfido/iProov-style KYC provider (decision
 * 3.7). Activated with KYC_DRIVER=http + KYC_BASE_URL/KYC_API_KEY. Not
 * tested against a real sandbox (would require a paid account) -- kept as
 * production-ready code, verified by reading only.
 */
class OnfidoKycProvider implements KycProvider
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
    }

    public function verify(string $identityDocument, string $selfie): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/verifications', [
                'headers' => ['Authorization' => "Token token={$this->apiKey}"],
                'json' => ['identity_document' => $identityDocument, 'selfie' => $selfie],
            ]);
        } catch (Throwable $e) {
            return ['approved' => false, 'score' => 0.0, 'reason' => "Could not reach the KYC provider: {$e->getMessage()}"];
        }

        $body = json_decode((string) $response->getBody(), true);

        return [
            'approved' => ($body['result'] ?? null) === 'clear',
            'score' => (float) ($body['score'] ?? 0),
            'reason' => $body['reason'] ?? null,
        ];
    }
}
