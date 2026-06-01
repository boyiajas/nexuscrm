<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'department',
        'department_id',
        'last_login_at',
        'username',
        'first_name',
        'middle_initial',
        'last_name',
        'primary_phone',
        'secondary_phone',
        'inactivity_timeout',
        'is_provider',
        'is_time_clock_user',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'is_provider' => 'boolean',
        'is_time_clock_user' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function isSuperAdmin()
    {
        return $this->role === 'SUPER_ADMIN';
    }

    public function isManager()
    {
        return $this->role === 'MANAGER';
    }

    public function isStaff()
    {
        return $this->role === 'STAFF';
    }

    public function resolvedDepartmentId(): ?int
    {
        if ($this->department_id) {
            return (int) $this->department_id;
        }

        if (!empty($this->department)) {
            return Department::query()
                ->where('name', $this->department)
                ->value('id');
        }

        return null;
    }
}
