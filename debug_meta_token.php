<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settings = \App\Models\SystemSetting::first();
$token = 'EAAE8IgilCxQBSCYXA3XNCXssr01ZAylKs90LlrZAQY6ZBlA6hTVyRFKGiFWvbKGCt2CJPfmFkDCSE9QX3yuAAQVZCJtckvOZBl5U1fhxd1YmFvpTddsMK5h0zALWoB39I33p3MLDfDhqy3RPKlZBWe7oZCDZAjzDpENOEyjj783HkQDl2vsPH2ZB5I8puFmpg3gZDZD';
$appId = $settings->meta_app_id ?: config('services.meta_whatsapp.app_id');
$appSecret = $settings->meta_app_secret ?: config('services.meta_whatsapp.app_secret');

echo "Token: " . substr($token, 0, 10) . "...\n";
echo "App ID: " . $appId . "\n";

$response = Illuminate\Support\Facades\Http::get("https://graph.facebook.com/v19.0/debug_token", [
    'input_token' => $token,
    'access_token' => "{$appId}|{$appSecret}"
]);

print_r($response->json());
