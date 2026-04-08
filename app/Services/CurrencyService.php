<?php

namespace App\Services;

class CurrencyService
{
    public static function format($amount)
    {
        $user = auth()->user();
        $tenant = $user?->tenant;
        $currency = $tenant?->currency;

        // 🔒 fallback seguro (evita errores)
        if (!$currency) {
            return number_format($amount, 2);
        }

        return $currency->symbol . ' ' . number_format(
            $amount,
            $currency->decimal_places,
            $currency->decimal_separator,
            $currency->thousands_separator
        );
    }
}
