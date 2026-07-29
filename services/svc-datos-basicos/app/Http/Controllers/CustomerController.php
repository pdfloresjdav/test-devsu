<?php

namespace App\Http\Controllers;

use App\Contracts\CustomerNotFoundException;
use App\Services\CustomerCompositionService;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerCompositionService $composition)
    {
    }

    public function show(string $customerId): JsonResponse
    {
        try {
            $customer = $this->composition->compose($customerId);
        } catch (CustomerNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 'customer_not_found', status: 404);
        }

        return ApiResponse::success($customer);
    }
}
