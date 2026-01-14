<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignEmailMessage extends Model
{
    use HasFactory;

    protected $table = 'campaign_email_messages';

    protected $fillable = [
        'campaign_id',
        'subject',
        'preview_body',
        'sent_at',
        'total',
        'delivered',
        'bounced',
        'opened',
        'clicked',
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
        return $this->hasMany(CampaignEmailRecipient::class, 'email_message_id');
    }
}
