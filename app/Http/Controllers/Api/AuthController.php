<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\UserSessionTracker;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    use HasAuditLogging;

    private const MFA_CACHE_PREFIX = 'login_mfa:';
    private const PASSWORD_RESET_CACHE_PREFIX = 'login_password_reset:';
    private const FORGOT_PASSWORD_CACHE_PREFIX = 'forgot_password:';

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if ($user?->locked_until && $user->locked_until->isFuture()) {
            return response()->json([
                'message' => 'Your account is temporarily locked due to repeated failed login attempts. Please try again later.',
            ], 423);
        }

        // Rate limiting for login attempts
        $executed = RateLimiter::attempt(
            'login:' . $request->ip(),
            $perMinute = 5,
            function() {
                // This callback will be executed if rate limit is not exceeded
            }
        );

        if (!$executed) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again in a minute.',
            ], 429);
        }

        // Attempt authentication
        if (!Auth::attempt($credentials)) {
            RateLimiter::hit('login:' . $request->ip());

            if ($user) {
                $attempts = (int) $user->failed_login_attempts + 1;
                $updates = ['failed_login_attempts' => $attempts];

                if ($attempts >= 5) {
                    $updates['locked_until'] = now()->addMinutes(15);
                    $updates['failed_login_attempts'] = 0;
                }

                $user->forceFill($updates)->save();
            }
            
            // Audit log for failed attempt
            $this->audit(
                action: "Failed login attempt for {$credentials['email']}",
                module: 'Auth',
                meta: ['email' => $credentials['email']]
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (!$user->isActive()) {
            Auth::logout();
            $this->audit(
                action: "Inactive user login blocked ({$user->email})",
                module: 'Auth',
                meta: ['user_id' => $user->id]
            );

            return response()->json([
                'message' => 'Your account is inactive. Please contact an administrator.',
            ], 403);
        }
        
        // Clear rate limiter on successful login
        RateLimiter::clear('login:' . $request->ip());

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        if ($ipAllowlistResponse = $this->enforceAdminIpAllowlist($user, $request)) {
            Auth::logout();
            return $ipAllowlistResponse;
        }

        if ($this->passwordResetRequired($user)) {
            Auth::logout();
            return $this->startPasswordResetChallenge($user, $request);
        }

        if ($user->requiresLoginMfa()) {
            Auth::logout();
            return $this->startMfaChallenge($user, $request);
        }

        return $this->issueAuthenticatedResponse($user, $request, 'password');
    }

    public function verifyLoginMfa(Request $request)
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);

        $cacheKey = self::MFA_CACHE_PREFIX . $data['challenge_id'];
        $challenge = app(CacheRepository::class)->get($cacheKey);

        if (!$challenge) {
            throw ValidationException::withMessages([
                'code' => ['The verification code has expired. Please sign in again.'],
            ]);
        }

        if (
            !hash_equals((string) $challenge['code'], (string) $data['code'])
            || ($challenge['ip'] ?? null) !== $request->ip()
        ) {
            $this->audit(
                action: 'Failed MFA verification attempt',
                module: 'Auth',
                meta: ['challenge_id' => $data['challenge_id']]
            );

            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        $user = User::findOrFail($challenge['user_id']);
        app(CacheRepository::class)->forget($cacheKey);

        return $this->issueAuthenticatedResponse($user, $request, 'password+mfa');
    }

    public function resetLoginPassword(Request $request)
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $cacheKey = self::PASSWORD_RESET_CACHE_PREFIX . $data['challenge_id'];
        $challenge = app(CacheRepository::class)->get($cacheKey);

        if (!$challenge) {
            throw ValidationException::withMessages([
                'password' => ['The password reset request has expired. Please sign in again.'],
            ]);
        }

        if (($challenge['ip'] ?? null) !== $request->ip()) {
            throw ValidationException::withMessages([
                'password' => ['The password reset request is invalid for this device.'],
            ]);
        }

        $user = User::findOrFail($challenge['user_id']);
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        app(CacheRepository::class)->forget($cacheKey);

        $this->audit(
            action: "Password reset completed during login for {$user->email}",
            module: 'Auth',
            meta: ['user_id' => $user->id]
        );

        if ($user->requiresLoginMfa()) {
            return $this->startMfaChallenge($user, $request);
        }

        return $this->issueAuthenticatedResponse($user, $request, 'password_reset');
    }

    public function requestForgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->isActive()) {
            $challengeId = (string) Str::uuid();
            $code = (string) random_int(100000, 999999);

            app(CacheRepository::class)->put(
                self::FORGOT_PASSWORD_CACHE_PREFIX . $challengeId,
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'code' => $code,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                ],
                now()->addMinutes(15)
            );

            Mail::raw(
                "Your SRS DailyCRM password reset code is {$code}. It expires in 15 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)->subject('Your SRS DailyCRM password reset code');
                }
            );

            $this->audit(
                action: "Forgot password challenge created for {$user->email}",
                module: 'Auth',
                meta: ['user_id' => $user->id]
            );

            return response()->json([
                'challenge_id' => $challengeId,
                'masked_email' => $this->maskEmail($user->email),
                'message' => 'A password reset code has been sent to your email address.',
            ], 202);
        }

        return response()->json([
            'message' => 'If an account exists for that email address, a password reset code has been sent.',
        ], 202);
    }

    public function completeForgotPasswordReset(Request $request)
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $cacheKey = self::FORGOT_PASSWORD_CACHE_PREFIX . $data['challenge_id'];
        $challenge = app(CacheRepository::class)->get($cacheKey);

        if (!$challenge) {
            throw ValidationException::withMessages([
                'code' => ['The password reset code has expired. Please request a new one.'],
            ]);
        }

        if (
            !hash_equals((string) $challenge['code'], (string) $data['code'])
            || ($challenge['ip'] ?? null) !== $request->ip()
        ) {
            $this->audit(
                action: 'Failed forgot password verification attempt',
                module: 'Auth',
                meta: ['challenge_id' => $data['challenge_id']]
            );

            throw ValidationException::withMessages([
                'code' => ['The password reset code is invalid.'],
            ]);
        }

        $user = User::findOrFail($challenge['user_id']);
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        app(CacheRepository::class)->forget($cacheKey);

        $this->audit(
            action: "Forgot password completed for {$user->email}",
            module: 'Auth',
            meta: ['user_id' => $user->id]
        );

        return response()->json([
            'message' => 'Your password has been reset successfully. You can now sign in.',
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'department_id'         => ['nullable', 'integer', 'exists:departments,id'],
            'role'                  => ['sometimes', \Illuminate\Validation\Rule::in(User::ALL_ROLES)],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'department_id' => $data['department_id'] ?? null,
            'role'          => $data['role'] ?? User::ROLE_AGENT,
            'status'        => 'Active',                 // default status
        ]);

        if ($role = Role::query()->where('code', $user->role)->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        // Create Sanctum token for the new user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Audit log
        $this->audit(
            action: "New user registered ({$user->email})",
            module: 'Auth',
            meta: ['user_id' => $user->id]
        );

        return response()->json([
            'user' => $user->fresh()->load(['roles:id,code,name,whatsapp_daily_limit']),
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke the current token
            if ($token = $user->currentAccessToken()) {
                if ($token instanceof TransientToken) {
                    Auth::guard('web')->logout();
                } else {
                    UserSessionTracker::closeByToken($token->id, 'logout');
                    $token->delete();
                }
            }
            
            // Audit log
            $this->audit(
                action: "User logged out ({$user->email})",
                module: 'Auth'
            );
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['department', 'departments:id,name', 'roles:id,code,name,whatsapp_daily_limit,watermark_enabled']);
        return response()->json($user);
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($name === '' || $domain === '') {
            return $email;
        }

        $visible = substr($name, 0, min(2, strlen($name)));
        return $visible . str_repeat('*', max(strlen($name) - strlen($visible), 2)) . '@' . $domain;
    }

    protected function passwordResetRequired(User $user): bool
    {
        if ((bool) $user->password_reset_required) {
            return true;
        }

        $settings = SystemSetting::query()->first();
        $maxAgeDays = (int) ($settings?->password_max_age_days ?: env('PASSWORD_MAX_AGE_DAYS', 90));
        if ($maxAgeDays <= 0) {
            return false;
        }

        if (!$user->password_changed_at) {
            return true;
        }

        return $user->password_changed_at->lt(now()->subDays($maxAgeDays));
    }

    protected function startPasswordResetChallenge(User $user, Request $request)
    {
        $challengeId = (string) Str::uuid();

        app(CacheRepository::class)->put(
            self::PASSWORD_RESET_CACHE_PREFIX . $challengeId,
            [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
            now()->addMinutes(15)
        );

        $this->audit(
            action: "Password reset challenge created for {$user->email}",
            module: 'Auth',
            meta: ['user_id' => $user->id]
        );

        return response()->json([
            'password_reset_required' => true,
            'challenge_id' => $challengeId,
            'message' => 'Your password must be reset before you can continue.',
        ], 428);
    }

    protected function startMfaChallenge(User $user, Request $request)
    {
        $challengeId = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);

        app(CacheRepository::class)->put(
            self::MFA_CACHE_PREFIX . $challengeId,
            [
                'user_id' => $user->id,
                'code' => $code,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
            now()->addMinutes(10)
        );

        Mail::raw(
            "Your Strauss DailyCRM verification code is {$code}. It expires in 10 minutes.",
            function ($message) use ($user) {
                $message->to($user->email)->subject('Your Strauss DailyCRM verification code');
            }
        );

        $this->audit(
            action: "MFA challenge created for {$user->email}",
            module: 'Auth',
            meta: ['user_id' => $user->id]
        );

        return response()->json([
            'mfa_required' => true,
            'challenge_id' => $challengeId,
            'masked_email' => $this->maskEmail($user->email),
            'message' => 'A verification code has been sent to your email address.',
        ], 202);
    }

    protected function issueAuthenticatedResponse(User $user, Request $request, string $method)
    {
        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact an administrator.',
            ], 403);
        }

        if ($ipAllowlistResponse = $this->enforceAdminIpAllowlist($user, $request)) {
            return $ipAllowlistResponse;
        }

        if ($this->passwordResetRequired($user)) {
            return $this->startPasswordResetChallenge($user, $request);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ])->save();

        $newToken = $user->createToken('auth_token');
        UserSessionTracker::start($user, $request, $newToken->accessToken->id, $method);

        $this->audit(
            action: "User logged in ({$user->email})",
            module: 'Auth',
            meta: ['user_id' => $user->id, 'method' => $method]
        );

        return response()->json([
            'user' => $user->fresh()->load(['bank:id,name', 'departments:id,name', 'roles:id,code,name,whatsapp_daily_limit,watermark_enabled']),
            'token' => $newToken->plainTextToken,
        ]);
    }

    protected function enforceAdminIpAllowlist(User $user, Request $request)
    {
        if (!$user->requiresAdminIpAllowlist()) {
            return null;
        }

        $settings = SystemSetting::query()->first();
        $allowlist = $settings?->adminIpAllowlistEntries()
            ?: collect(preg_split('/[\r\n,;]+/', (string) env('ADMIN_IP_ALLOWLIST', '')))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all();

        if (empty($allowlist)) {
            return null;
        }

        foreach ($allowlist as $pattern) {
            if (\Symfony\Component\HttpFoundation\IpUtils::checkIp((string) $request->ip(), $pattern)) {
                return null;
            }
        }

        $this->audit(
            action: "Admin IP allowlist blocked login for {$user->email}",
            module: 'Auth',
            meta: ['user_id' => $user->id, 'ip' => $request->ip()]
        );

        return response()->json([
            'message' => 'Your current IP address is not allowed to access the admin portal.',
        ], 403);
    }
}
