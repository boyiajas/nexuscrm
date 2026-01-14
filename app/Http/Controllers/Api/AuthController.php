<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use HasAuditLogging;

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

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
        
        // Clear rate limiter on successful login
        RateLimiter::clear('login:' . $request->ip());

        // Update last login
        $user->last_login_at = now();
        $user->save();

        // Revoke all existing tokens (optional - for single device login)
        // $user->tokens()->delete();

        // Create new Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Audit log for successful login
        $this->audit(
            action: "User logged in ({$user->email})",
            module: 'Auth'
        );

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
            'department_id'         => ['nullable', 'integer', 'exists:departments,id'],
            'role'                  => ['sometimes', 'in:SUPER_ADMIN,MANAGER,STAFF'],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'department_id' => $data['department_id'] ?? null,
            'role'          => $data['role'] ?? 'STAFF',  // default role
            'status'        => 'Active',                 // default status
        ]);

        // Create Sanctum token for the new user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Audit log
        $this->audit(
            action: "New user registered ({$user->email})",
            module: 'Auth',
            meta: ['user_id' => $user->id]
        );

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke the current token
            $user->currentAccessToken()->delete();
            
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
        $user = $request->user()->load('department');
        return response()->json($user);
    }
}