<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\PlatformInitService;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(private readonly PlatformInitService $platformInitService) {}

    public function localization(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return $this->successResponse(array_merge([
            'tenant_uid' => $tenant?->uid,
        ], $this->platformInitService->localization($user)));
    }

    public function localizationOptions()
    {
        return $this->successResponse([
            'timezones' => $this->timezoneOptions(),
            'currencies' => $this->currencyOptions(),
            'date_formats' => ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'],
            'locales' => [
                ['value' => 'es-CO', 'label' => 'Español (Colombia)'],
                ['value' => 'es-PE', 'label' => 'Español (Perú)'],
                ['value' => 'es-MX', 'label' => 'Español (México)'],
                ['value' => 'es-AR', 'label' => 'Español (Argentina)'],
                ['value' => 'en-US', 'label' => 'Inglés (Estados Unidos)'],
            ],
        ]);
    }

    public function countries()
    {
        return $this->successResponse(
            collect($this->countryCityOptions())
                ->keys()
                ->map(fn (string $country) => ['name' => $country])
                ->values()
                ->all()
        );
    }

    public function cities(Request $request)
    {
        $country = $request->query('country');
        $search = mb_strtolower((string) $request->query('search', ''));
        $cities = collect($this->countryCityOptions()[$country] ?? [])
            ->when($search !== '', fn ($items) => $items->filter(fn (string $city) => str_contains(mb_strtolower($city), $search)))
            ->values()
            ->map(fn (string $city) => ['name' => $city])
            ->all();

        return $this->successResponse($cities);
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

    private function timezoneOptions(): array
    {
        $preferred = [
            'America/Bogota',
            'America/Lima',
            'America/Mexico_City',
            'America/Argentina/Buenos_Aires',
            'America/Santiago',
            'America/New_York',
            'UTC',
        ];

        return collect(array_merge($preferred, DateTimeZone::listIdentifiers()))
            ->unique()
            ->values()
            ->all();
    }

    private function currencyOptions(): array
    {
        $currencies = Currency::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency) => [
                'code' => $currency->code,
                'label' => $currency->name,
                'symbol' => $currency->symbol,
            ])
            ->values()
            ->all();

        if ($currencies !== []) {
            return $currencies;
        }

        return [
            ['code' => 'COP', 'label' => 'Peso colombiano', 'symbol' => '$'],
            ['code' => 'USD', 'label' => 'US Dollar', 'symbol' => 'US$'],
            ['code' => 'EUR', 'label' => 'Euro', 'symbol' => '€'],
            ['code' => 'MXN', 'label' => 'Peso mexicano', 'symbol' => '$'],
            ['code' => 'PEN', 'label' => 'Sol peruano', 'symbol' => 'S/'],
            ['code' => 'ARS', 'label' => 'Peso argentino', 'symbol' => '$'],
            ['code' => 'CLP', 'label' => 'Peso chileno', 'symbol' => '$'],
        ];
    }

    private function countryCityOptions(): array
    {
        return [
            'Argentina' => ['Buenos Aires', 'Cordoba', 'Rosario', 'Mendoza'],
            'Chile' => ['Santiago', 'Valparaiso', 'Concepcion', 'La Serena'],
            'Colombia' => ['Bogota', 'Medellin', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga'],
            'Mexico' => ['Ciudad de Mexico', 'Guadalajara', 'Monterrey', 'Puebla', 'Queretaro'],
            'Peru' => ['Lima', 'Arequipa', 'Trujillo', 'Cusco'],
            'United States' => ['New York', 'Miami', 'Los Angeles', 'Chicago', 'Houston'],
        ];
    }
}
