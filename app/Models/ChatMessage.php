<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_session_id',
        'sender',
        'content',
        'media_url',
        'media_type',
        'is_template',
        'sent_at',
        'provider_message_id',
        'delivery_status',
        'delivery_status_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivery_status_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
