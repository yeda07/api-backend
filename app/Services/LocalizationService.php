<?php
namespace App\Services;

use Carbon\Carbon;

class LocalizationService
{
    public static function formatDate($date)
    {
        $user = auth()->user();
        $timezone = $user->timezone ?? $user->tenant->timezone;

        return Carbon::parse($date)
            ->timezone($timezone)
            ->format($user->tenant->date_format);
    }
}
