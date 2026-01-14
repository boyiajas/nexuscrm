<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return User::orderBy('name')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:6'],
            'role'       => ['required', Rule::in(['SUPER_ADMIN', 'MANAGER', 'STAFF'])],
            'department' => ['nullable', 'string', 'max:255'],
            'status'     => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'email'      => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'   => ['nullable', 'string', 'min:6'],
            'role'       => ['sometimes', Rule::in(['SUPER_ADMIN', 'MANAGER', 'STAFF'])],
            'department' => ['nullable', 'string', 'max:255'],
            'status'     => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }

        $user->delete();

        return response()->noContent();
    }
}
