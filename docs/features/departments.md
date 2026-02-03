# Departments

## Purpose
Organize clients/campaigns/users by department and manage WhatsApp sender numbers per department.

## Backend
- **Controller**: `DepartmentController` (`app/Http/Controllers/Api/DepartmentController.php`).
- **Endpoints**: REST `GET /api/departments`, `POST`, `PUT`, `DELETE`, with pagination support via `per_page`.
- **Model**: `Department` (`name`, `description`, `whatsapp_numbers` array).
- **Relationships**: many-to-many with clients (`client_department`) and campaigns (`campaign_department`); users belongTo department.
- **Permissions**: management restricted to SUPER_ADMIN/MANAGER; listing open to authenticated users.

## Frontend
- View: `resources/js/views/Departments.vue`.
- Features: paginated table, add/edit modal, pick WhatsApp numbers from `/api/twilio/whatsapp-senders`, badge display of numbers (default fallback when empty), delete action.
- Sender fetch: uses `TwilioController@whatsappSenders` to populate number dropdown.

## Data & Impact
- Department selections drive client visibility and campaign scoping.
- WhatsApp sender numbers attached to departments feed into campaign creation (default `whatsapp_from` fallback).
