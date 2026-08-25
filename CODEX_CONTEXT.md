# Codex System Context

Read this file first in any new Codex session before making changes. It is the project baseline for the app and should be updated whenever architecture, security, data model, or major workflows change.

## Project Summary

- App: `SRS DailyCRM` / `NexusCRM`
- Purpose: Strauss Recovery Solutions WhatsApp CRM for debt-collection operations
- Domain: call centre agents manage and follow up with debtors on behalf of banks across South Africa
- Stack:
  - Laravel 12 style bootstrap (`bootstrap/app.php`)
  - Sanctum token auth + SPA cookies
  - Vue 3 + Vite frontend in `resources/js`
  - MySQL/MariaDB-style relational schema

## Current Messaging Architecture

- WhatsApp provider: Meta direct WhatsApp Business Platform
- Twilio has been removed as the active transport layer
- Main service:
  - `app/Services/MetaWhatsAppService.php`
- Main webhook controller:
  - `app/Http/Controllers/Api/WhatsAppWebhookController.php`
- Webhook routes:
  - `GET /api/whatsapp/webhook`
  - `POST /api/whatsapp/webhook`
- Legacy route still points to Meta webhook controller for compatibility:
  - `POST /api/twilio/webhook/whatsapp`

## Main Functional Areas

- Clients
  - debtor records
  - department assignment
  - bank-linked fields like ID number and account number
- Campaigns
  - WhatsApp / Email / SMS campaign containers
  - per-client per-channel status tracking
- WhatsApp batches
  - approved Meta templates
  - optional live chat and response tracking
- Live Chat
  - WhatsApp reply conversations
- WhatsApp Flows
  - template-based guided follow-up sequences
- WhatsApp Storylines
  - rule-based WhatsApp template auto-responses tied to campaign messages
  - triggers on specific client replies (e.g., quick replies, opt-outs)
- Audit Log
  - user and system activity reporting
- Settings
  - branding
  - Meta WhatsApp configuration
  - WhatsApp template admin/read views
- Compliance Console
  - data subject rights workflow
  - complaint escalation workflow
  - information officer / deputy workflow
  - retention policy / action workflow
  - secure bank transfer profile governance

## Current Role & Dynamic Permission Model

Defined in `app/Models/User.php` and managed via `Roles.vue`.

- System Roles:
  - `SUPER_ADMIN`
  - `ADMIN`
  - `MANAGER`
  - `CALL_CENTRE_MANAGER`
  - `TEAM_LEADER`
  - `AGENT`
  - `STAFF` (legacy role code)
  - `AUDITOR`
  - `COMPLIANCE_OFFICER`
  - `READ_ONLY_REVIEWER`

- Dynamic Database-Driven RBAC Architecture:
  - Permissions are stored in `permissions` table and linked to roles via `permission_role` table.
  - Hardcoded role bypass arrays for non-admin roles have been completely removed from capability checks.
  - `User::hasPermission($permCode)` checks database permission assignments. `SUPER_ADMIN` maintains implicit administrative root bypass.
  - All capability helpers strictly invoke dynamic checks:
    - `canCreateClients()` -> `hasPermission('create_clients')`
    - `canEditClients()` -> `hasPermission('edit_clients')`
    - `canDeleteClients()` -> `hasPermission('delete_clients')`
    - `canImportClients()` -> `hasPermission('import_clients')`
    - `canCreateCampaigns()` -> `hasPermission('create_campaigns')`
    - `canEditCampaigns()` -> `hasPermission('edit_campaigns')`
    - `canDeleteCampaigns()` -> `hasPermission('delete_campaigns')`
    - `canViewClients()` -> `hasPermission('view_clients')`
    - `canViewCampaigns()` -> `hasPermission('view_campaigns')`
  - Frontend SPA uses `hasPermission(code)` instance methods (not computed getters) and `requiredPermission` router meta tags to enforce UI and navigation authorization.

Important helpers:

- `canManageSystemSettings()`
- `canManageUsersAndDepartments()`
- `canManageOperationalData()`
- `canViewOperationalData()`
- `canReviewAuditData()`
- `isReadOnlyRole()`
- `requiresLoginMfa()`

## Current Security Controls Implemented

### Authentication and Access

- Sanctum token auth
- role-based access model
- read-only reviewer/audit roles are enforced server-side for audit/chat/flow/admin route access
- account lockout after repeated failed logins
- privileged-login MFA challenge by email OTP
- password-reset-required challenge for admin-created or expired passwords
- inactivity timeout enforcement via API middleware
- admin IP allowlisting for privileged users
- explicit API payload size limits for general API writes, client imports, and WhatsApp webhooks
- stronger password policy for user registration and admin-created users
- user login session tracking with IP, user agent, authentication method, and logout reason
- inactive / deactivated users are blocked server-side and active tokens are revoked
- active API sessions are also terminated when password age exceeds the configured max age

Key files:

- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/UserProfileController.php`
- `app/Http/Middleware/EnforceAdminIpAllowlist.php`
- `app/Http/Middleware/EnforceApiPayloadLimits.php`
- `app/Http/Middleware/EnforceApiSessionTimeout.php`
- `app/Services/UserSessionTracker.php`
- `resources/js/views/auth/Login.vue`
- `resources/js/router.js`

### WhatsApp Security

- Meta webhook verify-token challenge
- Meta webhook POST signature validation using app secret
- replay protection for inbound message/status webhook events using cache
- STOP / opt-out messages suppress future WhatsApp sends for a client
- lawful-basis tracking is stored on client records and WhatsApp campaign sends are blocked when a client is opted out or has no lawful basis
- inbound phone-number matching is now ambiguity-aware:
  - message-id matches are preferred
  - stored Meta sender phone-number ID is used before broader phone-only fallback
  - phone matches are only auto-linked when the candidate set is unambiguous
  - ambiguous cross-bank/portfolio phone matches are logged and not auto-attached to the wrong client

Key files:

- `app/Http/Controllers/Api/WhatsAppWebhookController.php`
- `app/Services/MetaWhatsAppService.php`
- `app/Http/Controllers/Api/CampaignController.php`

### Data Protection

- client ID/account fields masked for read-only roles on client detail API
- raw sensitive client fields are stripped from normal client list responses
- masked ID/account values are shown in list views instead of raw values
- system secrets are encrypted at rest in `system_settings`
- WhatsApp compliance state is visible on client records:
  - `Eligible`
  - `Suppressed`
  - `Missing Lawful Basis`
- client import pipeline now:
  - rejects binary / unsupported upload content
  - validates supported CSV headers
  - detects duplicate email/phone rows within the import file
  - returns reconciliation counts for created, updated, duplicate, and skipped rows

Key files:

- `app/Models/Client.php`
- `app/Http/Controllers/Api/ClientController.php`
- `app/Models/SystemSetting.php`

### Audit and Monitoring

- API audit trail middleware
- audit log module and export
- auth events logged
- debtor record views are audited
- unusual export-volume detection forces approval review when thresholds are exceeded
- unusual export-volume detection also sends escalation emails to export approvers / compliance roles
- upload events are audited for:
  - client imports
  - branding logo upload/removal
- import uploads are tracked in a dedicated monitoring workflow with stored malware scan status, import status, and reconciliation summary
- compliance workflow actions are audited for:
  - data subject requests
  - complaint cases
  - information officer updates
  - retention policy/action updates
  - bank transfer profile test/sync actions
- client and audit CSV exports include export attribution rows:
  - exported by
  - exported at
  - bank scope
  - user role

Key files:

- `app/Http/Middleware/AuditTrailMiddleware.php`
- `app/Http/Controllers/Api/AuditLogController.php`

## Current Data Model Notes

### Bank / Tenant Segregation

- true bank ownership is now part of the application data model
- `banks` table exists
- `bank_id` is now present on:
  - `users`
  - `clients`
  - `campaigns`
  - `chat_sessions`
  - `audit_logs`
- non-global users are bank-scoped through controller query filters
- client portfolio access is enforced using `clients.assigned_to_id`
- portfolio assignees are exposed through:
  - `GET /api/users-assignees`
- user department assignments now support multiple departments through `department_user`
- departments have a `primary_whatsapp_number` and a JSON array of `secondary_whatsapp_numbers`
- if a department has no numbers configured, the system uses the global WhatsApp number as fallback
- legacy `users.department_id` remains the primary department for backward compatibility
- admin user create/update now keeps `department_user` synchronized with the selected primary department
- existing legacy users need the backfill migration `2026_06_11_160000_backfill_user_department_memberships.php` applied so department-based access uses real membership rows consistently
- current bank helper methods:
  - `User::resolvedBankId()`
  - `User::canAccessAllBanks()`
  - `User::isPortfolioScoped()`
  - `User::resolvedDepartmentIds()`
- campaign visibility is department-based for non-admin operational users
- portfolio-scoped users (`AGENT`) can see campaigns in their departments even before a client is assigned to them
- legacy `STAFF` users are department/bank scoped but are no longer restricted by assigned portfolio ownership
- campaign clients, recipients, stats, and exports remain portfolio-filtered where applicable

### Users

Security-relevant fields:

- `role`
- `status`
- `mfa_enabled`
- `mfa_type`
- `inactivity_timeout`
- `failed_login_attempts`
- `locked_until`
- `password_changed_at`
- `password_reset_required`
- `last_login_ip`
- `last_login_user_agent`
- `deactivated_at`
- `deactivated_by_user_id`
- multiple department membership is stored through `department_user`

### User Settings / Profile

- `Settings > User Account` now saves the full profile fields shown in the form
- department assignment in the profile screen is multi-select based on real `Department` records
- `STAFF` users can view but cannot change their department assignment
- profile endpoints:
  - `GET /api/user`
  - `PUT /api/user`
  - `GET /api/user/department-options`

### Frontend Modal Lifecycle

- Bootstrap modals in SPA views/components now use the shared helper:
  - `resources/js/utils/modal.js`
- shared helpers:
  - `createManagedModal()`
  - `disposeManagedModal()`
  - `cleanupModalArtifacts()`
- this was added to prevent orphaned `.modal-backdrop` overlays and stuck `modal-open` body state after modal-driven flows such as:
  - client edit
  - sensitive export request
  - approval/detail modals

### Clients

Security/business fields:

- `bank_id`
- `id_number`
- `bank_name`
- `account_number`
- `branch_code`
- `assigned_to_id`
- `import_batch_number`
- `arrears_amount`
- `outstanding_balance`
- `installment_amount`
- `last_payment_amount`
- `total_payment_amount`
- `whatsapp_opted_out_at`
- `whatsapp_opt_out_reason`
- `whatsapp_contact_basis`
- `whatsapp_contact_basis_details`
- `whatsapp_opted_in_at`
- `whatsapp_opt_in_source`

Batch Operations:
- Bulk user assignment supports both manual selection and batch-level assignment via `import_batch_number`.
- Endpoints:
  - `POST /api/clients/bulk-assign`
  - `POST /api/clients/assign-batch`
  - `POST /api/clients/delete-batch`

### Import Upload Monitoring

- `import_uploads` now persists client import upload lifecycle records
- frontend monitoring screen:
  - `GET /api/import-uploads`
  - SPA route: `/import-uploads`
- tracked fields include:
  - filename
  - uploader
  - bank scope
  - stored path
  - file hash
  - malware scan status / signature / message
  - import status
  - reconciliation summary
  - scanned/imported timestamps
- common import statuses:
  - `uploaded`
  - `scanning`
  - `scan_passed`
  - `rejected_invalid`
  - `rejected_malware`
  - `scanner_error`
  - `imported`
  - `import_failed`

### Compliance Console

- main SPA route:
  - `/compliance-console`
- main API overview:
  - `GET /api/compliance/overview`
- workflow APIs exist for:
  - data subject requests
  - complaint cases
  - information officers
  - retention policies
  - retention actions
  - bank transfer profiles
- view roles:
  - `SUPER_ADMIN`
  - `ADMIN`
  - `MANAGER`
  - `CALL_CENTRE_MANAGER`
  - `TEAM_LEADER`
  - `AUDITOR`
  - `COMPLIANCE_OFFICER`
  - `READ_ONLY_REVIEWER`
- manage roles:
  - `SUPER_ADMIN`
  - `ADMIN`
  - `COMPLIANCE_OFFICER`

### Secure Bank Transfer Profiles

- profiles are stored in `bank_transfer_profiles`
- test/sync runs are stored in `bank_transfer_runs`
- transfer profiles are bank-scoped
- current implementation expects Laravel `sftp` driver support
- if the SFTP adapter package is not installed, tests and syncs fail safely with a clear governance message instead of silently processing

### System Settings

Sensitive config:

- `meta_app_secret`
- `meta_access_token`
- `meta_webhook_verify_token`
- `admin_ip_allowlist`
- `password_max_age_days`
- `meta_environment`
- `meta_token_last_rotated_at`
- `meta_token_expires_at`
- `meta_token_rotation_notes`
- legacy Twilio secret fields still exist in schema for compatibility

### User Login Sessions

- `user_login_sessions` tracks:
  - `personal_access_token_id`
  - `session_uuid`
  - `ip_address`
  - `user_agent`
  - `authentication_method`
  - `authenticated_at`
  - `last_activity_at`
  - `logged_out_at`
  - `logout_reason`

### WhatsApp Recipient Tracking

- `campaign_whatsapp_recipients`
  - `message_sid`
  - `provider_message_id`
  - `provider_phone_number_id`
  - `provider_display_phone_number`
  - `status_payload`
  - `provider_status_payload`
  - `last_response`
  - `last_response_at`

- `campaign_whatsapp_messages`
  - `provider_phone_number_id`
  - `provider_display_phone_number`

- `campaign_whatsapp_auto_replies`
  - `campaign_whatsapp_message_id`
  - `trigger_keyword`
  - `template_sid`
  - `template_name`
  - `template_variables`

## Important Migrations Added Recently

- `2026_06_03_100000_add_meta_whatsapp_fields.php`
- `2026_06_08_120000_add_security_controls_to_users_and_clients.php`
- `2026_06_10_090000_add_meta_sender_context_to_campaign_whatsapp_tables.php`
- `2026_06_08_130000_encrypt_system_setting_secrets.php`
- `2026_06_10_120000_create_export_requests_table.php`
- `2026_06_10_150000_add_auth_governance_fields_to_users_and_settings.php`
- `2026_06_10_151000_create_user_login_sessions_table.php`
- `2026_06_10_152000_add_whatsapp_compliance_fields_to_clients.php`
- `2026_06_11_090000_add_meta_governance_fields_to_system_settings.php`

After pulling security changes, run:

```bash
php artisan migrate --force
php artisan optimize:clear
npm run build
```

## Key API Endpoints

### Auth

- `POST /api/login`
- `POST /api/login/mfa/verify`
- `POST /api/login/password/reset`
- `POST /api/logout`
- `GET /api/me`
- `GET /api/user/sessions`

### WhatsApp

- `GET /api/whatsapp/webhook`
- `POST /api/whatsapp/webhook`
- `GET /api/whatsapp-templates`
- `GET /api/whatsapp-templates/{id}`
- `GET /api/whatsapp/senders`

### Campaign Operations

- `GET /api/campaigns`
- `POST /api/campaigns`
- `GET /api/campaigns/{campaign}`
- `POST /api/campaigns/{campaign}/attach-clients`
- `POST /api/campaigns/{campaign}/whatsapp-messages`
- `PUT /api/campaigns/{campaign}/whatsapp-messages/{message}`
- `POST /api/campaigns/{campaign}/whatsapp-messages/{message}/send`
- campaign detail exports now exist:
  - `GET /api/campaigns/{campaign}/clients/export`
  - `GET /api/campaigns/{campaign}/whatsapp-messages/export`
  - `GET /api/campaigns/{campaign}/emails/export`
  - `GET /api/campaigns/{campaign}/sms-messages/export`

### Bank and Portfolio Operations

- `GET /api/banks`
- `GET /api/users-assignees`
- global admins must explicitly choose a bank when creating:
  - clients
  - campaigns
  - imported client batches
- operational users are forced to their assigned bank automatically

### Security Incident Operations

- `GET /api/security-incidents`
- `POST /api/security-incidents`
- `GET /api/security-incidents/{securityIncident}`
- `PUT /api/security-incidents/{securityIncident}`
- `POST /api/security-incidents/{securityIncident}/events`
- roles:
  - view: `SUPER_ADMIN`, `ADMIN`, `MANAGER`, `CALL_CENTRE_MANAGER`, `TEAM_LEADER`, `AUDITOR`, `COMPLIANCE_OFFICER`, `READ_ONLY_REVIEWER`
  - manage/update: `SUPER_ADMIN`, `ADMIN`, `COMPLIANCE_OFFICER`
- incidents are bank-scoped for non-global users

## Frontend Structure

- router:
  - `resources/js/router.js`
- layout/sidebar:
  - `resources/js/components/layout/MainLayout.vue`
- major views:
  - `resources/js/views/Clients.vue`
  - `resources/js/views/Campaigns.vue`
  - `resources/js/views/CampaignShow.vue`
  - `resources/js/views/Chat.vue`
  - `resources/js/views/AuditLog.vue`
  - `resources/js/views/SecurityIncidents.vue`
  - `resources/js/views/ExportRequests.vue`
  - `resources/js/views/Settings.vue`
  - `resources/js/views/WhatsAppFlows.vue`
  - `resources/js/views/WhatsappReplies.vue`

## Operational Assumptions

- outbound mail must be configured for MFA email OTP delivery
- Meta app secret must be configured for webhook POST signature validation
- production uses Meta direct, not Twilio
- the app now enforces bank-level tenant scoping and agent portfolio scoping on core operational datasets

## Known Remaining Security Work

These are still not fully implemented and should not be restated as complete until done:

- opt-in evidence capture and suppression-list ingestion from banks
- secret rotation workflows beyond token-metadata tracking
- full read-event auditing for every record view
- malware scanning and secure bank file-transfer channels
- formal data subject rights workflow
- complaint escalation workflow

## Change Log Snapshot

### Completed recently

- **Access & Permissions**: Decoupled bank scoping bypass from system settings and removed the hardcoded `ADMIN` role permission bypass. The `ADMIN` role is now strictly scoped to their assigned bank(s) unless explicitly granted `bypass_bank_scoping`.
- **User Management**: Resolved data persistence bugs for multi-bank and multi-department assignments, added UI badges to the user list, and implemented a secure admin password reset tool that revokes sessions and enforces a password change on next login.
- **WhatsApp Communications**: Fixed media attachment sending errors, restored immediate SMTP dispatch for inbound WhatsApp notifications (with user fallbacks), and added message history pagination to the Chat UI.
- **Campaign Dashboard**: Modernized the Batch Overview modal to use a compact inline metrics strip, expanded modal width for better data density, and fixed the "Pending" count calculation to correctly include 'Sent' status recipients.
- **Dashboard Analytics**: Added overall delivery statistics to the main dashboard and implemented a real-time notification dropdown for incoming WhatsApp replies.
- **UI / Landing Page**: Completely refactored the public landing page to achieve a premium aesthetic matching high-fidelity mockups, and resolved horizontal scrolling/overlap issues in mobile views.
- **Table / Bulk Actions**: Fixed table column overlap in the Clients interface, repaired the "Select All" functionality in campaign client selection, and expanded bulk assignment to support assigning clients by import batch number.
- **System Stability**: Updated `ServeSpaShell` middleware to correctly intercept new SPA routes (preventing 404s on reload) and optimized PHP configurations to prevent `UPLOAD_ERR_INI_SIZE` failures during large CSV imports.
- Strict dynamic database-driven RBAC migration (permissions & permission_role tables, user model capability checks, roles checkbox matrix, router meta permission guards)
- Removed all legacy hardcoded role bypass lists (`STAFF`, `AGENT`, etc.) across model, controllers (`ClientController`, `CampaignController`), and frontend SPA views (`Clients.vue`, `Campaigns.vue`, `CampaignShow.vue`, `MainLayout.vue`)
- Top utility header user avatar dropdown menu (View Profile, Logout, Activity Logs)
- Client schema extended with financial fields: Arrears Amount, Outstanding Balance, Installment Amount, Last Payment Amount, Total Payment Amount
- Bulk client assignment by import batch number (`import_batch_number`)
- Dynamic app name formatting across system mailers using `config('app.name')`
- Home dashboard card counts and metrics calculation fixes
- Meta direct WhatsApp migration completed
- Twilio transport removed from active flow
- WhatsApp template sync and preview from Meta
- reviewer/auditor/compliance read-only enforcement
- privileged MFA login
- webhook signature validation and replay protection
- account lockout
- inactivity timeout middleware
- WhatsApp opt-out suppression
- export approval gating for sensitive datasets

### Update rule

Whenever Codex changes:

- roles
- permissions
- security controls
- data model
- major routes
- integration behavior

this file must be updated in the same task if the change affects future understanding of the system.
## Current Visibility Rules

- campaign detail screens now visibly show:
  - campaign bank
  - client bank
  - assigned portfolio owner
- campaign recipient dashboards and export datasets are portfolio-aware for agent-scoped users
- campaign export CSV files include:
  - export type
  - campaign id/name
  - exported by
  - exported at
  - bank scope
  - user role
  - portfolio scoped flag

## Export Governance

- sensitive exports no longer download directly for most users
- export requests are persisted in `export_requests`
- backend pieces:
  - model: `app/Models/ExportRequest.php`
  - controller: `app/Http/Controllers/Api/ExportRequestController.php`
  - gate trait: `app/Concerns/GuardsSensitiveExports.php`
  - migration: `database/migrations/2026_06_10_120000_create_export_requests_table.php`
- routes:
  - `GET /api/export-requests`
  - `POST /api/export-requests`
  - `POST /api/export-requests/{exportRequest}/approve`
  - `POST /api/export-requests/{exportRequest}/reject`
- protected export endpoints now validate approved export requests for non-bypass users:
  - `GET /api/clients/export`
  - `GET /api/audit-logs/export`
  - `GET /api/campaigns/{campaign}/clients/export`
  - `GET /api/campaigns/{campaign}/whatsapp-messages/export`
  - `GET /api/campaigns/{campaign}/emails/export`
  - `GET /api/campaigns/{campaign}/sms-messages/export`
- approval roles:
  - `SUPER_ADMIN`
  - `ADMIN`
  - `COMPLIANCE_OFFICER`
- bypass roles for immediate export:
  - `SUPER_ADMIN`
  - `ADMIN`

## Current Import Security

- client import route:
  - `POST /api/clients/import`
- imports are bank-scoped and require an authenticated operational user
- imports enforce:
  - file type restrictions
  - file size restrictions
  - binary-content rejection
  - supported header validation
  - duplicate detection inside the uploaded file
  - row-level rejection reporting
  - optional ClamAV malware scanning before CSV parsing when enabled in system settings
- import responses return:
  - `imported`
  - `created`
  - `updated`
  - `duplicates`
  - `skipped`
  - `errors`
- import malware scanning configuration is stored in `system_settings`:
  - `enable_import_malware_scanning`
  - `malware_scanner_socket_path`
  - `malware_scanner_host`
  - `malware_scanner_port`
  - `malware_scanner_timeout_seconds`
- if scanning is enabled:
  - infected uploads are blocked
  - unavailable scanner daemon blocks imports with a clear error
  - scan outcomes are written into audit metadata

## Meta Token Governance

- settings now store:
  - `meta_environment`
  - `meta_token_last_rotated_at`
  - `meta_token_expires_at`
  - `meta_token_rotation_notes`
  - `meta_permissions_last_checked_at`
  - `meta_permissions_status`
  - `meta_permissions_snapshot`
- when the Meta access token changes through settings, `meta_token_last_rotated_at` is automatically set if not provided
- production Meta config now requires:
  - access token
  - WABA ID
  - phone number ID
  - token expiry date
  - token rotation notes
- admins can run `POST /api/settings/meta/validate` to validate the configured token against Meta
- validation currently checks:
  - token validity
  - app ID match
  - required scopes:
    - `whatsapp_business_management`
    - `whatsapp_business_messaging`
  - recommended scope:
    - `business_management`
- settings UI warns when:
  - production token expiry is not tracked
  - token expiry is near
  - token expiry is in the past
- in production, WhatsApp sends are blocked unless the last Meta permission validation is:
  - present
  - `healthy`
  - not older than `META_PERMISSION_VALIDATION_MAX_AGE_HOURS` (default `24`)
- this send-time guard is enforced in:
  - campaign batch send
  - campaign draft send
  - live chat WhatsApp reply send

## Current UI DLP Controls

- browser copy/cut/paste/drop is blocked on:
  - client ID number input
  - client account number input
  - Meta app secret input
  - Meta access token input
  - Meta webhook verify token input
- sensitive routes now render a visible deterrence watermark containing:
  - logged-in user name
  - email
  - role
  - current route
  - current timestamp
- the watermark is active on debtor/security-sensitive views and is intended for attribution and deterrence
- this is not true operating-system screenshot blocking; browsers cannot reliably prevent local screenshots
- this is a browser-layer deterrent only and does not replace endpoint protection or device management
- all approved downloads are single-use:
  - when consumed, the request status becomes `downloaded`
  - the filename, downloader, and download timestamp are stored
- frontend workflow:
  - export buttons in `Clients.vue`, `AuditLog.vue`, and `CampaignShow.vue` open a shared export request modal
  - users are redirected to the `Export Requests` screen to track status
  - shared modal component: `resources/js/components/ExportRequestModal.vue`
  - approvers can inspect full request detail in `ExportRequests.vue` before approving or rejecting
  - export workflow notifications now use app-wide Bootstrap toasts via `resources/js/utils/notify.js`
  - `Export Requests` supports dataset/status/search/date filtering and shows human-readable scope summaries instead of raw filter JSON
- destructive actions across the main operational screens now use the shared `resources/js/components/ConfirmationModal.vue` component instead of browser `confirm()`, including `Clients.vue`, `Users.vue`, `Settings.vue`, `CampaignShow.vue`, `Campaigns.vue`, `Departments.vue`, and `WhatsAppFlows.vue`
- route views are now lazy-loaded in `resources/js/router.js`, and Vite vendor chunking is configured in `vite.config.js` to reduce the main SPA bundle size

## SPA Auth Sync

- The SPA uses Sanctum personal access tokens for authenticated API requests and avoids relying on stale frontend-only user state.
- `resources/js/axios.js` exports `syncAuthenticatedUser()`, which calls `/api/me`, refreshes `localStorage.nexus_user`, and emits `auth-user-updated`.
- `resources/js/components/layout/MainLayout.vue` listens for `auth-user-updated` and refreshes the displayed user context from the authenticated API user.
- `resources/js/views/Clients.vue` and `resources/js/views/Campaigns.vue` call the auth sync helper before their initial data fetches so bank/department/role scoping uses the real authenticated API user.

## Banks Admin

- Banks are now managed through an admin-only SPA screen: `resources/js/views/Banks.vue`.
- Sidebar route: `/banks` (`name: 'banks'`) and API CRUD endpoints are provided by `BankController`.
- `GET /api/banks` now supports pagination, `search`, and `status` filters.
- Admin CRUD endpoints:
  - `POST /api/banks`
  - `PUT /api/banks/{bank}`
  - `DELETE /api/banks/{bank}`
- Deletion is blocked when a bank is referenced by users, clients, campaigns, chat sessions, audit logs, imports, exports, security incidents, or compliance records.
- South African banks are seeded via `database/migrations/2026_06_11_170000_seed_south_african_banks.php`.
- Bank dropdown consumers now request `per_page=200` so the full bank list is available in forms and filters.
