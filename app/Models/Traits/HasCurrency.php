<?php

namespace App\Models\Traits;

trait HasCurrency
{
    public function formatCurrency($amount)
    {
        $currency = $this->currency ?? 'USD';

        $symbols = [
            'USD' => '$',
            'COP' => '$',
            'EUR' => '€'
        ];

        $symbol = $symbols[$currency] ?? $currency;

        return $symbol . ' ' . number_format($amount, 2, ',', '.');
    }
}
