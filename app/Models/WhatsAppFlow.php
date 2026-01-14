<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppFlow extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_flows';

    protected $fillable = [
        'name',
        'template_sid',
        'template_name',
        'template_language',
        'status',
        'description',
        'flow_definition',
        'created_by',
    ];

    protected $casts = [
        'flow_definition' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
