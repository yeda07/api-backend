<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    public function rates(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'from_currency' => 'nullable|string|max:10',
            'to_currency' => 'nullable|string|max:10',
            'from' => 'nullable|string|max:10',
            'to' => 'nullable|string|max:10',
            'rate_date' => 'nullable|date',
        ])->validate();

        $from = strtoupper($validated['from_currency'] ?? $validated['from'] ?? 'USD');
        $to = strtoupper($validated['to_currency'] ?? $validated['to'] ?? '');

        return ExchangeRate::query()
            ->when($from, fn ($query) => $query->where('from_currency', $from))
            ->when($to !== '', fn ($query) => $query->where('to_currency', $to))
            ->when(!empty($validated['rate_date']), fn ($query) => $query->whereDate('rate_date', $validated['rate_date']))
            ->orderByDesc('rate_date')
            ->get()
            ->map(fn (ExchangeRate $rate) => $this->formatRate($rate))
            ->values();
    }

    public function upsertRate(array $data): ExchangeRate
    {
        $validated = Validator::make($data, [
            'from_currency' => 'required|string|max:10',
            'to_currency' => 'required|string|max:10|different:from_currency',
            'rate' => 'required|numeric|min:0.000001',
            'rate_date' => 'nullable|date',
        ])->validate();

        return ExchangeRate::query()->updateOrCreate(
            [
                'from_currency' => strtoupper($validated['from_currency']),
                'to_currency' => strtoupper($validated['to_currency']),
                'rate_date' => $validated['rate_date'] ?? now()->toDateString(),
            ],
            [
                'rate' => $validated['rate'],
            ]
        );
    }

    public function convert(array $data): array
    {
        $validated = Validator::make($data, [
            'amount' => 'required|numeric|min:0',
            'from_currency' => 'required_without:from|string|max:10',
            'to_currency' => 'required_without:to|string|max:10',
            'from' => 'required_without:from_currency|string|max:10',
            'to' => 'required_without:to_currency|string|max:10',
            'rate_date' => 'nullable|date',
        ])->validate();

        $from = strtoupper($validated['from_currency'] ?? $validated['from']);
        $to = strtoupper($validated['to_currency'] ?? $validated['to']);

        if ($from === $to) {
            return [
                'amount' => (float) $validated['amount'],
                'from_currency' => $from,
                'to_currency' => $to,
                'rate' => 1.0,
                'converted_amount' => round((float) $validated['amount'], 2),
                'result' => round((float) $validated['amount'], 2),
                'rate_date' => $validated['rate_date'] ?? now()->toDateString(),
            ];
        }

        $exchangeRate = $this->getRateModel($from, $to, $validated['rate_date'] ?? null);
        $rate = (float) $exchangeRate->rate;
        $converted = round((float) $validated['amount'] * $rate, 2);

        return [
            'amount' => (float) $validated['amount'],
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'converted_amount' => $converted,
            'result' => $converted,
            'rate_date' => $exchangeRate->rate_date?->toDateString(),
        ];
    }

    public function getRate(string $from, string $to, ?string $date = null): float
    {
        return (float) $this->getRateModel($from, $to, $date)->rate;
    }

    private function getRateModel(string $from, string $to, ?string $date = null): ExchangeRate
    {
        $query = ExchangeRate::query()
            ->where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to));

        if ($date) {
            $query->whereDate('rate_date', $date);
        }

        $rate = $query->latest('rate_date')->first();

        if (!$rate) {
            throw ValidationException::withMessages([
                'rate' => ['No existe una tasa de cambio definida para la conversion solicitada'],
            ]);
        }

        return $rate;
    }

    private function formatRate(ExchangeRate $rate): array
    {
        $currency = Currency::query()->where('code', $rate->to_currency)->first();
        $lastUpdate = $rate->rate_date?->toDateString();

        return [
            'uid' => $rate->uid,
            'code' => $rate->to_currency,
            'name' => $currency?->name ?? $rate->to_currency,
            'rate' => (float) $rate->rate,
            'last_update' => $lastUpdate,
            'status' => $rate->rate_date && $rate->rate_date->greaterThanOrEqualTo(now()->subDay()->startOfDay()) ? 'active' : 'outdated',
            'from_currency' => $rate->from_currency,
            'to_currency' => $rate->to_currency,
            'rate_date' => $lastUpdate,
        ];
    }
}
