<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'assigned_to_id',
        'tags',
        'last_contacted_at',
        'id_number',
        'bank_name',
        'account_number',
        'branch_code',
        // Note: removed 'department' column since we're using many-to-many
    ];

    protected $casts = [
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
    ];

    // Many-to-many departments (NEW)
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'client_department')
            ->withTimestamps();
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    // Campaigns this client belongs to (EXISTING - matches your pivot table)
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_clients')
            ->using(CampaignClient::class)
            ->withPivot([
                'whatsapp_status',
                'whatsapp_sent_at',
                'email_status',
                'email_sent_at',
                'sms_status',
                'sms_sent_at',
            ])
            ->withTimestamps();
    }

    // Accessor for backward compatibility with existing code that might reference department
    public function getDepartmentAttribute()
    {
        $firstDept = $this->departments->first();
        return $firstDept ? $firstDept->name : null;
    }

    // Helper method to get department names
    public function getDepartmentNamesAttribute()
    {
        return $this->departments->pluck('name')->join(', ');
    }

    // Helper method to get department IDs
    public function getDepartmentIdsAttribute()
    {
        return $this->departments->pluck('id')->toArray();
    }
}
