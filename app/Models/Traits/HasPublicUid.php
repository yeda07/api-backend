<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasPublicUid
{
    protected static function bootHasPublicUid()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uid';
    }
}
