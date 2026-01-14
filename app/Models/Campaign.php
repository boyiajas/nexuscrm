<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channels',
        'status',
        'scheduled_at',
        'template_body',
        'whatsapp_from',
    ];

    protected $casts = [
        'channels'     => 'array',
        'scheduled_at' => 'datetime',
    ];

    // Many-to-many departments
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'campaign_department')
            ->withTimestamps();
    }

    // Clients attached to this campaign
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'campaign_clients')
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

    // Channel batches
    public function whatsappMessages()
    {
        return $this->hasMany(CampaignWhatsappMessage::class);
    }

    public function emailMessages()
    {
        return $this->hasMany(CampaignEmailMessage::class);
    }

    public function smsMessages()
    {
        return $this->hasMany(CampaignSmsMessage::class);
    }
}
