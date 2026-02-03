# Data Models

Main Eloquent models in `app/Models/` and their notable fields/relations.

- **User**: `name`, `email`, `username`, `role`, `status`, `department_id`, phones, `inactivity_timeout`, `is_provider`, `is_time_clock_user`; belongsTo `Department`; helpers `isSuperAdmin|isManager|isStaff`.
- **Department**: `name`, `description`, `whatsapp_numbers` (array of sender numbers); many-to-many with clients/campaigns via pivots.
- **Client**: core contact info, banking fields, `tags` (array), `assigned_to_id`; belongsToMany `departments`, belongsToMany `campaigns` (pivot `campaign_clients`), accessor `department` for backward compatibility.
- **Campaign**: `name`, `channels` (JSON array), `status`, `scheduled_at`, `template_body`, `whatsapp_from`; many-to-many `departments` and `clients`; hasMany WhatsApp/Email/SMS message batches.
- **CampaignClient (pivot)**: per-client status per channel (`whatsapp_status`, `email_status`, `sms_status`), send timestamps.
- **CampaignWhatsappMessage**: batch metadata (`mode` template/flow, template/flow identifiers, preview, `enable_live_chat`, `track_responses`, counts, `sent_at`); hasMany `CampaignWhatsappRecipient`.
- **CampaignWhatsappRecipient**: `whatsapp_message_id`, `client_id`, `phone`, `status`, `error_code`, `error_message`, `delivered_at`, `last_response`, `last_response_at`; belongsTo message and client.
- **CampaignEmailMessage** / **CampaignEmailRecipient**: subject/preview counts and per-recipient status with delivered/opened/clicked timestamps.
- **CampaignSmsMessage** / **CampaignSmsRecipient**: text and delivery counts; per-recipient status/delivered_at.
- **ChatSession**: `client_id`, `agent_id`, `client_name`, `status`, `platform`, `last_message`, `unread_count`; belongsTo client/agent; hasMany `ChatMessage`.
- **ChatMessage**: `chat_session_id`, `sender` (client/agent/system), `content`, `is_template`, `sent_at`.
- **AuditLog**: `user_id`, `action`, `module`, `ip_address`, `meta` (JSON), `logged_at`; belongsTo user; query scopes for module/user/date/search.
- **SystemSetting**: Twilio + provider configuration (API keys, Content SID, Messaging Service SID, status callback, etc.).
- **WhatsAppFlow**: `name`, template identifiers, `status`, `description`, `flow_definition` (array of steps/branches), `created_by`; belongsTo creator `User`.
