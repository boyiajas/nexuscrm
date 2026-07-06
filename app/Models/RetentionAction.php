<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetentionAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'retention_policy_id',
        'bank_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'dataset',
        'action_type',
        'status',
        'scope_summary',
        'notes',
        'approved_at',
        'executed_at',
        'execution_result',
    ];

    protected $casts = [
        'scope_summary' => 'array',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function policy()
    {
        return $this->belongsTo(RetentionPolicy::class, 'retention_policy_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
