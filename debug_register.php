<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'EAAE8IgilCxQBSCYXA3XNCXssr01ZAylKs90LlrZAQY6ZBlA6hTVyRFKGiFWvbKGCt2CJPfmFkDCSE9QX3yuAAQVZCJtckvOZBl5U1fhxd1YmFvpTddsMK5h0zALWoB39I33p3MLDfDhqy3RPKlZBWe7oZCDZAjzDpENOEyjj783HkQDl2vsPH2ZB5I8puFmpg3gZDZD';
$wabaId = '1455412218881488';

$response = Illuminate\Support\Facades\Http::withToken($token)
    ->get("https://graph.facebook.com/v19.0/{$wabaId}/phone_numbers");

$data = $response->json();
print_r($data);

if (isset($data['data'])) {
    foreach ($data['data'] as $number) {
        if (strpos($number['display_phone_number'], '781') !== false || strpos($number['display_phone_number'], '399') !== false) {
            $phoneId = $number['id'];
            echo "Found Phone ID: {$phoneId} for {$number['display_phone_number']}\n";
            
            // Register it
            echo "Registering...\n";
            $regResponse = Illuminate\Support\Facades\Http::withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/register", [
                    'messaging_product' => 'whatsapp',
                    'pin' => '123456'
                ]);
            
            print_r($regResponse->json());
        }
    }
}
