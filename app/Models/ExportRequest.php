<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DOWNLOADED = 'downloaded';

    public const DATASET_CLIENTS = 'clients';
    public const DATASET_AUDIT_LOGS = 'audit_logs';
    public const DATASET_CAMPAIGN_CLIENTS = 'campaign_clients';
    public const DATASET_CAMPAIGN_WHATSAPP_MESSAGES = 'campaign_whatsapp_messages';
    public const DATASET_CAMPAIGN_EMAILS = 'campaign_emails';
    public const DATASET_CAMPAIGN_SMS_MESSAGES = 'campaign_sms_messages';

    public const ALL_DATASETS = [
        self::DATASET_CLIENTS,
        self::DATASET_AUDIT_LOGS,
        self::DATASET_CAMPAIGN_CLIENTS,
        self::DATASET_CAMPAIGN_WHATSAPP_MESSAGES,
        self::DATASET_CAMPAIGN_EMAILS,
        self::DATASET_CAMPAIGN_SMS_MESSAGES,
    ];

    protected $fillable = [
        'requested_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'downloaded_by_user_id',
        'bank_id',
        'dataset',
        'target_type',
        'target_id',
        'status',
        'filters',
        'justification',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'downloaded_at',
        'download_filename',
    ];

    protected $casts = [
        'filters' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function downloadedBy()
    {
        return $this->belongsTo(User::class, 'downloaded_by_user_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
};
