<?php

namespace App\Clients;

use App\Contracts\LivenessProvider;

/**
 * Simula AWS Rekognition Face Liveness mientras se prueba en local.
 * Determinista: si la nueva selfie es literalmente "RECHAZA", la
 * revalidacion falla; cualquier otro valor se aprueba.
 */
class FakeLivenessProvider implements LivenessProvider
{
    public function revalidar(string $selfieReferencia, string $selfieNueva): array
    {
        if ($selfieNueva === 'RECHAZA') {
            return ['aprobado' => false, 'score' => 0.30];
        }

        return ['aprobado' => true, 'score' => 0.95];
    }
}
