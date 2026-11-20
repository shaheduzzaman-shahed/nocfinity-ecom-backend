<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CurrencySettingResource;
use App\Services\Settings\CurrencyService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function currency(CurrencyService $service): JsonResponse
    {
        $currency = $service->getStoreCurrency();

        return $this->successResponse(
            new CurrencySettingResource(['currency' => $currency]),
            'Store currency retrieved successfully.',
        );
    }
}
