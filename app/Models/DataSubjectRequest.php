<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSubjectRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'client_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'request_type',
        'status',
        'requester_name',
        'requester_email',
        'requester_phone',
        'received_channel',
        'details',
        'due_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
