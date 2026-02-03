# Clients

## Purpose
Manage customer records, tags, banking info, and department ownership; supply recipients for campaigns and chat.

## Backend
- **Controller**: `ClientController` (`app/Http/Controllers/Api/ClientController.php`).
- **Endpoints**:
  - `GET /api/clients`: paginated list with filters `search` (name/email/phone), `department` (name), `department_id`, `tag`.
  - `POST /api/clients`: create (requires department_ids).
  - `GET /api/clients/{id}`: detail with departments.
  - `PUT /api/clients/{id}`: update; department-scoped checks for non-super-admins.
  - `DELETE /api/clients/{id}`: delete client.
  - `POST /api/clients/import`: CSV import (multipart). Expects `file`; returns counts/errors.
  - `GET /api/clients/export`: CSV export honoring filters.
- **Model**: `Client` with `tags` array, contact + banking fields; belongsToMany `departments`, belongsToMany `campaigns` via pivot `campaign_clients`. Accessor `department` for legacy single-dept code.
- **Audit**: uses `HasAuditLogging` to log creates/updates with payload meta.
- **Permissions**: create/update/delete restricted to SUPER_ADMIN/MANAGER; list scoped to department for non-super-admin.

## Frontend
- View: `resources/js/views/Clients.vue`.
- Features: filters + pagination, CSV import/export, create/edit modal with `VueMultiselect` for departments, tags parsing, delete action.
- Import flow: hidden file input -> POST to `/api/clients/import` -> alert on result -> refresh list.
- Export flow: opens `/api/clients/export` with current filters in a new tab for CSV download.

## Data Considerations
- Department association mandatory (multi-select); client pivot data used later by campaigns for channel statuses.
- Tags stored as simple string arrays; UI accepts comma-separated input.
