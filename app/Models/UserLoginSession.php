<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'session_uuid',
        'ip_address',
        'user_agent',
        'authentication_method',
        'authenticated_at',
        'last_activity_at',
        'logged_out_at',
        'logout_reason',
    ];

    protected $casts = [
        'authenticated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
