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
        'provider_message_id',
        'provider_phone_number_id',
        'provider_display_phone_number',
        'queued_at',
        'processing_started_at',
        'last_attempted_at',
        'attempts_count',
        'status',
        'error_code',
        'error_message',
        'status_payload',
        'provider_status_payload',
        'delivered_at',
        'last_response',
        'last_response_at',
    ];

    protected $casts = [
        'queued_at'        => 'datetime',
        'processing_started_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'delivered_at'     => 'datetime',
        'last_response_at' => 'datetime',
        'status_payload'   => 'array',
        'provider_status_payload' => 'array',
        'attempts_count' => 'integer',
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
