<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Log;

$inputPath = '/var/www/Laravel_Projects/nexuscrm/docs/TENACITY SPECIALITY DAILY EXTRACT-11.09.2026.csv';
$outputPath = '/var/www/Laravel_Projects/nexuscrm/docs/UNIQUE_TENACITY_SPECIALITY_11.09.2026.csv';

$file = fopen($inputPath, 'r');
$header = fgetcsv($file);

$outFile = fopen($outputPath, 'w');
fputcsv($outFile, $header);

$seenEmails = [];
$seenPhones = [];
$seenAccountNumbers = [];
$duplicates = [];
$uniqueCount = 0;

$rowNumber = 1;

while (($row = fgetcsv($file)) !== false) {
    $rowNumber++;
    // if row is empty or doesn't match header size, skip for matching purposes
    if (count($row) !== count($header)) {
        continue;
    }
    $data = array_combine($header, $row);

    // Replicate logic from ImportHelpers/ImportClientsJob
    $emailValue = null;
    foreach (['email_personal', 'patient_email_personal', 'email', 'email_work', 'patient_email_work'] as $key) {
        if (!empty($data[$key])) {
            $emailValue = $data[$key];
            break;
        }
    }
    
    $cellPhone = null;
    foreach (['cell', 'cell_phone'] as $key) {
        if (!empty($data[$key])) {
            $cellPhone = $data[$key];
            break;
        }
    }
    
    $homePhone = null;
    foreach (['home', 'home_phone'] as $key) {
        if (!empty($data[$key])) {
            $homePhone = $data[$key];
            break;
        }
    }
    
    $workPhone = null;
    foreach (['work', 'work_phone'] as $key) {
        if (!empty($data[$key])) {
            $workPhone = $data[$key];
            break;
        }
    }

    $primaryPhone = $cellPhone ?: (!empty($data['phone']) ? $data['phone'] : ($homePhone ?: $workPhone));

    $normalizedEmail = $emailValue ? mb_strtolower(trim($emailValue)) : null;
    $normalizedPhone = $primaryPhone ? preg_replace('/\D+/', '', (string) $primaryPhone) : null;
    $accountNumber = isset($data['Account Number']) ? trim($data['Account Number']) : null;
    
    // In our specific CSV, 'Account Number' is the exact header string, but let's check normalized headers
    if (!$accountNumber && isset($data['account_number'])) {
        $accountNumber = trim($data['account_number']);
    }
    
    $isDuplicate = false;
    $duplicateReason = "";
    
    if ($accountNumber && isset($seenAccountNumbers[$accountNumber])) {
        $isDuplicate = true;
        $duplicateReason = "Duplicate Account Number: " . $accountNumber;
    } elseif ($normalizedEmail && isset($seenEmails[$normalizedEmail])) {
        $isDuplicate = true;
        $duplicateReason = "Duplicate Email: " . $normalizedEmail;
    } elseif ($normalizedPhone && isset($seenPhones[$normalizedPhone])) {
        $isDuplicate = true;
        $duplicateReason = "Duplicate Phone: " . $normalizedPhone;
    }

    if ($isDuplicate) {
        $name = isset($data['Name']) ? $data['Name'] : (isset($data['name']) ? $data['name'] : '');
        $surname = isset($data['Surname']) ? $data['Surname'] : (isset($data['surname']) ? $data['surname'] : '');
        $duplicates[] = [
            'row' => $rowNumber,
            'name' => trim("$name $surname"),
            'reason' => $duplicateReason
        ];
    } else {
        if ($normalizedEmail) $seenEmails[$normalizedEmail] = true;
        if ($normalizedPhone) $seenPhones[$normalizedPhone] = true;
        if ($accountNumber) $seenAccountNumbers[$accountNumber] = true;
        
        fputcsv($outFile, $row);
        $uniqueCount++;
    }
}

fclose($file);
fclose($outFile);

echo json_encode([
    'uniqueCount' => $uniqueCount,
    'duplicatesCount' => count($duplicates),
    'duplicates' => $duplicates
], JSON_PRETTY_PRINT);

