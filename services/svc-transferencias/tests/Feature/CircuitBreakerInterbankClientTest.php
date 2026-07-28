<?php

namespace Tests\Feature;

use App\Clients\CircuitBreakerInterbankClient;
use App\Contracts\CircuitOpenException;
use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerInterbankClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->limpiarEstadoDelCircuito();
    }

    protected function tearDown(): void
    {
        // El estado del circuito vive en Redis real (a proposito, para ser
        // consistente entre workers/instancias) y DatabaseTransactions no lo
        // revierte -- si no se limpia aqui, un circuito abierto por este test
        // contamina cualquier otro test (de esta clase o de otras) que use
        // el mismo InterbankClient del contenedor.
        $this->limpiarEstadoDelCircuito();

        parent::tearDown();
    }

    private function limpiarEstadoDelCircuito(): void
    {
        Cache::store('redis')->forget('circuit-breaker:interbank:failures');
        Cache::store('redis')->forget('circuit-breaker:interbank:opened-at');
    }

    private function siempreFallaInterbankClient(array &$calls): InterbankClient
    {
        return new class($calls) implements InterbankClient {
            public function __construct(private array &$calls)
            {
            }

            public function ejecutar(string $cuentaDestino, float $monto): array
            {
                $this->calls[] = $cuentaDestino;
                throw new InterbankException('el banco destino no responde');
            }
        };
    }

    public function test_abre_el_circuito_tras_el_umbral_de_fallas_y_deja_de_llamar_al_cliente_interno(): void
    {
        $calls = [];
        $breaker = new CircuitBreakerInterbankClient(
            $this->siempreFallaInterbankClient($calls),
            Cache::store('redis'),
            failureThreshold: 2,
            cooldownSeconds: 30,
            maxAttempts: 1, // sin reintentos internos, para contar fallas de forma predecible
        );

        // Primeras 2 llamadas: fallan y abren el circuito (umbral = 2).
        try {
            $breaker->ejecutar('X', 10);
        } catch (InterbankException) {
        }
        try {
            $breaker->ejecutar('X', 10);
        } catch (InterbankException) {
        }

        $this->assertCount(2, $calls, 'Las primeras 2 llamadas si debieron llegar al cliente interno');

        // Tercera llamada: el circuito ya deberia estar abierto.
        $this->expectException(CircuitOpenException::class);

        try {
            $breaker->ejecutar('X', 10);
        } finally {
            $this->assertCount(2, $calls, 'Con el circuito abierto, el cliente interno NO debio volver a llamarse');
        }
    }
}
