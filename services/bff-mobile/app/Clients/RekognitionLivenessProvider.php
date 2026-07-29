<?php

namespace App\Clients;

use App\Contracts\LivenessProvider;
use Aws\Rekognition\RekognitionClient;
use Throwable;

/**
 * Implementacion real con AWS Rekognition (decision 3.7). Simplificacion
 * documentada: la API completa de Face Liveness (CreateFaceLivenessSession
 * + streaming de video desde el SDK movil) requiere una sesion iniciada
 * por la app; para la revalidacion mediada por el BFF de esta version se
 * usa CompareFaces entre la selfie de referencia del onboarding y la
 * nueva selfie -- una verificacion mas liviana, suficiente para un
 * step-up de riesgo medio. No se prueba contra AWS real (no hay
 * Rekognition en LocalStack sin la version Pro).
 */
class RekognitionLivenessProvider implements LivenessProvider
{
    private const UMBRAL_SIMILITUD = 90.0;

    public function __construct(private readonly RekognitionClient $client)
    {
    }

    public function revalidar(string $selfieReferencia, string $selfieNueva): array
    {
        try {
            $resultado = $this->client->compareFaces([
                'SourceImage' => ['Bytes' => base64_decode($selfieReferencia)],
                'TargetImage' => ['Bytes' => base64_decode($selfieNueva)],
                'SimilarityThreshold' => self::UMBRAL_SIMILITUD,
            ]);
        } catch (Throwable $e) {
            return ['aprobado' => false, 'score' => 0.0];
        }

        $coincidencias = $resultado['FaceMatches'] ?? [];

        if (empty($coincidencias)) {
            return ['aprobado' => false, 'score' => 0.0];
        }

        $similitud = (float) $coincidencias[0]['Similarity'];

        return ['aprobado' => $similitud >= self::UMBRAL_SIMILITUD, 'score' => $similitud / 100];
    }
}
