<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;
$client = Client::first();
if (!$client) {
    echo "No clients.\n";
    exit;
}
$batch = 'TEST-BATCH-' . time();
$client->importBatches()->firstOrCreate(['import_batch_number' => $batch]);
$count = Client::whereHas('importBatches', function($q) use ($batch) { 
    $q->where('import_batch_number', $batch); 
})->count();
echo "Count for batch $batch: $count\n";
