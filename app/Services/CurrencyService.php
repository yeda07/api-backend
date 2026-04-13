<?php

namespace App\Services;

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
            'rate_date' => 'nullable|date',
        ])->validate();

        return ExchangeRate::query()
            ->when(!empty($validated['from_currency']), fn ($query) => $query->where('from_currency', strtoupper($validated['from_currency'])))
            ->when(!empty($validated['to_currency']), fn ($query) => $query->where('to_currency', strtoupper($validated['to_currency'])))
            ->when(!empty($validated['rate_date']), fn ($query) => $query->whereDate('rate_date', $validated['rate_date']))
            ->orderByDesc('rate_date')
            ->get();
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
            'from_currency' => 'required|string|max:10',
            'to_currency' => 'required|string|max:10',
            'rate_date' => 'nullable|date',
        ])->validate();

        $from = strtoupper($validated['from_currency']);
        $to = strtoupper($validated['to_currency']);

        if ($from === $to) {
            return [
                'amount' => (float) $validated['amount'],
                'from_currency' => $from,
                'to_currency' => $to,
                'rate' => 1.0,
                'converted_amount' => round((float) $validated['amount'], 2),
            ];
        }

        $rate = $this->getRate($from, $to, $validated['rate_date'] ?? null);

        return [
            'amount' => (float) $validated['amount'],
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'converted_amount' => round((float) $validated['amount'] * $rate, 2),
        ];
    }

    public function getRate(string $from, string $to, ?string $date = null): float
    {
        $query = ExchangeRate::query()
            ->where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to));

        if ($date) {
            $query->whereDate('rate_date', $date);
        }

        $rate = $query->latest('rate_date')->value('rate');

        if (!$rate) {
            throw ValidationException::withMessages([
                'rate' => ['No existe una tasa de cambio definida para la conversion solicitada'],
            ]);
        }

        return (float) $rate;
    }
}
