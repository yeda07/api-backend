<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasPublicUid;

class Plan extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'name',
        'price',
        'max_users',
        'tier',
        'billing_interval',
        'status',
        'features',
        'max_records',
        'max_accounts',
        'max_contacts',
        'max_entities',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'features' => 'array',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
