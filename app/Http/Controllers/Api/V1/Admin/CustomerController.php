<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Customer\AdminCustomerIndexRequest;
use App\Http\Resources\Api\V1\AdminCustomerResource;
use App\Http\Resources\Api\V1\AdminOrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected AdminCustomerService $customerService,
    ) {}

    /**
     * Display a paginated listing of customer accounts.
     */
    public function index(AdminCustomerIndexRequest $request): JsonResponse
    {
        $customers = $this->customerService
            ->paginateCustomers($request->validated())
            ->through(fn (User $user) => new AdminCustomerResource($user));

        return $this->paginatedResponse($customers, 'Customers retrieved successfully.');
    }

    /**
     * Display the specified customer account details.
     */
    public function show(int|string $id): JsonResponse
    {
        $customer = $this->customerService->getCustomer($id);

        return $this->successResponse(
            new AdminCustomerResource($customer),
            'Customer details retrieved successfully.'
        );
    }

    /**
     * Display the specified customer's order history.
     */
    public function orders(Request $request, int|string $id): JsonResponse
    {
        $customer = $this->customerService->getCustomer($id);
        $orders = $this->customerService
            ->paginateCustomerOrders($customer, $request->query())
            ->through(fn (Order $order) => new AdminOrderResource($order));

        return $this->paginatedResponse($orders, 'Customer orders retrieved successfully.');
    }
}
