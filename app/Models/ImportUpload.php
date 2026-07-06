<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'user_id',
        'dataset',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'file_hash',
        'import_status',
        'scan_enabled',
        'scan_status',
        'scan_engine',
        'scan_signature',
        'scan_message',
        'import_summary',
        'error_message',
        'scanned_at',
        'imported_at',
    ];

    protected $casts = [
        'scan_enabled' => 'boolean',
        'size_bytes' => 'integer',
        'import_summary' => 'array',
        'scanned_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
