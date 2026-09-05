<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientImportBatch extends Model
{
    protected $fillable = ['client_id', 'import_batch_number'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
