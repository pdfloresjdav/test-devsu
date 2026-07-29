<?php

namespace App\Clients;

use App\Contracts\LivenessProvider;
use Aws\Rekognition\RekognitionClient;
use Throwable;

/**
 * Real implementation with AWS Rekognition (decision 3.7). Documented
 * simplification: the full Face Liveness API (CreateFaceLivenessSession +
 * video streaming from the mobile SDK) requires a session started by the
 * app; for the BFF-mediated revalidation in this version, CompareFaces is
 * used between the onboarding reference selfie and the new selfie -- a
 * lighter check, enough for a medium-risk step-up. Not tested against real
 * AWS (there's no Rekognition in LocalStack without the Pro version).
 */
class RekognitionLivenessProvider implements LivenessProvider
{
    private const SIMILARITY_THRESHOLD = 90.0;

    public function __construct(private readonly RekognitionClient $client)
    {
    }

    public function revalidate(string $referenceSelfie, string $newSelfie): array
    {
        try {
            $result = $this->client->compareFaces([
                'SourceImage' => ['Bytes' => base64_decode($referenceSelfie)],
                'TargetImage' => ['Bytes' => base64_decode($newSelfie)],
                'SimilarityThreshold' => self::SIMILARITY_THRESHOLD,
            ]);
        } catch (Throwable $e) {
            return ['approved' => false, 'score' => 0.0];
        }

        $matches = $result['FaceMatches'] ?? [];

        if (empty($matches)) {
            return ['approved' => false, 'score' => 0.0];
        }

        $similarity = (float) $matches[0]['Similarity'];

        return ['approved' => $similarity >= self::SIMILARITY_THRESHOLD, 'score' => $similarity / 100];
    }
}
