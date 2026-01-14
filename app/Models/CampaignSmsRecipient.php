<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignSmsRecipient extends Model
{
    use HasFactory;

    protected $table = 'campaign_sms_recipients';

    protected $fillable = [
        'sms_message_id',
        'client_id',
        'phone',
        'status',
        'delivered_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(CampaignSmsMessage::class, 'sms_message_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
