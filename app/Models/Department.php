<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'description',
        'primary_whatsapp_number',
        'secondary_whatsapp_numbers',
    ];

    protected $casts = [
        'secondary_whatsapp_numbers' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withTimestamps();
    }
}
