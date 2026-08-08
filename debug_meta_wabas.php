<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'EAAE8IgilCxQBSCYXA3XNCXssr01ZAylKs90LlrZAQY6ZBlA6hTVyRFKGiFWvbKGCt2CJPfmFkDCSE9QX3yuAAQVZCJtckvOZBl5U1fhxd1YmFvpTddsMK5h0zALWoB39I33p3MLDfDhqy3RPKlZBWe7oZCDZAjzDpENOEyjj783HkQDl2vsPH2ZB5I8puFmpg3gZDZD';

$businessId = '1323373205187636';

$response1 = Illuminate\Support\Facades\Http::withToken($token)
    ->get("https://graph.facebook.com/v19.0/{$businessId}/owned_whatsapp_business_accounts");
print_r($response1->json());

$response2 = Illuminate\Support\Facades\Http::withToken($token)
    ->get("https://graph.facebook.com/v19.0/{$businessId}/client_whatsapp_business_accounts");
print_r($response2->json());
