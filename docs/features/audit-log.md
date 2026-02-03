# Audit Log

## Purpose
Track user actions across modules with searchable/filterable history and export.

## Backend
- **Controller**: `AuditLogController` (`app/Http/Controllers/Api/AuditLogController.php`).
- **Endpoints**:
  - `GET /api/audit-logs`: paginated list with filters `module`, `user_id`, `date_from`, `date_to`, `q` (search across action/module/ip/meta).
  - `GET /api/audit-logs/{id}`: single entry detail.
  - `GET /api/audit-logs/export`: CSV stream honoring the same filters.
- **Model**: `AuditLog` with scopes for module/user/date/search; casts `meta` JSON and `logged_at` datetime.
- **Writer**: `AuditLogger` service and `HasAuditLogging` trait (used in ClientController) standardize writes.
- **Fields captured**: user_id (nullable), action (human text), module, ip_address, meta (JSON), logged_at timestamp.

## Frontend
- View: `resources/js/views/AuditLog.vue`.
- Features: filters, paginated table, detail modal (prettified meta JSON), CSV export button, refresh action.
- Module and user filter options populated from current results and `/api/users?per_page=200`.

## Data & Permissions
- Dashboard recent activity uses audit log data; non-super-admin dashboard shows only their own entries.
- Export uses streamed response; browser downloads CSV directly.
