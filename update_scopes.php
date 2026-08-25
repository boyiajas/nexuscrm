<?php
$files = [
    'app/Http/Controllers/Api/ClientController.php',
    'app/Http/Controllers/Api/CampaignController.php',
    'app/Http/Controllers/Api/DashboardController.php',
    'app/Http/Controllers/Api/ImportUploadController.php',
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // 1. Replace cases where both are checked
    $content = str_replace('!$user->canManageSystemSettings() && !$user->canViewAllImportedClients()', '!$user->canViewAllImportedClients()', $content);
    $content = str_replace('!$user->canViewAllImportedClients() && !$user->canManageSystemSettings()', '!$user->canViewAllImportedClients()', $content);
    
    // 2. Replace cases where only canManageSystemSettings is checked, but it's for department/client scoping
    // We replace it with canViewAllImportedClients()
    $content = str_replace('!$user->canManageSystemSettings() && !empty($userDepartmentIds)', '!$user->canViewAllImportedClients() && !empty($userDepartmentIds)', $content);
    $content = str_replace('!$user->canManageSystemSettings() && !empty($userDeptIds)', '!$user->canViewAllImportedClients() && !empty($userDeptIds)', $content);
    $content = str_replace('!$user->canManageSystemSettings() && !empty($allowedDepartmentIds)', '!$user->canViewAllImportedClients() && !empty($allowedDepartmentIds)', $content);
    $content = str_replace('!$user->canManageSystemSettings()', '!$user->canViewAllImportedClients()', $content);
    $content = str_replace('$user->canManageSystemSettings()', '$user->canViewAllImportedClients()', $content);
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
