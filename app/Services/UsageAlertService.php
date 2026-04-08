<?php

namespace App\Services;

class UsageAlertService
{
    public static function check($percentage)
    {
        $alerts = [];

        foreach ($percentage as $key => $value) {
            if ($value !== null && $value >= 80) {
                $alerts[$key] = "Estas al {$value}% del limite de {$key}";
            }
        }

        return $alerts;
    }
}
