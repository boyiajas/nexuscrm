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

    /**
     * Convert this model back to the shape expected by the campaign frontend.
     */
    public function toApiArray(): array
    {
        return [
            'id'            => $this->sid,
            'meta_id'       => $this->meta_id,
            'sid'           => $this->sid,
            'name'          => $this->friendly_name,
            'language'      => $this->language,
            'category'      => $this->category,
            'status'        => $this->status,
            'body_preview'  => $this->body_preview,
            'variables'     => $this->variables ?? [],
            'whatsapp'      => $this->raw_whatsapp ?? [],
            'media_urls'    => $this->media_urls ?? [],
            'header_format' => $this->header_format,
            'header_text'   => $this->header_text,
            'footer_text'   => $this->footer_text,
            'buttons'       => $this->buttons ?? [],
            'components'    => $this->raw_whatsapp['components'] ?? [],
        ];
    }
}
