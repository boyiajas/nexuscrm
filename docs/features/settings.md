# Settings & System Config

## Purpose
Allow admins/users to manage personal profile, MFA, preferences, and Twilio/system messaging configuration.

## Backend
- **SettingsController** (`app/Http/Controllers/Api/SettingsController.php`):
  - `GET /api/settings`: returns `SystemSetting` record (Twilio keys/SIDs, ZoomConnect, backup options).
  - `POST /api/settings`: update settings (admin-only via `authorizeAdmin` allowing SUPER_ADMIN/MANAGER).
- **SystemSetting model**: stores Twilio SID/token, Messaging Service SID, ContentSid, WhatsApp from number, status callback, ZoomConnect config, backup settings, email provider.
- **MFA**: handled by `MfaController` (enable/verify/disable email OTP).
- **Twilio senders**: `/api/twilio/whatsapp-senders` from `TwilioController` lists configured WhatsApp number + Messaging Service SID for dropdowns (departments/campaigns).

## Frontend
- View: `resources/js/views/Settings.vue`.
- Tabs:
  - User Account: profile info + working info, inactivity timeout, provider/time-clock flags. Saves via `/api/user`.
  - Security & MFA: enable email OTP, verify code, disable MFA.
  - Preferences: dark mode + notifications toggles (client-only state).
  - Twilio Config: form for SID/token/from/msg sid/template sid/status callback; persists via `/api/settings`.
  - WhatsApp Templates tab links to template preview helper.
- Uses stored `nexus_user` for default form values; reloads `/api/user` to refresh.

## Data & Permissions
- Settings update restricted to SUPER_ADMIN/MANAGER; regular users can update their own profile via `/api/user`.
- Twilio credentials loaded by `TwilioWhatsAppService` at runtime; updates take effect on next service construction (request lifecycle).
