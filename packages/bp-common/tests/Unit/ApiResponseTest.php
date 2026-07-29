<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_wraps_the_data_in_data_and_meta(): void
    {
        $response = ApiResponse::success(['id' => 1], ['total' => 1]);

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 1], $payload['data']);
        $this->assertSame(['total' => 1], $payload['meta']);
    }

    public function test_error_wraps_the_message_code_and_errors(): void
    {
        $response = ApiResponse::error('Invalid request', 'validation_error', [['field' => 'amount', 'message' => 'required']], 422);

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Invalid request', $payload['error']['message']);
        $this->assertSame('validation_error', $payload['error']['code']);
        $this->assertCount(1, $payload['error']['errors']);
    }
}
