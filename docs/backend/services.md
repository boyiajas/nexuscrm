# Services & Helpers

- **TwilioWhatsAppService** (`app/Services/TwilioWhatsAppService.php`)
  - Loads credentials from `SystemSetting` or `config('services.twilio.*')` (SID, token, WhatsApp from number, Messaging Service SID, default ContentSid, status callback).
  - Capabilities: list WhatsApp senders; send Content templates via `sendTemplateFromSubjectMessage` (normalizes ZA numbers, optional status callback); CRUD WhatsApp templates (create/update/delete/submit for approval); fetch template lists/details/approval status; normalize phone helper `normalizeZA`.
  - Used by `CampaignController` for WhatsApp batches and `WhatsAppTemplateController` for template management.

- **AuditLogger** (`app/Services/AuditLogger.php`)
  - Static `log($action, $module = 'General', $meta = null)` writes to `audit_logs` with current user and request IP.
  - Trait `App\Concerns\HasAuditLogging` wraps it for controllers (e.g., `ClientController`).

- **HasAuditLogging trait** (`app/Concerns/HasAuditLogging.php`)
  - Helper to call `audit(...)` inside controllers for consistent meta payloads.
