<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Address\StoreShippingAddressRequest;
use App\Http\Requests\Api\V1\Customer\Address\UpdateShippingAddressRequest;
use App\Http\Resources\Api\V1\ShippingAddressResource;
use App\Services\Customer\ShippingAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingAddressController extends Controller
{
    public function index(Request $request, ShippingAddressService $service): JsonResponse
    {
        $addresses = $service->list($request->user());

        return $this->successResponse(
            ShippingAddressResource::collection($addresses),
            'Shipping addresses retrieved successfully.',
        );
    }

    public function store(
        StoreShippingAddressRequest $request,
        ShippingAddressService $service,
    ): JsonResponse {
        $address = $service->create($request->user(), $request->validated());

        return $this->successResponse(
            new ShippingAddressResource($address),
            'Shipping address created successfully.',
            201,
        );
    }

    public function show(
        Request $request,
        int $id,
        ShippingAddressService $service,
    ): JsonResponse {
        $address = $service->findForUser($request->user(), $id);

        return $this->successResponse(
            new ShippingAddressResource($address),
            'Shipping address retrieved successfully.',
        );
    }

    public function update(
        UpdateShippingAddressRequest $request,
        int $id,
        ShippingAddressService $service,
    ): JsonResponse {
        $address = $service->update($request->user(), $id, $request->validated());

        return $this->successResponse(
            new ShippingAddressResource($address),
            'Shipping address updated successfully.',
        );
    }

    public function destroy(
        Request $request,
        int $id,
        ShippingAddressService $service,
    ): JsonResponse {
        $service->delete($request->user(), $id);

        return $this->successResponse(
            null,
            'Shipping address deleted successfully.',
        );
    }

    public function setDefault(
        Request $request,
        int $id,
        ShippingAddressService $service,
    ): JsonResponse {
        $address = $service->setDefault($request->user(), $id);

        return $this->successResponse(
            new ShippingAddressResource($address),
            'Default shipping address updated successfully.',
        );
    }
}
