# Chat

## Purpose
Lightweight live chat console for client conversations; supports campaign live-chat opt-in flag on WhatsApp batches.

## Backend
- **Controller**: `ChatController` (`app/Http/Controllers/Api/ChatController.php`).
- **Endpoints**:
  - `GET /api/chat/sessions` (filters: `status` = all/active/closed) with client + agent relations.
  - `GET /api/chat/sessions/{session}`: loads session with client, agent, and messages (ordered asc).
  - `POST /api/chat/sessions/{session}/messages`: append message (`content`, `is_template` bool).
  - `POST /api/chat/webhook` is not present; inbound messages currently stubbed by `receiveFromClient(Request)` for integrations.
- **Models**: `ChatSession` (client/agent IDs, platform, unread_count, last_message) and `ChatMessage` (sender enum, content, sent_at).

## Frontend
- View: `resources/js/views/Chat.vue`.
- Features: left column session list with status badges/unread counts + agent names; right column conversation thread; send message form posts to `/messages`; filter by status dropdown triggers refetch.
- UX: scroll-to-bottom after loading/sending.

## Data & Permissions
- No explicit role gate beyond Sanctum; department scoping not applied here.
- Campaign WhatsApp batches support `enable_live_chat` flag, but routing to chat sessions depends on external webhook integration to create/attach sessions.
