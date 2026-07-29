<?php

namespace Tests\Unit;

use App\Clients\FakeCoreBankingClient;
use App\Clients\FakeCustomerProfileClient;
use App\Contracts\CustomerNotFoundException;
use PHPUnit\Framework\TestCase;

class FakeClientsTest extends TestCase
{
    public function test_fake_core_banking_returns_data_for_a_known_customer(): void
    {
        $customer = (new FakeCoreBankingClient)->getBasicData('1001');

        $this->assertSame('Ana Torres', $customer['name']);
        $this->assertNotEmpty($customer['products']);
    }

    public function test_fake_core_banking_throws_an_exception_for_an_unknown_customer(): void
    {
        $this->expectException(CustomerNotFoundException::class);
        (new FakeCoreBankingClient)->getBasicData('9999');
    }

    public function test_fake_customer_profile_returns_profile_for_a_known_customer(): void
    {
        $profile = (new FakeCustomerProfileClient)->getProfile('1002');

        $this->assertSame('standard', $profile['segment']);
    }

    public function test_fake_customer_profile_throws_an_exception_for_an_unknown_customer(): void
    {
        $this->expectException(CustomerNotFoundException::class);
        (new FakeCustomerProfileClient)->getProfile('9999');
    }
}
