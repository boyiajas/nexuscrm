<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetentionPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'dataset',
        'retention_days',
        'archive_after_days',
        'delete_after_days',
        'legal_hold_allowed',
        'status',
        'notes',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'archive_after_days' => 'integer',
        'delete_after_days' => 'integer',
        'legal_hold_allowed' => 'boolean',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function actions()
    {
        return $this->hasMany(RetentionAction::class);
    }
}
