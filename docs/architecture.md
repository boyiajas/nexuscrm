# Architecture

## Stack
- Backend: Laravel + Sanctum auth, MySQL; REST API defined in `routes/api.php`.
- Frontend: Vue 3 + Vite + axios; SPA served from `resources/views/app.blade.php` and mounted via `resources/js/app.js`.
- Messaging: Twilio Content API for WhatsApp templates; optional Messaging Service SID or WhatsApp number; status callbacks configurable.
- Build: `npm run dev|build` for the SPA, `php artisan serve` or standard PHP-FPM for the API.

## High-level Flow
1) User authenticates (AuthController -> Sanctum token). Router guard in `resources/js/router.js` gates protected routes.
2) SPA issues JSON API calls to Laravel controllers; Sanctum token travels via axios interceptor (`resources/js/axios.js`).
3) Data persisted in MySQL via Eloquent models; many-to-many joins for clients↔departments and campaigns↔departments/clients.
4) Messaging features call Twilio through `App\Services\TwilioWhatsAppService`; send results are stored in campaign message/recipient tables.
5) Actions log to `audit_logs` through `App\Services\AuditLogger` or `App\Concerns\HasAuditLogging`.

## Deployment Notes
- Environment: `.env` plus `config/services.php` keys for Twilio; `SystemSetting` table can override runtime Twilio values.
- Single-page fallback: `routes/web.php` sends all paths to `resources/views/app.blade.php`.
- File uploads: client CSV import posts multipart form-data to `/api/clients/import`.
- Exports: CSV streaming via Laravel `StreamedResponse` (clients and audit logs).

## Data Ownership & Security
- Department-scoped visibility is enforced in controllers (clients, campaigns, dashboard) for non-super-admin roles.
- Auth roles: `SUPER_ADMIN`, `MANAGER`, `STAFF` (see `App\Models\User` helpers).
- MFA: email OTP endpoints in `MfaController`; state stored on the user record (see `Settings` feature doc).
