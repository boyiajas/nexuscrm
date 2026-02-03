# Authentication & Users

## Purpose
User onboarding, session management, MFA, and admin user CRUD.

## Backend
- **AuthController**: `/api/login`, `/api/register`, `/api/logout`, `/api/me` issue/validate Sanctum tokens.
- **UserProfileController**: `/api/user` (GET/PUT) reads/updates the logged-in user's profile (name, email, phones, inactivity timeout, provider/time-clock flags).
- **MfaController**: `/api/mfa/status`, `/setup-email`, `/verify-email`, `/disable` manage email OTP-based MFA state on the user record.
- **UserController**: RESTful CRUD (excluding show) for admin user management; enforces role/department access; pagination supported.
- **Roles**: `App\Models\User` exposes `isSuperAdmin|isManager|isStaff`; controllers gate management actions to SUPER_ADMIN/MANAGER.

## Frontend
- **Login.vue / Register.vue**: public routes; on success saves `nexus_user` JSON to `localStorage`; router guard (`resources/js/router.js`) redirects unauthenticated users.
- **MainLayout.vue**: shows user name/role pulled from `localStorage`; logout posts to `/api/logout` then clears localStorage.
- **Settings.vue**: User Account tab edits profile via `/api/user`; Security tab drives MFA enable/verify/disable.
- **Users.vue**: admin list/create/edit/delete users; profile modal shows extended fields; calls `/api/users` endpoints.

## Data & Permissions
- Tokens are stored client-side only in `localStorage`; axios sends them automatically via interceptor.
- Department scoping applied in UserController where relevant; SUPER_ADMIN bypasses.
