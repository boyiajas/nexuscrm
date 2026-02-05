# System Flows

End-to-end paths to understand how pieces connect.

## Auth session
1) User visits `/login` (Login.vue) and posts credentials to `POST /api/login`.
2) Server issues Sanctum token; frontend stores `localStorage.nexus_user`.
3) Router guard in `resources/js/router.js` blocks protected routes when token missing; `/api/logout` clears server session; frontend clears storage.

## Client onboarding
1) Create client in Clients.vue modal -> `POST /api/clients` with departments/tags.
2) Client stored with many-to-many departments; audit entry written via `HasAuditLogging`.
3) CSV import uses `POST /api/clients/import` (multipart); export uses `GET /api/clients/export`.

## Campaign creation and send (WhatsApp template)
1) Campaigns.vue creates campaign -> `POST /api/campaigns` with channels/departments/whatsapp_from.
2) Attach clients via CampaignShow.vue -> `POST /api/campaigns/{id}/attach-clients` (all or selected).
3) Add WhatsApp batch (template mode) -> `POST /api/campaigns/{id}/whatsapp-messages` with template_id and recipients. Controller creates `CampaignWhatsappMessage` + recipients; optionally sends immediately via `TwilioWhatsAppService`.
4) Recipients dashboard pulls `GET /api/campaigns/{id}/whatsapp-messages/{message}/recipients`; batch list uses `GET /api/campaigns/{id}/whatsapp-messages`.
5) Twilio status/reply webhook posts to `POST /api/twilio/webhook/whatsapp` (public). Delivery statuses update `CampaignWhatsappRecipient.status`/`delivered_at` and recalc batch counts. Inbound replies set `last_response`, feed yes/no metrics, and create chat sessions/messages for live chat.

## Campaign creation and send (WhatsApp flow)
1) Build flow in WhatsAppFlows.vue -> `POST /api/whatsapp-flows` with `flow_definition` and template_sid.
2) CampaignShow.vue WhatsApp modal chooses mode `flow` and a saved flow; controller copies flow definition into batch for historical consistency and sends via flow’s template_sid.

## Email/SMS batches
1) CampaignShow.vue Email/SMS tabs add batches (API endpoints in CampaignController).
2) Recipients dashboards call `/emails/{id}/recipients` and `/sms-messages/{id}/recipients` to show delivery breakdown.

## Template preview
1) Template dropdowns load from `GET /api/whatsapp-templates` (CampaignController/WhatsAppTemplateController).
2) Full preview page (`WhatsappTemplatePreview.vue`) calls `GET /api/whatsapp-templates/{id}` for content/media/approval.

## Chat
1) Chat.vue lists sessions via `GET /api/chat/sessions` (status filter).
2) Opening a session loads messages via `GET /api/chat/sessions/{session}`.
3) Sending posts to `POST /api/chat/sessions/{session}/messages` and refreshes session list for unread counts. (Inbound webhook stubbed in controller.)

## Audit trail
1) Controllers using `AuditLogger`/`HasAuditLogging` write rows to `audit_logs` (user, action, module, meta, IP, logged_at).
2) AuditLog.vue consumes `GET /api/audit-logs` (filters), `GET /api/audit-logs/{id}`, and CSV export `GET /api/audit-logs/export`.

## Settings & Twilio config
1) Settings.vue pulls `/api/settings` and `/api/user`.
2) Admin updates to Twilio keys/SIDs post to `/api/settings`; future requests instantiate `TwilioWhatsAppService` with updated values.
