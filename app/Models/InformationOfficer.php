<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InformationOfficer extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'officer_type',
        'name',
        'title',
        'email',
        'phone',
        'status',
        'notes',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
