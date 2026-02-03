# Authorization & Scoping

## Roles
- `SUPER_ADMIN`: full access to all resources.
- `MANAGER`: manage most entities (clients, campaigns, departments, users, settings) but still subject to department scoping for visibility in some controllers.
- `STAFF`: read-only in most management areas; limited create/update permissions.

Helpers: `User::isSuperAdmin()`, `isManager()`, `isStaff()` in `App\Models\User`.

## Department Scoping
- **Clients**: listing filtered to the user's department (non-super-admin); updates/deletes allowed only if the client belongs to the user’s department.
- **Campaigns**: listing shows global (no departments) or same-department campaigns for non-super-admins. `authorizeView`/`authorizeManageCampaign` combine role check with department match. Attach clients constrained to campaign departments.
- **Dashboard**: counts respect user department for clients/campaigns; recent activity limited to self for non-super-admin.
- **Available clients for campaigns**: restricted to campaign departments and excludes already-attached clients.

## Admin-only Paths
- **SettingsController**: `authorizeAdmin` guards `/api/settings` updates (SUPER_ADMIN or MANAGER).
- **WhatsAppTemplateController**: create/update/delete/submit require admin roles; list/show allowed for authenticated users.
- **Departments/User management**: DepartmentController and UserController restrict create/update/delete to SUPER_ADMIN/MANAGER.

## Authentication
- Sanctum token required for all `/api` routes except `POST /register`, `POST /login`.
- Router guard (frontend) redirects unauthenticated users to `/login`.

## MFA
- Email OTP via `MfaController`; status stored on user. Frontend drives enable/verify/disable flows in Settings.
