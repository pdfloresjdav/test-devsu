<?php

namespace Tests\Unit;

use App\Contracts\CoreBankingClient;
use App\Contracts\CustomerProfileClient;
use App\Services\CustomerCompositionService;
use PHPUnit\Framework\TestCase;

class CustomerCompositionServiceTest extends TestCase
{
    public function test_composes_the_core_and_complementary_data_into_a_single_contract(): void
    {
        $coreBanking = $this->createMock(CoreBankingClient::class);
        $coreBanking->method('getBasicData')->with('1001')->willReturn([
            'customer_id' => '1001',
            'name' => 'Ana Torres',
            'document' => '1234567890',
            'products' => [['type' => 'savings_account', 'number' => '0011', 'status' => 'active']],
        ]);

        $customerProfile = $this->createMock(CustomerProfileClient::class);
        $customerProfile->method('getProfile')->with('1001')->willReturn([
            'customer_id' => '1001',
            'segment' => 'preferred',
            'email' => 'ana@example.com',
            'phone' => '+57 300 000 0000',
            'preferences' => ['language' => 'es'],
        ]);

        $service = new CustomerCompositionService($coreBanking, $customerProfile);
        $result = $service->compose('1001');

        $this->assertSame('1001', $result['customer_id']);
        $this->assertSame('Ana Torres', $result['name']);
        $this->assertSame('preferred', $result['segment']);
        $this->assertSame('ana@example.com', $result['contact']['email']);
        $this->assertSame(['language' => 'es'], $result['preferences']);
    }
}
