<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignWhatsappMessage extends Model
{
    protected $fillable = [
        'campaign_id',
        'mode',
        'template_sid',
        'template_name',
        'name',
        'preview_body',
        'whatsapp_flow_id',
        'flow_name',
        'flow_definition',
        'sent_at',
        'total',
        'delivered',
        'failed',
        'pending',
        'enable_live_chat',
        'track_responses',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'flow_definition' => 'array',
        'track_responses' => 'boolean',
        'enable_live_chat' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function recipients()
    {
        return $this->hasMany(CampaignWhatsappRecipient::class, 'whatsapp_message_id');
    }
}
