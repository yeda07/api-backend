<?php

namespace App\Models\Traits;

use Carbon\Carbon;

trait HasUserTimezone
{
    protected function convertToUserTimezone($value)
    {
        if (!$value) return null;

        $user = auth()->user();

        $timezone = $user->timezone ?? $user->tenant->timezone ?? 'UTC';

        return Carbon::parse($value)
            ->timezone($timezone)
            ->format($user->tenant->date_format ?? 'Y-m-d H:i:s');
    }

    public function getCreatedAtAttribute($value)
    {
        return $this->convertToUserTimezone($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        return $this->convertToUserTimezone($value);
    }
}
