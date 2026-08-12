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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'role_codes',
        'role_names',
        'avatar_url',
    ];

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
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager()
    {
        return in_array($this->role, [
            self::ROLE_MANAGER,
            self::ROLE_CALL_CENTRE_MANAGER,
            self::ROLE_TEAM_LEADER,
        ], true);
    }

    public function isStaff()
    {
        return in_array($this->role, [self::ROLE_AGENT, self::ROLE_STAFF_LEGACY], true);
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

    public function canManageSystemSettings(): bool
    {
        return $this->hasRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public function canManageUsersAndDepartments(): bool
    {
        return $this->hasRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public function canManageOperationalData(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_CALL_CENTRE_MANAGER,
            self::ROLE_TEAM_LEADER,
            self::ROLE_AGENT,
            self::ROLE_STAFF_LEGACY,
        ]);
    }

    public function canViewOperationalData(): bool
    {
        return $this->canManageOperationalData() || $this->canReviewAuditData();
    }

    public function canReviewAuditData(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_AUDITOR,
            self::ROLE_COMPLIANCE_OFFICER,
            self::ROLE_READ_ONLY_REVIEWER,
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
        return $this->canManageSystemSettings()
            || $this->isManager()
            || $this->canReviewAuditData();
    }

    public function requiresAdminIpAllowlist(): bool
    {
        return $this->hasRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public function canRequestSensitiveExports(): bool
    {
        return $this->canViewOperationalData() || $this->canReviewAuditData();
    }

    public function canApproveExportRequests(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_COMPLIANCE_OFFICER,
        ]);
    }

    public function canViewSecurityIncidents(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_CALL_CENTRE_MANAGER,
            self::ROLE_TEAM_LEADER,
            self::ROLE_AUDITOR,
            self::ROLE_COMPLIANCE_OFFICER,
            self::ROLE_READ_ONLY_REVIEWER,
        ]);
    }

    public function canCreateSecurityIncidents(): bool
    {
        return $this->canViewSecurityIncidents();
    }

    public function canManageSecurityIncidents(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_COMPLIANCE_OFFICER,
        ]);
    }

    public function canViewComplianceConsole(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_CALL_CENTRE_MANAGER,
            self::ROLE_TEAM_LEADER,
            self::ROLE_AUDITOR,
            self::ROLE_COMPLIANCE_OFFICER,
            self::ROLE_READ_ONLY_REVIEWER,
        ]);
    }

    public function canManageComplianceConsole(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_COMPLIANCE_OFFICER,
        ]);
    }

    public function canBypassExportApproval(): bool
    {
        return $this->hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
        ]);
    }

    public function resolvedBankId(): ?int
    {
        return $this->bank_id ? (int) $this->bank_id : null;
    }

    public function canAccessAllBanks(): bool
    {
        return $this->canManageSystemSettings();
    }

    public function isPortfolioScoped(): bool
    {
        return $this->hasRole([
            self::ROLE_AGENT,
            self::ROLE_STAFF_LEGACY,
        ]);
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
