# Campaigns

## Purpose
Plan and send multi-channel (WhatsApp/Email/SMS) campaigns scoped to departments and clients, with batch dashboards.

## Backend
- **Controller**: `CampaignController` (`app/Http/Controllers/Api/CampaignController.php`).
- **Listing/CRUD**:
  - `GET /api/campaigns` (status filter optional) with department scoping for non-super-admins; includes departments and `total_recipients`.
  - `POST /api/campaigns`, `PUT /api/campaigns/{id}`, `DELETE /api/campaigns/{id}`, `GET /api/campaigns/{id}`.
  - Department scoping enforced in `authorizeView/authorizeManageCampaign`; manage restricted to SUPER_ADMIN/MANAGER.
  - Default WhatsApp sender auto-filled from first department number or system setting.
- **Client management**:
  - `GET /api/campaigns/{campaign}/clients`: paginated clients with pivot statuses.
  - `GET /api/campaigns/{campaign}/available-clients`: department-matched clients not yet attached.
  - `POST /api/campaigns/{campaign}/attach-clients`: attach all or selected clients (initial statuses set to Pending).
- **Stats**:
  - `GET /api/campaigns/{campaign}/stats`: aggregates sent/delivered/failed/pending across channels plus client count.
- **WhatsApp channel**:
  - `GET /api/campaigns/{campaign}/whatsapp-messages`: list batches with yes/no response counts, flow/template metadata.
  - `POST /api/campaigns/{campaign}/whatsapp-messages`: create batch (template or flow), optionally send immediately; creates `CampaignWhatsappMessage` + recipients and optionally calls Twilio via `TwilioWhatsAppService`.
  - `PUT /api/campaigns/{campaign}/whatsapp-messages/{message}`: edit batch recipients/template/flow; optional send-now.
  - `POST /api/campaigns/{campaign}/whatsapp-messages/{message}/send`: send an existing draft.
  - `DELETE /api/campaigns/{campaign}/whatsapp-messages/{message}`: delete batch.
  - `GET /api/campaigns/{campaign}/whatsapp-messages/{messageId}/recipients`: recipient dashboard (status summary + agent placeholder + recipient list with departments).
  - Status/reply webhook: `POST /api/twilio/webhook/whatsapp` (public) receives Twilio delivery callbacks + inbound replies. Updates `CampaignWhatsappRecipient` status, last_response, and recalculates batch delivery counts.
- **Email channel**:
  - `GET /api/campaigns/{campaign}/emails`: list batches; `GET /.../emails/{id}/recipients`: recipients + summary.
- **SMS channel**:
  - `GET /api/campaigns/{campaign}/sms-messages`: list; `GET /.../sms-messages/{id}/recipients`: recipients + summary.
- **Template helpers**:
  - `GET /api/whatsapp-templates` (CampaignController wrapper) for dropdowns; `GET /api/whatsapp-templates/{id}` for preview (delegates to `TwilioWhatsAppService`).

## Frontend
- **Campaigns.vue**: list with status/channel badges, department chips, action buttons (edit/send/delete). Create/edit modal supports multi-departments, channel selection, WhatsApp sender choices derived from departments (plus system default), scheduled_at, status. Role check from stored user.
- **CampaignShow.vue**: detailed operations:
  - Overview cards from `/api/campaigns/{id}/stats`.
  - Clients tab: attach clients (search/filter, select-all), export CSV.
  - WhatsApp tab: add template or flow batch, optional send immediately, live chat toggle, track responses flag, per-batch dashboard and recipient modal.
  - Email tab: add template (new or existing), view batch delivery stats + recipients.
  - SMS tab: add template and view recipients dashboard.
  - Header actions: Send Now (stubbed server response) and Refresh.
- **WhatsappTemplatePreview.vue**: deep link to inspect Twilio template content/media + variables via `/api/whatsapp-templates/{sid}`.

## Data & Permissions
- Department scoping for non-super-admin applies to listing, viewing, attaching clients.
- WhatsApp sending relies on valid Twilio ContentSid; flow batches reuse `WhatsAppFlow` template_sid + definition.
- Recipients tables (`campaign_*_recipients`) hold delivery state; batch tables hold summary counts.
- Live chat toggle (`enable_live_chat`) is stored on WhatsApp batches for UI/display; chat routing beyond storing flag is not implemented here.
- Twilio setup: point WhatsApp status callback/webhook to `/api/twilio/webhook/whatsapp` (public) so delivery + replies update batches and chat.
