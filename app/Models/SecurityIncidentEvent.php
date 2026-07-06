<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityIncidentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_incident_id',
        'user_id',
        'event_type',
        'note',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function incident()
    {
        return $this->belongsTo(SecurityIncident::class, 'security_incident_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
