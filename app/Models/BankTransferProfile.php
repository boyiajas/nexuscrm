<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransferProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'name',
        'protocol',
        'environment',
        'status',
        'host',
        'port',
        'username',
        'password',
        'private_key',
        'remote_path',
        'archive_path',
        'filename_pattern',
        'last_tested_at',
        'last_sync_at',
        'notes',
    ];

    protected $hidden = [
        'password',
        'private_key',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'private_key' => 'encrypted',
        'port' => 'integer',
        'last_tested_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function runs()
    {
        return $this->hasMany(BankTransferRun::class);
    }
}
