<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSendAttempt extends Model
{
    protected $table = 'whatsapp_send_attempts';

    protected $fillable = [
        'campaign_whatsapp_message_id',
        'campaign_whatsapp_recipient_id',
        'client_id',
        'user_id',
        'attempt_date',
        'attempted_at',
        'status',
        'provider_message_id',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'attempt_date' => 'date',
        'attempted_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(CampaignWhatsappMessage::class, 'campaign_whatsapp_message_id');
    }

    public function recipient()
    {
        return $this->belongsTo(CampaignWhatsappRecipient::class, 'campaign_whatsapp_recipient_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
