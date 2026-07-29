<?php

namespace App\Contracts;

interface LivenessProvider
{
    /**
     * Revalida que quien esta operando sigue siendo el mismo cliente
     * verificado en el onboarding -- paso de step-up para operaciones
     * sensibles (diagrama de secuencia 8.2, decision 3.7).
     *
     * @return array{aprobado: bool, score: float}
     */
    public function revalidar(string $selfieReferencia, string $selfieNueva): array;
}
