<?php

namespace App\Models;

use App\Models\Traits\HasCurrency;
use App\Models\Traits\HasPublicUid;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasPublicUid, HasCurrency;

    protected $fillable = [
        'uid',
        'name',
        'plan_id',
        'currency_id',
        'is_active',
        'expires_at',
    ];

    protected $hidden = [
        'id',
        'plan_id',
        'currency_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function relations()
    {
        return $this->hasMany(Relation::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && (
            !$this->expires_at || now()->lt($this->expires_at)
        );
    }
}
