<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Profile pages (coordinator-wired: views by Builder A, routes left
 * unwired by A/B by design). All methods take $locale first — Laravel
 * passes {locale} positionally (Builder B finding).
 *
 * Note: self-deletion is NOT governed by UserPolicy::delete (that policy
 * is for an admin deleting another user: super-admin only). A user may
 * delete their own account after password confirmation; the Super Admin
 * account can never be deleted (roles doc).
 */
class ProfileController extends Controller
{
    public function show(string $locale): View
    {
        return view('profile.show', ['seo' => $this->seo(__('auth.profile_title'))]);
    }

    public function edit(string $locale): View
    {
        return view('profile.edit', ['seo' => $this->seo(__('auth.edit_profile_title'))]);
    }

    public function update(string $locale, Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'locale' => ['required', 'in:bn,en'],
            'country' => ['required', 'string', 'size:2'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'locale' => $validated['locale'],
            'country' => strtoupper($validated['country']),
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('profile.show', ['locale' => $validated['locale']])
            ->with('status', __('auth.profile_updated'));
    }

    public function privacy(string $locale): View
    {
        return view('profile.privacy', ['seo' => $this->seo(__('auth.privacy_title'))]);
    }

    public function delete(string $locale): View
    {
        return view('profile.delete', ['seo' => $this->seo(__('auth.delete_title'))]);
    }

    public function destroy(string $locale, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return redirect()
                ->route('profile.show', ['locale' => $locale])
                ->with('status', __('auth.delete_super_admin_refused'));
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home', ['locale' => $locale])
            ->with('status', __('auth.account_deleted'));
    }

    private function seo(string $title): array
    {
        return ['title' => $title, 'noindex' => true];
    }
}
