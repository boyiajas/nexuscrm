<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignWhatsappRecipient extends Model
{
    protected $fillable = [
        'whatsapp_message_id',
        'client_id',
        'phone',
        'message_sid',
        'status',
        'error_code',
        'error_message',
        'status_payload',
        'delivered_at',
        'last_response',
        'last_response_at',
    ];

    protected $casts = [
        'delivered_at'     => 'datetime',
        'last_response_at' => 'datetime',
        'status_payload'   => 'array',
    ];

    public function message()
    {
        return $this->belongsTo(CampaignWhatsappMessage::class, 'whatsapp_message_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
