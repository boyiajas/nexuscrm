<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'EAAE8IgilCxQBSCYXA3XNCXssr01ZAylKs90LlrZAQY6ZBlA6hTVyRFKGiFWvbKGCt2CJPfmFkDCSE9QX3yuAAQVZCJtckvOZBl5U1fhxd1YmFvpTddsMK5h0zALWoB39I33p3MLDfDhqy3RPKlZBWe7oZCDZAjzDpENOEyjj783HkQDl2vsPH2ZB5I8puFmpg3gZDZD';
$wabaId = '406811385845304';

$response = Illuminate\Support\Facades\Http::withToken($token)
    ->get("https://graph.facebook.com/v19.0/{$wabaId}/message_templates");

print_r($response->json());
