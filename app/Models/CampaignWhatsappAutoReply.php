<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignWhatsappAutoReply extends Model
{
    protected $fillable = [
        'campaign_whatsapp_message_id',
        'trigger_keyword',
        'template_sid',
        'template_name',
        'template_variables',
    ];

    protected $casts = [
        'template_variables' => 'array',
    ];

    public function message()
    {
        return $this->belongsTo(CampaignWhatsappMessage::class, 'campaign_whatsapp_message_id');
    }
}
