<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$whatsapp = app(\App\Services\MetaWhatsAppService::class);
$templateName = 'settlement_debit_order_reminder';

try {
    $template = $whatsapp->getTemplateDetails($templateName);
    echo "Found template:\n";
    print_r($template);
    
    echo "\nFetching phone numbers for WABA...\n";
    $numbers = $whatsapp->getPhoneNumbers();
    print_r($numbers);
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
