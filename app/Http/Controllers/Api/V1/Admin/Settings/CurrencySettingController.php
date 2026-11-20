<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Settings\UpdateCurrencyRequest;
use App\Http\Resources\Api\V1\CurrencySettingResource;
use App\Services\Settings\CurrencyService;
use Illuminate\Http\JsonResponse;

class CurrencySettingController extends Controller
{
    public function show(CurrencyService $service): JsonResponse
    {
        $currency = $service->getStoreCurrency();

        return $this->successResponse(
            new CurrencySettingResource(['currency' => $currency]),
            'Store currency retrieved successfully.',
        );
    }

    public function update(
        UpdateCurrencyRequest $request,
        CurrencyService $service,
    ): JsonResponse {
        $currency = $service->updateStoreCurrency((string) $request->validated('currency'));

        return $this->successResponse(
            new CurrencySettingResource(['currency' => $currency]),
            'Store currency updated successfully.',
        );
    }
}
