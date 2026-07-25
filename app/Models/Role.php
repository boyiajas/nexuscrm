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
}
