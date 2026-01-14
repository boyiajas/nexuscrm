<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignWhatsappMessage extends Model
{
    protected $fillable = [
        'campaign_id',
        'template_sid',
        'template_name',
        'name',
        'preview_body',
        'sent_at',
        'total',
        'delivered',
        'failed',
        'pending',
        'enable_live_chat',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
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
