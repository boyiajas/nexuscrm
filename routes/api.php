<?php

// routes/api.php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\WhatsAppTemplateController;
use App\Http\Controllers\Api\TwilioController;
use App\Http\Controllers\Api\WhatsAppFlowController;
use Illuminate\Support\Facades\Route;






Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Twilio webhooks (public)
Route::post('/twilio/webhook/whatsapp', [TwilioController::class, 'webhookWhatsapp']);


Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', ClientController::class);
    Route::post('clients/import', [ClientController::class, 'import']);
    Route::get('clients/export', [ClientController::class, 'export']);

    Route::get('/whatsapp-templates', [WhatsAppTemplateController::class, 'index']);
    Route::post('/whatsapp-templates', [WhatsAppTemplateController::class, 'store']);
    Route::get('/whatsapp-templates/{id}', [WhatsAppTemplateController::class, 'show']);
    Route::put('/whatsapp-templates/{id}', [WhatsAppTemplateController::class, 'update']);
    Route::delete('/whatsapp-templates/{id}', [WhatsAppTemplateController::class, 'destroy']);
    Route::post('/whatsapp-templates/{id}/submit', [WhatsAppTemplateController::class, 'submitForApproval']);

    Route::apiResource('campaigns', CampaignController::class)->only(['index','store','update','destroy','show']);

    Route::get('/dashboard/campaign-activity', [DashboardController::class, 'campaignActivity']);

    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send']);

    Route::get('/campaigns/{campaign}/stats', [CampaignController::class, 'stats']);
    Route::get('/campaigns/{campaign}/clients', [CampaignController::class, 'clients']);
    Route::get('/campaigns/{campaign}/available-clients', [CampaignController::class, 'availableClients']);
    Route::post('/campaigns/{campaign}/attach-clients', [CampaignController::class, 'attachClients']);

    Route::get('/campaigns/{campaign}/whatsapp-messages', [CampaignController::class, 'whatsappMessages']);
    Route::put('/campaigns/{campaign}/whatsapp-messages/{message}', [CampaignController::class, 'updateWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages/{message}/send', [CampaignController::class, 'sendDraftWhatsappMessage']);
    Route::delete('/campaigns/{campaign}/whatsapp-messages/{message}', [CampaignController::class, 'deleteWhatsappMessage']);
    Route::post('/campaigns/{campaign}/whatsapp-messages', [CampaignController::class, 'sendWhatsappMessage']);

    Route::get('/campaigns/{campaign}/emails', [CampaignController::class, 'emails']);
    Route::get('/campaigns/{campaign}/sms-messages', [CampaignController::class, 'smsMessages']);

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

    Route::get('settings', [SettingsController::class, 'show']);
    Route::post('settings', [SettingsController::class, 'update']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::apiResource('departments', DepartmentController::class);
    Route::get('/twilio/whatsapp-senders', [TwilioController::class, 'whatsappSenders']);

    Route::apiResource('users', UserController::class)->except(['show']);

    Route::get('/user', [UserProfileController::class, 'show']);
    Route::put('/user', [UserProfileController::class, 'update']);

    Route::get('mfa/status', [MfaController::class, 'status']);
    Route::post('mfa/setup-email', [MfaController::class, 'setupEmail']);
    Route::post('mfa/verify-email', [MfaController::class, 'verifyEmail']);
    Route::post('mfa/disable', [MfaController::class, 'disable']);
});
