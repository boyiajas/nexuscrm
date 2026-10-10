<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplateCache extends Model
{
    protected $table = 'whatsapp_templates_cache';

    protected $fillable = [
        'meta_id',
        'sid',
        'friendly_name',
        'language',
        'category',
        'status',
        'body_preview',
        'header_format',
        'header_text',
        'footer_text',
        'variables',
        'media_urls',
        'buttons',
        'raw_whatsapp',
        'synced_at',
    ];

    protected $casts = [
        'variables'    => 'array',
        'media_urls'   => 'array',
        'buttons'      => 'array',
        'raw_whatsapp' => 'array',
        'synced_at'    => 'datetime',
    ];

    public function banks()
    {
        return $this->belongsToMany(
            Bank::class,
            'bank_whatsapp_template_cache',
            'whatsapp_template_cache_id',
            'bank_id'
        )->withTimestamps();
    }

    /**
     * Convert this model back to the shape expected by the campaign frontend.
     */
    public function toApiArray(): array
    {
        $raw = $this->raw_whatsapp ?? [];
        $rejectedReason = $raw['rejected_reason'] ?? null;
        if (is_string($rejectedReason) && strtoupper($rejectedReason) === 'NONE') {
            $rejectedReason = null;
        }

        $banksData = [];
        $bankIds = [];
        if ($this->relationLoaded('banks')) {
            $banksData = $this->banks->map(fn ($b) => [
                'id'   => $b->id,
                'name' => $b->name,
                'code' => $b->code,
            ])->values()->all();
            $bankIds = $this->banks->pluck('id')->values()->all();
        } else {
            $banksData = $this->banks()->select(['banks.id', 'banks.name', 'banks.code'])->get()->map(fn ($b) => [
                'id'   => $b->id,
                'name' => $b->name,
                'code' => $b->code,
            ])->values()->all();
            $bankIds = array_column($banksData, 'id');
        }

        return [
            'id'              => $this->sid,
            'meta_id'         => $this->meta_id,
            'sid'             => $this->sid,
            'name'            => $this->friendly_name,
            'language'        => $this->language,
            'category'        => $this->category,
            'status'          => $this->status,
            'body_preview'    => $this->body_preview,
            'variables'       => $this->variables ?? [],
            'whatsapp'        => $raw,
            'rejected_reason' => $rejectedReason,
            'quality_score'   => $raw['quality_score'] ?? null,
            'media_urls'      => $this->media_urls ?? [],
            'header_format'   => $this->header_format,
            'header_text'     => $this->header_text,
            'footer_text'     => $this->footer_text,
            'buttons'         => $this->buttons ?? [],
            'components'      => $raw['components'] ?? [],
            'synced_at'       => optional($this->synced_at)->toDateTimeString(),
            'banks'           => $banksData,
            'bank_ids'        => $bankIds,
        ];
    }
}
