<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envuelve_los_datos_en_data_y_meta(): void
    {
        $response = ApiResponse::success(['id' => 1], ['total' => 1]);

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 1], $payload['data']);
        $this->assertSame(['total' => 1], $payload['meta']);
    }

    public function test_error_envuelve_el_mensaje_codigo_y_errores(): void
    {
        $response = ApiResponse::error('Solicitud invalida', 'validation_error', [['field' => 'monto', 'message' => 'requerido']], 422);

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Solicitud invalida', $payload['error']['message']);
        $this->assertSame('validation_error', $payload['error']['code']);
        $this->assertCount(1, $payload['error']['errors']);
    }
}
