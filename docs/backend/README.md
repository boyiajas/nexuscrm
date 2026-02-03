# Backend Overview

- Framework: Laravel (API-first). Entry route file `routes/api.php`; single SPA catch-all in `routes/web.php`.
- Auth: Laravel Sanctum token auth. Guards applied to most API routes via `Route::middleware('auth:sanctum')`. See [authorization](authorization.md) for role/scoping rules.
- Controllers live in `app/Http/Controllers/Api/`. Each aligns with a feature doc (clients, campaigns, chat, audit logs, departments, users, settings, WhatsApp flows/templates, MFA).
- Data layer: Eloquent models in `app/Models/` with casts and relationships for campaigns, messages, recipients, clients, departments, users, audit logs, chat, settings, and WhatsApp flows.
- Services: `App\Services\TwilioWhatsAppService` (Twilio Content + WhatsApp sending) and `App\Services\AuditLogger` (audit trail helper); `App\Concerns\HasAuditLogging` trait wraps AuditLogger.
- Validation: Controllers validate request payloads inline with Laravel validation rules; most write paths re-check role/department permissions.
