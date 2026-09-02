<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'client_id',
        'agent_id',
        'client_name',
        'phone',
        'status',
        'platform',
        'waba_phone_number_id',
        'last_message',
        'unread_count',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'chat_session_id')->latestOfMany();
    }

    protected static function booted()
    {
        static::deleting(function ($session) {
            $session->messages()->delete();
        });
    }
}
