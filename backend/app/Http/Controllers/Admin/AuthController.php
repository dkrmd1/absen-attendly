<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();
        $storedHash = $user?->getAuthPassword();
        $expectedAlgorithm = match (config('hashing.driver')) {
            'bcrypt' => 'bcrypt',
            'argon' => 'argon2i',
            'argon2id' => 'argon2id',
            default => null,
        };
        $storedAlgorithm = $storedHash ? (Hash::info($storedHash)['algoName'] ?? null) : null;

        if (
            ! $user
            || ! $expectedAlgorithm
            || $storedAlgorithm !== $expectedAlgorithm
            || ! Hash::check($credentials['password'], $storedHash)
            || ! $user->canAccessAdminPortal()
        ) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi akun admin/HR/pimpinan tidak sesuai. Jika akun lama, reset kata sandi melalui terminal administrator.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $defaultRoute = $request->user()->isAdmin()
            ? route('admin.dashboard')
            : route('admin.leave-requests.index');

        return redirect()->intended($defaultRoute);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
