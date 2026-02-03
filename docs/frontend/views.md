# Frontend Pages

All views live in `resources/js/views/` unless noted.

- **Login.vue / Register.vue**: public auth forms posting to `/api/login` and `/api/register`; on success stores `nexus_user` in `localStorage` and redirects to dashboard.
- **Dashboard.vue**: loads `/api/dashboard` for summary cards, channel breakdown, recent activity, and 7-day campaign chart (with Chart.js). Refresh button refetches.
- **Clients.vue**: CRUD UI for clients with filters (search, department, tag), pagination, CSV import/export. Uses `VueMultiselect` for assigning departments; calls `/api/clients`, `/api/clients/{id}`, `/api/clients/import|export`.
- **Campaigns.vue**: list/create/edit campaigns with department scoping, channel selection, status, schedule, WhatsApp sender choices derived from departments. Sends save/delete/send actions to CampaignController (`/api/campaigns[...]`).
- **CampaignShow.vue**: campaign detail with tabs:
  - Clients: view attached clients, add clients modal (filters, multi-select) via `/api/campaigns/{id}/clients|available-clients|attach-clients`.
  - WhatsApp: manage batches (template or flow), preview, send drafts, view recipients dashboard. Uses `/api/campaigns/{id}/whatsapp-messages[...]` plus template lists from `/api/whatsapp-templates`.
  - Email: batch list and recipients dashboard (`/api/campaigns/{id}/emails` and `/recipients`).
  - SMS: batch list and recipients dashboard (`/api/campaigns/{id}/sms-messages` and `/recipients`).
  - Header buttons trigger `/api/campaigns/{id}/send` or refresh stats (`/api/campaigns/{id}/stats`).
- **WhatsappTemplatePreview.vue**: deep-link from CampaignShow to preview a Twilio template with variables/media via `/api/whatsapp-templates/{id}`.
- **Chat.vue**: live chat console showing sessions list and message thread; sends/loads via `/api/chat/sessions[...]` and `/messages`.
- **AuditLog.vue**: filterable audit trail with module/user/date/search filters; CSV export. Calls `/api/audit-logs`, `/api/audit-logs/{id}`, `/api/audit-logs/export`.
- **Settings.vue**: tabbed settings for user profile, security/MFA, preferences, Twilio config, template shortcuts. Uses `/api/user`, `/api/settings`, `/api/mfa/*`, and WhatsApp template endpoints for preview.
- **Departments.vue**: CRUD with pagination; assigns WhatsApp numbers from `/api/twilio/whatsapp-senders`. Endpoints `/api/departments[...]`.
- **Users.vue**: list/manage users, view profile modal; uses `/api/users` for CRUD and `/api/user` for current profile data.
- **WhatsAppFlows.vue**: builder for automation flows with diagram preview; CRUD against `/api/whatsapp-flows`; pulls templates list from `/api/whatsapp-templates`.

Shared layout components in `resources/js/components/layout/`:
- **MainLayout.vue**: sidebar navigation, collapse toggle, header with user info + logout (`/api/logout`), wraps child routes.
- **App.vue**: root wrapper and modal styling.
