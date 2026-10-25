<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Account auth for owners: register, log in, log out. Publishing a microsite
 * requires a verified account, so a site's owner is proven rather than just an
 * email someone typed.
 */
class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:owners,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $owner = Owner::query()->create([
            'name' => $data['name'] ?? null,
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
        ]);

        event(new Registered($owner));
        Auth::login($owner);

        return redirect()->route('verification.notice');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Those credentials do not match.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
