<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Clients\HttpDatosBasicosClient;
use BP\Common\Clients\HttpMovimientosClient;
use BP\Common\Clients\HttpTransferenciasClient;
use BP\Common\Clients\UpstreamServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class HttpUpstreamClientsTest extends TestCase
{
    private function clienteConRespuestas(array $respuestas): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($respuestas))]);
    }

    public function test_datos_basicos_decodifica_el_envelope_data(): void
    {
        $http = $this->clienteConRespuestas([
            new Response(200, [], json_encode(['data' => ['cliente_id' => '1001']])),
        ]);

        $cliente = (new HttpDatosBasicosClient($http, 'http://svc'))->obtenerCliente('1001', 'token-x');

        $this->assertSame('1001', $cliente['cliente_id']);
    }

    public function test_movimientos_propaga_una_falla_preservando_el_status_code(): void
    {
        $http = $this->clienteConRespuestas([
            new Response(404, [], json_encode(['error' => ['message' => 'no encontrado']])),
        ]);

        $client = new HttpMovimientosClient($http, 'http://svc');

        try {
            $client->listar('9999', 'token-x');
            $this->fail('Debio lanzar UpstreamServiceException');
        } catch (UpstreamServiceException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('no encontrado', $e->getMessage());
        }
    }

    public function test_transferencias_envia_el_idempotency_key_y_el_token(): void
    {
        $historial = [];
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(201, [], json_encode(['data' => ['transferencia_id' => 't1']])),
        ]));
        $handlerStack->push(\GuzzleHttp\Middleware::history($historial));
        $http = new Client(['handler' => $handlerStack]);

        (new HttpTransferenciasClient($http, 'http://svc'))->crear(['monto' => 10], 'idem-1', 'token-x');

        $peticion = $historial[0]['request'];
        $this->assertSame('idem-1', $peticion->getHeaderLine('Idempotency-Key'));
        $this->assertSame('Bearer token-x', $peticion->getHeaderLine('Authorization'));
    }
}
