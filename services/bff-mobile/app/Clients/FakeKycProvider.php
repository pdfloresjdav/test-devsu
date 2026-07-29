<?php

namespace App\Clients;

use App\Contracts\KycProvider;

/**
 * Simula al proveedor KYC (Onfido/iProov) mientras no exista una
 * integracion real. Determinista para poder probar el camino de rechazo:
 * cualquier documento de identidad que empiece con "RECHAZA-" es
 * rechazado; el resto se aprueba.
 */
class FakeKycProvider implements KycProvider
{
    public function verificar(string $documentoIdentidad, string $selfie): array
    {
        if (str_starts_with($documentoIdentidad, 'RECHAZA-')) {
            return ['aprobado' => false, 'score' => 0.12, 'motivo' => 'El documento no coincide con la selfie'];
        }

        return ['aprobado' => true, 'score' => 0.97, 'motivo' => null];
    }
}
