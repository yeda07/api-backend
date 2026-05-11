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
        'description',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'download_count',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'file_path',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'material_type',
        'file_name',
        'file_size',
        'uploaded_at',
        'uploaded_by',
        'tags',
    ];

    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'partner_access')->withTimestamps();
    }

    public function getMaterialTypeAttribute(): string
    {
        return match ($this->type) {
            'training' => 'training',
            default => 'deck',
        };
    }

    public function getFileNameAttribute(): ?string
    {
        return $this->original_name;
    }

    public function getFileSizeAttribute(): string
    {
        $size = (int) $this->size;

        if ($size >= 1048576) {
            return round($size / 1048576, 1).' MB';
        }

        return round($size / 1024, 1).' KB';
    }

    public function getUploadedAtAttribute(): ?string
    {
        return $this->created_at?->toISOString();
    }

    public function getUploadedByAttribute(): ?string
    {
        return null;
    }

    public function getTagsAttribute(): array
    {
        return [];
    }
}
