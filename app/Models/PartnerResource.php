<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\HasTenantRelation;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class PartnerResource extends Model
{
    use HasPublicUid, HasTenantRelation, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'title',
        'type',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'file_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'partner_access')->withTimestamps();
    }
}
