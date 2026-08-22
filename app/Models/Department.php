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
        'whatsapp_account_id',
    ];

    protected $casts = [
        'secondary_whatsapp_numbers' => 'array',
    ];

    public function banks()
    {
        return $this->belongsToMany(Bank::class, 'bank_department')->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withTimestamps();
    }
}
