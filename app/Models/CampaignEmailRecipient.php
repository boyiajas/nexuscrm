<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignEmailRecipient extends Model
{
    use HasFactory;

    protected $table = 'campaign_email_recipients';

    protected $fillable = [
        'email_message_id',
        'client_id',
        'email',
        'status',
        'delivered_at',
        'opened_at',
        'clicked_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'opened_at'    => 'datetime',
        'clicked_at'   => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(CampaignEmailMessage::class, 'email_message_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
