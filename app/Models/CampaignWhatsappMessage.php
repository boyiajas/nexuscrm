<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignWhatsappMessage extends Model
{
    protected $fillable = [
        'campaign_id',
        'created_by_user_id',
        'mode',
        'template_sid',
        'template_name',
        'provider_phone_number_id',
        'provider_display_phone_number',
        'name',
        'preview_body',
        'template_variables',
        'whatsapp_flow_id',
        'flow_name',
        'flow_definition',
        'sent_at',
        'total',
        'delivered',
        'failed',
        'pending',
        'status',
        'queued_at',
        'processing_started_at',
        'completed_at',
        'paused_at',
        'pause_reason',
        'last_processed_at',
        'messages_per_second',
        'enable_live_chat',
        'track_responses',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'queued_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'last_processed_at' => 'datetime',
        'template_variables' => 'array',
        'flow_definition' => 'array',
        'track_responses' => 'boolean',
        'enable_live_chat' => 'boolean',
        'messages_per_second' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients()
    {
        return $this->hasMany(CampaignWhatsappRecipient::class, 'whatsapp_message_id');
    }

    public function autoReplies()
    {
        return $this->hasMany(CampaignWhatsappAutoReply::class, 'campaign_whatsapp_message_id');
    }
}
