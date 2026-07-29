<?php

namespace App\Contracts;

interface KycProvider
{
    /**
     * Verifica el documento de identidad + selfie con prueba de vida
     * (decision 3.7 del documento de arquitectura).
     *
     * @return array{aprobado: bool, score: float, motivo: ?string}
     */
    public function verificar(string $documentoIdentidad, string $selfie): array;
}
