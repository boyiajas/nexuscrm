<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransferRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_transfer_profile_id',
        'bank_id',
        'triggered_by_user_id',
        'run_type',
        'status',
        'files_discovered',
        'files_pulled',
        'files_failed',
        'result_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'files_discovered' => 'integer',
        'files_pulled' => 'integer',
        'files_failed' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(BankTransferProfile::class, 'bank_transfer_profile_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function triggerUser()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
