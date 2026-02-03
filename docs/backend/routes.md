# API Routes

Key JSON endpoints from `routes/api.php` (all under `/api`, Sanctum-protected unless noted).

- **AuthController**: `POST /login`, `POST /register`, `POST /logout` (auth), `GET /me` (token check).
- **UserProfileController**: `GET /user`, `PUT /user` (current user profile update).
- **MfaController**: `GET /mfa/status`, `POST /mfa/setup-email`, `POST /mfa/verify-email`, `POST /mfa/disable`.
- **DashboardController**: `GET /dashboard` (summary + charts), `GET /dashboard/campaign-activity` (30-day activity).
- **Clients (ClientController)**: `GET /clients`, `POST /clients`, `GET /clients/{id}`, `PUT /clients/{id}`, `DELETE /clients/{id}`, `POST /clients/import` (CSV), `GET /clients/export` (CSV).
- **Campaigns (CampaignController)**: `GET /campaigns`, `POST /campaigns`, `PUT /campaigns/{id}`, `DELETE /campaigns/{id}`, `GET /campaigns/{id}`. Attach/inspect clients: `GET /campaigns/{campaign}/clients`, `GET /campaigns/{campaign}/available-clients`, `POST /campaigns/{campaign}/attach-clients`. Sending & reporting: `POST /campaigns/{campaign}/send`, `GET /campaigns/{campaign}/stats`. Channel-specific: WhatsApp batches (`GET/PUT/POST/DELETE /campaigns/{campaign}/whatsapp-messages[...]`, `POST /.../{message}/send`, recipient dashboards), Email (`GET /campaigns/{campaign}/emails`, `GET /campaigns/{campaign}/emails/{id}/recipients`), SMS (`GET /campaigns/{campaign}/sms-messages`, `GET /campaigns/{campaign}/sms-messages/{id}/recipients`). Template helpers: `GET /whatsapp-templates` and `GET /whatsapp-templates/{id}` via CampaignController for dropdowns/previews.
- **WhatsApp Flows (WhatsAppFlowController)**: `GET /whatsapp-flows`, `POST /whatsapp-flows`, `GET /whatsapp-flows/{id}`, `PUT /whatsapp-flows/{id}`, `DELETE /whatsapp-flows/{id}`.
- **WhatsApp Templates (WhatsAppTemplateController)**: `GET /whatsapp-templates`, `POST /whatsapp-templates`, `GET /whatsapp-templates/{id}`, `PUT /whatsapp-templates/{id}`, `DELETE /whatsapp-templates/{id}`, `POST /whatsapp-templates/{id}/submit` (approval).
- **Chat (ChatController)**: `GET /chat/sessions`, `GET /chat/sessions/{session}`, `POST /chat/sessions/{session}/messages`.
- **AuditLogController**: `GET /audit-logs`, `GET /audit-logs/{id}`, `GET /audit-logs/export`.
- **SettingsController**: `GET /settings`, `POST /settings` (Twilio/system settings).
- **Departments (DepartmentController)**: REST `apiResource` for departments.
- **Users (UserController)**: REST `apiResource` except `show` (list, create, update, delete).
- **TwilioController**: `GET /twilio/whatsapp-senders` (available sender numbers/Messaging Service SID).
