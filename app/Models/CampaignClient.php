<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignClient extends Model
{
    use HasFactory;

    protected $table = 'campaign_clients';

    protected $fillable = [
        'campaign_id',
        'client_id',

        'whatsapp_status',
        'whatsapp_sent_at',

        'email_status',
        'email_sent_at',

        'sms_status',
        'sms_sent_at',
    ];

    protected $casts = [
        'whatsapp_sent_at' => 'datetime',
        'email_sent_at'    => 'datetime',
        'sms_sent_at'      => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
