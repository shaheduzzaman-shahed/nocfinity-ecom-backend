<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Settings\UpdateShippingSettingsRequest;
use App\Http\Resources\Api\V1\ShippingSettingResource;
use App\Services\Settings\ShippingSettingService;
use Illuminate\Http\JsonResponse;

class ShippingSettingController extends Controller
{
    public function show(ShippingSettingService $service): JsonResponse
    {
        $settings = $service->getShippingSettings();

        return $this->successResponse(
            new ShippingSettingResource($settings),
            'Shipping settings retrieved successfully.',
        );
    }

    public function update(
        UpdateShippingSettingsRequest $request,
        ShippingSettingService $service,
    ): JsonResponse {
        $settings = $service->updateShippingSettings($request->validated());

        return $this->successResponse(
            new ShippingSettingResource($settings),
            'Shipping settings updated successfully.',
        );
    }
}
