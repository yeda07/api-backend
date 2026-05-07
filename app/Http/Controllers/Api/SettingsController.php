<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\PlatformInitService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(private readonly PlatformInitService $platformInitService)
    {
    }

    public function localization(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return $this->successResponse(array_merge([
            'tenant_uid' => $tenant?->uid,
        ], $this->platformInitService->localization($user)));
    }

    public function updateLocalization(Request $request)
    {
        try {
            $validated = $request->validate([
                'timezone' => 'sometimes|string|max:100',
                'currency' => 'sometimes|string|max:10',
                'date_format' => 'sometimes|string|max:50',
                'locale' => 'sometimes|string|max:20',
                'user_timezone' => 'sometimes|nullable|string|max:100',
            ]);

            $user = $request->user();
            $tenant = $user->tenant;

            $tenantPayload = [];

            foreach (['timezone', 'date_format', 'locale'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $tenantPayload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('currency', $validated)) {
                $currency = Currency::query()->where('code', strtoupper($validated['currency']))->first();

                if ($currency) {
                    $tenantPayload['currency_id'] = $currency->getKey();
                }
            }

            if ($tenant && $tenantPayload !== []) {
                $tenant->forceFill($tenantPayload)->save();
            }

            if (array_key_exists('user_timezone', $validated)) {
                $user->forceFill(['timezone' => $validated['user_timezone'] ?? $tenant?->timezone ?? 'UTC'])->save();
            }

            return $this->localization($request);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
