<?php

// routes/api.php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DepartmentStatsController;
use App\Http\Controllers\Api\ExportRequestController;
use App\Http\Controllers\Api\ImportUploadController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SecurityIncidentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\WhatsAppTemplateController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\WhatsAppFlowController;
use Illuminate\Support\Facades\Route;






Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/mfa/verify', [AuthController::class, 'verifyLoginMfa']);
Route::post('/login/password/reset', [AuthController::class, 'resetLoginPassword']);
Route::post('/forgot-password/request', [AuthController::class, 'requestForgotPassword']);
Route::post('/forgot-password/reset', [AuthController::class, 'completeForgotPasswordReset']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/settings/branding', [SettingsController::class, 'branding']);

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'webhook']);
Route::post('/twilio/webhook/whatsapp', [WhatsAppWebhookController::class, 'webhook']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('clients/import', [ClientController::class, 'import']);
    Route::delete('clients/delete-batch', [ClientController::class, 'destroyBatch']);
    Route::get('import-uploads', [ImportUploadController::class, 'index']);
    Route::get('clients/export', [ClientController::class, 'export']);
    Route::apiResource('clients', ClientController::class);
    Route::get('banks', [BankController::class, 'index']);
    Route::post('banks', [BankController::class, 'store']);
    Route::put('banks/{bank}', [BankController::class, 'update']);
    Route::delete('banks/{bank}', [BankController::class, 'destroy']);

    Route::get('/whatsapp-templates', [WhatsAppTemplateController::class, 'index']);
    Route::post('/whatsapp-templates', [WhatsAppTemplateController::class, 'store']);
    Route::get('/whatsapp-templates/{id}', [WhatsAppTemplateController::class, 'show']);
    Route::put('/whatsapp-templates/{id}', [WhatsAppTemplateController::class, 'update']);
    Route::delete('/whatsapp-templates/{id}', [WhatsAppTemplateController::class, 'destroy']);
    Route::post('/whatsapp-templates/{id}/submit', [WhatsAppTemplateController::class, 'submitForApproval']);

    Route::apiResource('campaigns', CampaignController::class)->only(['index','store','update','destroy','show']);

    Route::get('/dashboard/campaign-activity', [DashboardController::class, 'campaignActivity']);
    Route::get('/dashboard/whatsapp-replies', [DashboardController::class, 'whatsappReplies']);

    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send']);

    Route::get('/campaigns/{campaign}/stats', [CampaignController::class, 'stats']);
    Route::get('/campaigns/{campaign}/clients', [CampaignController::class, 'clients']);
    Route::get('/campaigns/{campaign}/clients/export', [CampaignController::class, 'exportClients']);
    Route::delete('/campaigns/{campaign}/clients/{client}', [CampaignController::class, 'detachClient']);
    Route::post('/campaigns/{campaign}/detach-clients', [CampaignController::class, 'detachClients']);
    Route::get('/campaigns/{campaign}/available-clients', [CampaignController::class, 'availableClients']);
    Route::post('/campaigns/{campaign}/attach-clients', [CampaignController::class, 'attachClients']);

    Route::get('/campaigns/{campaign}/whatsapp-messages', [CampaignController::class, 'whatsappMessages']);
    Route::get('/campaigns/{campaign}/whatsapp-messages/export', [CampaignController::class, 'exportWhatsappMessages']);
    Route::put('/campaigns/{campaign}/whatsapp-messages/{message}', [CampaignController::class, 'updateWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages/{message}/send', [CampaignController::class, 'sendDraftWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages/{message}/pause', [CampaignController::class, 'pauseWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages/{message}/resume', [CampaignController::class, 'resumeWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages/{message}/retry-failed', [CampaignController::class, 'retryFailedWhatsappRecipients']);
    Route::delete('/campaigns/{campaign}/whatsapp-messages/{message}', [CampaignController::class, 'deleteWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages', [CampaignController::class, 'sendWhatsappMessage']);

    Route::get('/campaigns/{campaign}/emails', [CampaignController::class, 'emails']);
    Route::get('/campaigns/{campaign}/emails/export', [CampaignController::class, 'exportEmails']);
    Route::get('/campaigns/{campaign}/sms-messages', [CampaignController::class, 'smsMessages']);
    Route::get('/campaigns/{campaign}/sms-messages/export', [CampaignController::class, 'exportSmsMessages']);

    Route::get('/campaigns/{campaign}/whatsapp-messages/{messageId}/recipients', [CampaignController::class, 'whatsappRecipients']);
    Route::get('/campaigns/{campaign}/emails/{emailId}/recipients', [CampaignController::class, 'emailRecipients']);
    Route::get('/campaigns/{campaign}/sms-messages/{smsId}/recipients', [CampaignController::class, 'smsRecipients']);

    Route::apiResource('whatsapp-flows', WhatsAppFlowController::class);

    Route::get('chat/sessions', [ChatController::class, 'index']);
    Route::get('chat/sessions/{session}', [ChatController::class, 'show']);
    Route::post('chat/sessions/{session}/messages', [ChatController::class, 'storeMessage']);
    Route::post('chat/session-for-client', [ChatController::class, 'sessionForClient']);

    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export']);
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show']);
    Route::get('security-incidents', [SecurityIncidentController::class, 'index']);
    Route::post('security-incidents', [SecurityIncidentController::class, 'store']);
    Route::get('security-incidents/{securityIncident}', [SecurityIncidentController::class, 'show']);
    Route::put('security-incidents/{securityIncident}', [SecurityIncidentController::class, 'update']);
    Route::post('security-incidents/{securityIncident}/events', [SecurityIncidentController::class, 'addEvent']);
    Route::get('compliance/overview', [ComplianceController::class, 'overview']);
    Route::post('compliance/data-subject-requests', [ComplianceController::class, 'storeDataSubjectRequest']);
    Route::put('compliance/data-subject-requests/{dataSubjectRequest}', [ComplianceController::class, 'updateDataSubjectRequest']);
    Route::post('compliance/complaints', [ComplianceController::class, 'storeComplaint']);
    Route::put('compliance/complaints/{complaintCase}', [ComplianceController::class, 'updateComplaint']);
    Route::post('compliance/information-officers', [ComplianceController::class, 'storeOfficer']);
    Route::put('compliance/information-officers/{informationOfficer}', [ComplianceController::class, 'updateOfficer']);
    Route::post('compliance/retention-policies', [ComplianceController::class, 'storeRetentionPolicy']);
    Route::put('compliance/retention-policies/{retentionPolicy}', [ComplianceController::class, 'updateRetentionPolicy']);
    Route::post('compliance/retention-actions', [ComplianceController::class, 'storeRetentionAction']);
    Route::post('compliance/retention-actions/{retentionAction}/approve', [ComplianceController::class, 'approveRetentionAction']);
    Route::post('compliance/retention-actions/{retentionAction}/complete', [ComplianceController::class, 'completeRetentionAction']);
    Route::post('compliance/bank-transfer-profiles', [ComplianceController::class, 'storeTransferProfile']);
    Route::put('compliance/bank-transfer-profiles/{bankTransferProfile}', [ComplianceController::class, 'updateTransferProfile']);
    Route::post('compliance/bank-transfer-profiles/{bankTransferProfile}/test', [ComplianceController::class, 'testTransferProfile']);
    Route::post('compliance/bank-transfer-profiles/{bankTransferProfile}/sync', [ComplianceController::class, 'syncTransferProfile']);
    Route::get('export-requests', [ExportRequestController::class, 'index']);
    Route::post('export-requests', [ExportRequestController::class, 'store']);
    Route::post('export-requests/{exportRequest}/approve', [ExportRequestController::class, 'approve']);
    Route::post('export-requests/{exportRequest}/reject', [ExportRequestController::class, 'reject']);

    Route::get('settings', [SettingsController::class, 'show']);
    Route::post('settings', [SettingsController::class, 'update']);
    Route::post('settings/meta/validate', [SettingsController::class, 'validateMetaPermissions']);

    Route::get('settings/meta/phone-numbers', [SettingsController::class, 'fetchMetaPhoneNumbers']);
    Route::post('settings/meta/phone-numbers', [SettingsController::class, 'submitMetaPhoneNumber']);
    Route::post('settings/meta/phone-numbers/request-verification', [SettingsController::class, 'requestMetaPhoneVerification']);
    Route::post('settings/meta/phone-numbers/verify', [SettingsController::class, 'verifyMetaPhoneNumber']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments/{department}/whatsapp-stats', [DepartmentStatsController::class, 'whatsappStats']);
    Route::get('/whatsapp/senders', [WhatsAppWebhookController::class, 'whatsappSenders']);
    Route::get('/twilio/whatsapp-senders', [WhatsAppWebhookController::class, 'whatsappSenders']);

    Route::apiResource('users', UserController::class)->except(['show']);
    Route::patch('roles/{role}/watermark', [RoleController::class, 'toggleWatermark']);
    Route::apiResource('roles', RoleController::class)->except(['show']);
    Route::get('users-assignees', [UserController::class, 'assignees']);

    Route::get('/user', [UserProfileController::class, 'show']);
    Route::put('/user', [UserProfileController::class, 'update']);
    Route::get('/user/department-options', [UserProfileController::class, 'departmentOptions']);
    Route::get('/user/sessions', [UserProfileController::class, 'sessions']);

    Route::get('mfa/status', [MfaController::class, 'status']);
    Route::post('mfa/setup-email', [MfaController::class, 'setupEmail']);
    Route::post('mfa/verify-email', [MfaController::class, 'verifyEmail']);
    Route::post('mfa/disable', [MfaController::class, 'disable']);
});
