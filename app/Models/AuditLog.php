<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'ip_address',
        'meta',
        'logged_at',
    ];

    protected $casts = [
        'meta'      => 'array',       // JSON
        'logged_at' => 'datetime',    // Carbon datetime
    ];

    /**
     * Relationship: audit log belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Optional: scopes if you want to use them in the controller easily
     */
    public function scopeModule($query, $module)
    {
        if ($module && $module !== 'all') {
            $query->where('module', $module);
        }
        return $query;
    }

    public function scopeUserId($query, $userId)
    {
        if ($userId && $userId !== 'all') {
            $query->where('user_id', $userId);
        }
        return $query;
    }

    public function scopeDateFrom($query, $date)
    {
        if ($date) {
            $query->whereDate('logged_at', '>=', $date);
        }
        return $query;
    }

    public function scopeDateTo($query, $date)
    {
        if ($date) {
            $query->whereDate('logged_at', '<=', $date);
        }
        return $query;
    }

    public function scopeSearch($query, $term)
    {
        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', "%$term%")
                    ->orWhere('module', 'like', "%$term%")
                    ->orWhere('ip_address', 'like', "%$term%")
                    ->orWhere('meta', 'like', "%$term%");
            });
        }
        return $query;
    }
}
