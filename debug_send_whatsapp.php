<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'EAAE8IgilCxQBSCYXA3XNCXssr01ZAylKs90LlrZAQY6ZBlA6hTVyRFKGiFWvbKGCt2CJPfmFkDCSE9QX3yuAAQVZCJtckvOZBl5U1fhxd1YmFvpTddsMK5h0zALWoB39I33p3MLDfDhqy3RPKlZBWe7oZCDZAjzDpENOEyjj783HkQDl2vsPH2ZB5I8puFmpg3gZDZD';
$phoneId = '897918230060626';

$payload = [
    'messaging_product' => 'whatsapp',
    'to' => '17813994783', // Send to the other WABA number
    'type' => 'template',
    'template' => [
        'name' => 'hello_world',
        'language' => ['code' => 'en_US']
    ]
];

$response = Illuminate\Support\Facades\Http::withToken($token)
    ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", $payload);

print_r($response->json());
