# WhatsApp Flows

## Purpose
Reusable automation flows (Yes/No branches) backed by Twilio-approved templates; can be attached to campaigns as WhatsApp batches.

## Backend
- **Controller**: `WhatsAppFlowController` (`app/Http/Controllers/Api/WhatsAppFlowController.php`).
- **Endpoints**: `GET /api/whatsapp-flows`, `POST /api/whatsapp-flows`, `GET /api/whatsapp-flows/{id}`, `PUT /api/whatsapp-flows/{id}`, `DELETE /api/whatsapp-flows/{id}`.
- **Data shape**: `name`, `template_sid/name/language`, `status`, `description`, `flow_definition` (array of steps with labels/messages/branch targets), `created_by`.
- **Transforms**: controller maps flows to include creator name and normalizes dates; flow_definition stored as JSON array.

## Frontend
- **WhatsAppFlows.vue**: table of flows, create/edit modal with step builder (supports decision steps with yes/no next-step mapping and hints), template selector sourced from `/api/whatsapp-templates`, media preview if template contains images, diagram modal rendering flow as org-tree.
- **CampaignShow.vue**: WhatsApp tab supports sending a Flow batch (mode `flow`), choosing from saved flows; preview shows flow steps, and batch stores flow_definition for recipients.

## Data & Permissions
- Flow status flag (`active`/`inactive`) is used for display only; controller still returns all flows.
- Campaign WhatsApp batches copy flow_definition into `CampaignWhatsappMessage` for historical accuracy even if flow changes later.
