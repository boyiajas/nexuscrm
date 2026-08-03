<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$whatsapp = app(\App\Services\MetaWhatsAppService::class);
$templateName = 'settlement_debit_order_reminder';

try {
    $template = $whatsapp->getTemplateDetails($templateName);
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => '1234567890',
        'type' => 'template',
        'template' => [
            'name' => $template['name'],
            'language' => [
                'code' => $template['language'] ?? 'en_US',
            ],
        ],
    ];

    echo "Sending from 897918230060626 with language {$template['language']}\n";
    
    $property = new ReflectionProperty(\App\Services\MetaWhatsAppService::class, 'accessToken');
    $property->setAccessible(true);
    $token = $property->getValue($whatsapp);

    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->post("https://graph.facebook.com/v19.0/897918230060626/messages", $payload);
        
    echo "Response Code: " . $response->status() . "\n";
    print_r($response->json());
    
    echo "Default Phone Number ID: " . $whatsapp->phoneNumberId . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
