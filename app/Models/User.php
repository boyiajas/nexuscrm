<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_MANAGER = 'MANAGER';
    public const ROLE_CALL_CENTRE_MANAGER = 'CALL_CENTRE_MANAGER';
    public const ROLE_TEAM_LEADER = 'TEAM_LEADER';
    public const ROLE_AGENT = 'AGENT';
    public const ROLE_STAFF_LEGACY = 'STAFF';
    public const ROLE_AUDITOR = 'AUDITOR';
    public const ROLE_COMPLIANCE_OFFICER = 'COMPLIANCE_OFFICER';
    public const ROLE_READ_ONLY_REVIEWER = 'READ_ONLY_REVIEWER';

    public const ALL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_CALL_CENTRE_MANAGER,
        self::ROLE_TEAM_LEADER,
        self::ROLE_AGENT,
        self::ROLE_STAFF_LEGACY,
        self::ROLE_AUDITOR,
        self::ROLE_COMPLIANCE_OFFICER,
        self::ROLE_READ_ONLY_REVIEWER,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_changed_at',
        'password_reset_required',
        'role',
        'status',
        'department',
        'department_id',
        'bank_id',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'username',
        'first_name',
        'middle_initial',
        'last_name',
        'primary_phone',
        'secondary_phone',
        'inactivity_timeout',
        'failed_login_attempts',
        'locked_until',
        'deactivated_at',
        'deactivated_by_user_id',
        'is_provider',
        'is_time_clock_user',
        'avatar_path',
        'preferences',
        'mfa_enabled',
        'mfa_type',
        'mfa_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'permission_codes',
        'role_codes',
        'role_names',
        'avatar_url',
        'is_locked',
    ];

    public function getIsLockedAttribute(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function getPermissionCodesAttribute(): array
    {
        return $this->permissions();
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password_changed_at' => 'datetime',
        'password_reset_required' => 'boolean',
        'deactivated_at' => 'datetime',
        'is_provider' => 'boolean',
        'is_time_clock_user' => 'boolean',
        'preferences' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function banks()
    {
        return $this->belongsToMany(Bank::class, 'bank_user')
            ->withTimestamps();
    }

    public function loginSessions()
    {
        return $this->hasMany(UserLoginSession::class);
    }

    public function importUploads()
    {
        return $this->hasMany(ImportUpload::class);
    }

    public function isSuperAdmin()
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isAdmin()
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isManager()
    {
        return $this->hasRole([
            self::ROLE_MANAGER,
            self::ROLE_CALL_CENTRE_MANAGER,
            self::ROLE_TEAM_LEADER,
        ]);
    }

    public function isStaff()
    {
        return $this->hasRole([self::ROLE_AGENT, self::ROLE_STAFF_LEGACY]);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        return !empty(array_intersect($this->resolvedRoleCodes(), $roles));
    }

    public function getRoleCodesAttribute(): array
    {
        return $this->resolvedRoleCodes();
    }

    public function getRoleNamesAttribute(): array
    {
        if ($this->relationLoaded('roles')) {
            $names = $this->roles->pluck('name')->filter()->values()->all();
            if (!empty($names)) {
                return $names;
            }
        }

        return array_map(fn (string $code) => str_replace('_', ' ', $code), $this->resolvedRoleCodes());
    }

    public function permissions(): array
    {
        $roleCodes = $this->resolvedRoleCodes();
        if (empty($roleCodes)) {
            return [];
        }

        $roles = Role::query()
            ->whereIn('code', $roleCodes)
            ->where('is_active', true)
            ->with('permissions')
            ->get();

        $permCodes = [];
        foreach ($roles as $r) {
            foreach ($r->permissions as $p) {
                $permCodes[] = $p->code;
            }
        }

        return array_values(array_unique($permCodes));
    }

    public function hasPermission(string|array $permissions): bool
    {
        if ($this->hasRole([self::ROLE_SUPER_ADMIN])) {
            return true;
        }

        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $userPerms = $this->permissions();

        foreach ($permissions as $p) {
            if (in_array($p, $userPerms, true)) {
                return true;
            }
        }

        return false;
    }

    public function canManageSystemSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('manage_system_settings') || $this->hasPermission('settings_system');
    }

    public function canAccessUserAccountSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('settings_user_account');
    }

    public function canAccessMetaWhatsappSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('settings_meta_whatsapp');
    }

    public function canAccessWabaProfileSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('settings_waba_profile');
    }

    public function canAccessWabaNumbersSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('settings_waba_numbers');
    }

    public function canAccessWabaTemplatesSettings(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('settings_waba_templates');
    }

    public function canAccessAnySettings(): bool
    {
        return $this->canManageSystemSettings()
            || $this->canAccessUserAccountSettings()
            || $this->canAccessMetaWhatsappSettings()
            || $this->canAccessWabaProfileSettings()
            || $this->canAccessWabaNumbersSettings()
            || $this->canAccessWabaTemplatesSettings();
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission('manage_users');
    }

    public function canManageRoles(): bool
    {
        return $this->hasPermission('manage_roles');
    }

    public function canManageDepartments(): bool
    {
        return $this->hasPermission('manage_departments');
    }

    public function canManageBanks(): bool
    {
        return $this->hasPermission('manage_banks');
    }

    public function canManageUsersAndDepartments(): bool
    {
        return $this->hasPermission(['manage_users', 'manage_roles', 'manage_departments', 'manage_banks']);
    }

    public function canManageOperationalData(): bool
    {
        return $this->hasPermission([
            'create_clients',
            'edit_clients',
            'delete_clients',
            'import_clients',
            'create_campaigns',
            'edit_campaigns',
            'delete_campaigns',
            'send_whatsapp',
            'manage_auto_replies',
            'manage_whatsapp_flows',
        ]);
    }

    public function canViewCampaigns(): bool
    {
        return $this->hasPermission('view_campaigns');
    }

    public function canCreateCampaigns(): bool
    {
        return $this->hasPermission('create_campaigns');
    }

    public function canEditCampaigns(): bool
    {
        return $this->hasPermission('edit_campaigns');
    }

    public function canDeleteCampaigns(): bool
    {
        return $this->hasPermission('delete_campaigns');
    }

    public function canViewClients(): bool
    {
        return $this->hasPermission('view_clients');
    }

    public function canCreateClients(): bool
    {
        return $this->hasPermission('create_clients');
    }

    public function canEditClients(): bool
    {
        return $this->hasPermission('edit_clients');
    }

    public function canDeleteClients(): bool
    {
        return $this->hasPermission('delete_clients');
    }

    public function canImportClients(): bool
    {
        return $this->hasPermission('import_clients');
    }

    public function canViewAllImportedClients(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->hasPermission('view_all_imported_clients');
    }

    public function canViewOperationalData(): bool
    {
        return $this->hasPermission([
            'view_clients',
            'create_clients',
            'edit_clients',
            'delete_clients',
            'import_clients',
            'view_campaigns',
            'create_campaigns',
            'edit_campaigns',
            'delete_campaigns',
            'view_live_chat',
            'send_whatsapp',
            'manage_auto_replies',
            'manage_whatsapp_flows',
        ]);
    }

    public function canReviewAuditData(): bool
    {
        return $this->hasPermission([
            'view_audit_logs',
            'view_security_incidents',
            'manage_security_incidents',
            'view_compliance_console',
            'manage_compliance_console',
            'request_exports',
            'approve_exports',
            'bypass_export_approval',
        ]);
    }

    public function isReadOnlyRole(): bool
    {
        return $this->hasRole([
            self::ROLE_AUDITOR,
            self::ROLE_COMPLIANCE_OFFICER,
            self::ROLE_READ_ONLY_REVIEWER,
        ]);
    }

    public function requiresLoginMfa(): bool
    {
        return (bool) $this->mfa_enabled;
    }

    public function requiresAdminIpAllowlist(): bool
    {
        return $this->hasRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public function canRequestSensitiveExports(): bool
    {
        return $this->hasPermission('request_exports');
    }

    public function canViewExportRequests(): bool
    {
        return $this->hasPermission([
            'request_exports',
            'approve_exports',
            'bypass_export_approval',
        ]);
    }

    public function canApproveExportRequests(): bool
    {
        return $this->hasPermission('approve_exports');
    }

    public function canViewAuditLogs(): bool
    {
        return $this->hasPermission([
            'view_audit_logs',
            'view_audit_logs_role_only',
            'view_audit_logs_all_users',
        ]);
    }

    public function canViewAuditLogsRoleOnly(): bool
    {
        return $this->hasPermission('view_audit_logs_role_only') && !$this->canViewAuditLogsAllUsers();
    }

    public function canViewAuditLogsAllUsers(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('view_audit_logs_all_users');
    }

    public function canViewLiveChat(): bool
    {
        return $this->hasPermission(['view_live_chat', 'send_whatsapp']);
    }

    public function canSendWhatsapp(): bool
    {
        return $this->hasPermission('send_whatsapp');
    }

    public function canViewImportUploads(): bool
    {
        return $this->hasPermission('import_clients');
    }

    public function canManageWhatsAppFlows(): bool
    {
        return $this->hasPermission('manage_whatsapp_flows');
    }

    public function canViewSecurityIncidents(): bool
    {
        return $this->hasPermission([
            'view_security_incidents',
            'manage_security_incidents',
        ]);
    }

    public function canCreateSecurityIncidents(): bool
    {
        return $this->canViewSecurityIncidents();
    }

    public function canManageSecurityIncidents(): bool
    {
        return $this->hasPermission('manage_security_incidents');
    }

    public function canViewComplianceConsole(): bool
    {
        return $this->hasPermission([
            'view_compliance_console',
            'manage_compliance_console',
        ]);
    }

    public function canManageComplianceConsole(): bool
    {
        return $this->hasPermission('manage_compliance_console');
    }

    public function canBypassExportApproval(): bool
    {
        return $this->hasPermission('bypass_export_approval');
    }

    public function resolvedBankId(): ?int
    {
        $ids = $this->resolvedBankIds();
        return !empty($ids) ? $ids[0] : null;
    }

    public function resolvedBankIds(): array
    {
        if ($this->relationLoaded('banks')) {
            $ids = $this->banks->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (!empty($ids)) {
                return array_values(array_unique($ids));
            }
        }

        $pivotIds = $this->banks()->pluck('banks.id')->map(fn ($id) => (int) $id)->all();
        if (!empty($pivotIds)) {
            return array_values(array_unique($pivotIds));
        }

        if ($this->bank_id) {
            return [(int) $this->bank_id];
        }

        return [];
    }

    public function canAccessAllBanks(): bool
    {
        return $this->isSuperAdmin() || $this->hasPermission('bypass_bank_scoping');
    }

    public function isPortfolioScoped(): bool
    {
        if ($this->canViewAllImportedClients()) {
            return false;
        }

        return $this->hasPermission('portfolio_scoped_only');
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function resolvedRoleCodes(): array
    {
        if ($this->relationLoaded('roles')) {
            $codes = $this->roles->pluck('code')->filter()->map(fn ($code) => (string) $code)->values()->all();
            if (!empty($codes)) {
                return array_values(array_unique($codes));
            }
        }

        $pivotCodes = $this->roles()->pluck('roles.code')->filter()->map(fn ($code) => (string) $code)->values()->all();
        if (!empty($pivotCodes)) {
            return array_values(array_unique($pivotCodes));
        }

        if (!empty($this->role)) {
            return [(string) $this->role];
        }

        return [];
    }

    public static function resolvePrimaryRoleFromCodes(array $codes): ?string
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
        foreach (self::ALL_ROLES as $preferred) {
            if (in_array($preferred, $codes, true)) {
                return $preferred;
            }
        }

        return $codes[0] ?? null;
    }

    public function resolvedDepartmentId(): ?int
    {
        $ids = $this->resolvedDepartmentIds();
        if (!empty($ids)) {
            return $ids[0];
        }

        if ($this->department_id) {
            return (int) $this->department_id;
        }

        if (!empty($this->department)) {
            return Department::query()
                ->where('name', $this->department)
                ->value('id');
        }

        return null;
    }

    public function resolvedDepartmentIds(): array
    {
        if ($this->relationLoaded('departments')) {
            $ids = $this->departments->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (!empty($ids)) {
                return array_values(array_unique($ids));
            }
        }

        $pivotIds = $this->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all();
        if (!empty($pivotIds)) {
            return array_values(array_unique($pivotIds));
        }

        if ($this->department_id) {
            return [(int) $this->department_id];
        }

        if (!empty($this->department)) {
            $departmentId = Department::query()
                ->where('name', $this->department)
                ->value('id');

            if ($departmentId) {
                return [(int) $departmentId];
            }
        }

        return [];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path) {
            if (config('filesystems.disks.public.driver') === 's3') {
                return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
            }
            return asset('storage/' . $this->avatar_path);
        }
        return null;
    }
}
