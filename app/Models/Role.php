<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'whatsapp_daily_limit',
        'watermark_enabled',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'whatsapp_daily_limit' => 'integer',
        'watermark_enabled' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role')->withTimestamps();
    }

    public function hasPermission(string $code): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('code', $code);
        }

        return $this->permissions()->where('code', $code)->exists();
    }
}
