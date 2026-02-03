# WhatsApp Templates

## Purpose
Manage Twilio Content templates and surface them for campaign WhatsApp sends and flows.

## Backend
- **WhatsAppTemplateController** (`app/Http/Controllers/Api/WhatsAppTemplateController.php`):
  - `GET /api/whatsapp-templates`: list templates from Twilio Content API (approved by default); maps to id/name/language/category/preview/variables/media.
  - `GET /api/whatsapp-templates/{id}`: full template details + approval status.
  - `POST /api/whatsapp-templates`: create a new template (friendly name, body, language, category, optional media).
  - `PUT /api/whatsapp-templates/{id}`: update template metadata/types/media.
  - `DELETE /api/whatsapp-templates/{id}`: delete template in Twilio.
  - `POST /api/whatsapp-templates/{id}/submit`: submit for WhatsApp approval (category required).
  - Admin-only actions enforced via `authorizeAdmin()` (roles SUPER_ADMIN or MANAGER).
- **CampaignController helpers**: `listWhatsappTemplates` and `showWhatsappTemplate` expose the same data for campaign dropdowns/previews.
- **Service dependency**: all Twilio calls go through `TwilioWhatsAppService`.

## Frontend
- **CampaignShow.vue**: Add WhatsApp Template modal fetches template list; Preview/Configure button links to dedicated preview view.
- **WhatsappTemplatePreview.vue**: shows template body/variables and media assets; fetches `/api/whatsapp-templates/{id}` on load.
- **WhatsAppFlows.vue**: when selecting a template for a flow, pulls `/api/whatsapp-templates` to ensure the flow is tied to an approved template.

## Data & Permissions
- Template list defaults to approved-only; pass `approved=0` to include drafts/rejected.
- Media URLs surfaced from Twilio `types.twilio/media` or `types.twilio/card.media`.
- Requires valid Twilio SID/token and ContentSid configuration in `.env` or `SystemSetting`.
