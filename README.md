# NexusCRM

NexusCRM is a Laravel + Vue 3 application for running WhatsApp-first campaigns, live chat, and audit logging. Recent additions include WhatsApp automation flows, Twilio template management, and a collapsible dashboard sidebar.

## Stack
- Backend: Laravel, Sanctum auth, MySQL
- Frontend: Vue 3 (Vite), Chart.js, Bootstrap Icons
- Messaging: Twilio WhatsApp Content Templates

## Quickstart
1) Copy environment and set secrets:
```bash
cp .env.example .env
php artisan key:generate
```
Fill Twilio keys in `.env` (see below).

2) Install dependencies:
```bash
composer install
npm install
```

3) Database:
```bash
php artisan migrate
```

4) Build / dev:
```bash
npm run dev   # or npm run build
php artisan serve
```

## Key Features
- Dashboard with campaign activity (auto-renders on load), channel breakdown, delivery stats.
- WhatsApp Templates synced from Twilio; media preview support.
- WhatsApp Flows automation: create/edit/delete flows, dynamic steps, branching Yes/No paths, sub-decisions, and org-chart style diagram preview.
- Collapsible sidebar with Automation section and WhatsApp Flows entry.
- Campaigns, clients, chat sessions, audit logs, departments, users.

## WhatsApp / Twilio Setup
Set the following in `.env` (or via System Settings in-app):
```
TWILIO_SID=ACxxxxxxxx
TWILIO_TOKEN=xxxxxxxx
TWILIO_WHATSAPP_FROM=whatsapp:+27...
TWILIO_MSG_SID=MGxxxxxxxx      # optional messaging service
TWILIO_TEMPLATE_SID=HXxxxxxxxx  # default ContentSid (optional)
TWILIO_STATUS_CALLBACK=https://your-domain.com/api/twilio/status
```

## WhatsApp Flows
- API: `/api/whatsapp-flows` (CRUD) with `flow_definition` JSON storing steps, decisions, and branch targets.
- UI: `Automation -> WhatsApp Flows` (route: `/automation/whatsapp-flows`) lists flows, provides a modal for creating/editing, dynamic steps, template media preview, and diagram view.
- Diagram uses org-chart styling with nested branches for Yes/No decisions.

## Routes (frontend)
- Dashboard `/`
- Clients `/clients`
- Campaigns `/campaigns`
- Chat `/chat`
- Audit Log `/audit-log`
- Settings `/settings`
- Departments `/departments`
- Users `/users`
- WhatsApp Flows `/automation/whatsapp-flows`

## Maintenance Notes
- Migration for flows: `2026_02_01_000000_create_whatsapp_flows_table.php`
- Model table override: `App\Models\WhatsAppFlow` uses `whatsapp_flows`.
- Dashboard chart rendering waits for DOM (`$nextTick`) to avoid missing initial render.
- Sidebar supports collapse (icon-only) toggle in `MainLayout.vue`.

## Testing
Project does not include an automated suite yet. Run targeted checks as needed:
```bash
php artisan test
npm run build
```
