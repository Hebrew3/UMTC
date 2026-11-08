<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrfToken()
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'agent_ids' => $user->isAdmin() ? [] : $user->agents()->pluck('agents.id')->all(),
        ];
    }
}
