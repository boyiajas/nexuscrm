<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'client_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'complaint_type',
        'severity',
        'status',
        'title',
        'details',
        'escalation_required',
        'regulator_notification_required',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'escalation_required' => 'boolean',
        'regulator_notification_required' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
