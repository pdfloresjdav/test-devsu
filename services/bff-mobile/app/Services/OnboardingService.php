<?php

namespace App\Services;

use App\Contracts\IdentityProviderClient;
use App\Contracts\KycProvider;
use App\Contracts\OnboardingRejectedException;
use BP\Common\Events\EventPublisherInterface;

/**
 * Orquesta el onboarding (diagrama de secuencia 8.2): verifica identidad
 * con el proveedor KYC y, si se aprueba, crea la identidad del cliente en
 * el proveedor de identidad. El registro de la credencial de acceso
 * (usuario/clave o WebAuthn) lo hace la app directamente contra el IdP
 * despues de esto, no el BFF.
 *
 * Simplificacion de alcance respecto al diagrama de secuencia: no se
 * valida la existencia del cliente en el Core Bancario antes de crear la
 * identidad -- esa llamada requeriria que el BFF se autentique como
 * servicio (client_credentials) en vez de con el JWT del usuario final
 * (que en onboarding todavia no existe), un flujo de autenticacion
 * maquina-a-maquina completo que el checklist de esta fase no pide. Queda
 * documentado aqui para no perderlo de vista si se retoma mas adelante.
 */
class OnboardingService
{
    public function __construct(
        private readonly KycProvider $kyc,
        private readonly IdentityProviderClient $identity,
        private readonly EventPublisherInterface $events,
    ) {
    }

    /**
     * @return array{usuario_id: string, estado: string}
     */
    public function onboardear(
        string $clienteId,
        string $nombre,
        string $email,
        string $documentoIdentidad,
        string $selfie,
    ): array {
        $resultadoKyc = $this->kyc->verificar($documentoIdentidad, $selfie);

        if (! $resultadoKyc['aprobado']) {
            $this->events->publish('OnboardingRejected', [
                'actor' => $clienteId,
                'cliente_id' => $clienteId,
                'motivo' => $resultadoKyc['motivo'],
            ]);

            throw new OnboardingRejectedException($resultadoKyc['motivo'] ?? 'Verificación de identidad rechazada');
        }

        $identidad = $this->identity->crearUsuario($clienteId, $nombre, $email);

        $this->events->publish('OnboardingCompleted', [
            'actor' => $clienteId,
            'cliente_id' => $clienteId,
            'usuario_id' => $identidad['usuario_id'],
        ]);

        return ['usuario_id' => $identidad['usuario_id'], 'estado' => 'aprobado'];
    }
}
