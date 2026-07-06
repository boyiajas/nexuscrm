<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'bank_id',
        'type',
        'severity',
        'status',
        'title',
        'description',
        'affected_module',
        'affected_records_count',
        'suspected_personal_data_exposed',
        'regulator_notification_required',
        'bank_notification_required',
        'contained_at',
        'closed_at',
        'reported_by_user_id',
        'assigned_to_user_id',
    ];

    protected $casts = [
        'affected_records_count' => 'integer',
        'suspected_personal_data_exposed' => 'boolean',
        'regulator_notification_required' => 'boolean',
        'bank_notification_required' => 'boolean',
        'contained_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function events()
    {
        return $this->hasMany(SecurityIncidentEvent::class)->latest();
    }
}
