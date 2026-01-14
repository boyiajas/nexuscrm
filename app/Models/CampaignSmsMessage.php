<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignSmsMessage extends Model
{
    use HasFactory;

    protected $table = 'campaign_sms_messages';

    protected $fillable = [
        'campaign_id',
        'text',
        'sent_at',
        'total',
        'delivered',
        'failed',
        'pending',
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
        return $this->hasMany(CampaignSmsRecipient::class, 'sms_message_id');
    }
}
