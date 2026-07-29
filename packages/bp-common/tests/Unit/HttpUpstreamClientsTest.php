<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Clients\HttpCustomerDataClient;
use BP\Common\Clients\HttpMovementsClient;
use BP\Common\Clients\HttpTransfersClient;
use BP\Common\Clients\UpstreamServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class HttpUpstreamClientsTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    public function test_customer_data_decodes_the_data_envelope(): void
    {
        $http = $this->clientWithResponses([
            new Response(200, [], json_encode(['data' => ['customer_id' => '1001']])),
        ]);

        $customer = (new HttpCustomerDataClient($http, 'http://svc'))->getCustomer('1001', 'token-x');

        $this->assertSame('1001', $customer['customer_id']);
    }

    public function test_movements_propagates_a_failure_preserving_the_status_code(): void
    {
        $http = $this->clientWithResponses([
            new Response(404, [], json_encode(['error' => ['message' => 'not found']])),
        ]);

        $client = new HttpMovementsClient($http, 'http://svc');

        try {
            $client->list('9999', 'token-x');
            $this->fail('Should have thrown UpstreamServiceException');
        } catch (UpstreamServiceException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('not found', $e->getMessage());
        }
    }

    public function test_transfers_sends_the_idempotency_key_and_the_token(): void
    {
        $history = [];
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(201, [], json_encode(['data' => ['transfer_id' => 't1']])),
        ]));
        $handlerStack->push(Middleware::history($history));
        $http = new Client(['handler' => $handlerStack]);

        (new HttpTransfersClient($http, 'http://svc'))->create(['amount' => 10], 'idem-1', 'token-x');

        $request = $history[0]['request'];
        $this->assertSame('idem-1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('Bearer token-x', $request->getHeaderLine('Authorization'));
    }
}
