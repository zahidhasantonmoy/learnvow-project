<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * SEO data shared by all auth pages: auth pages are never indexed.
     */
    protected function seo(string $titleKey, string $descriptionKey, string $path): array
    {
        $locale = app()->getLocale();

        return [
            'title' => __($titleKey),
            'description' => __($descriptionKey),
            'canonical' => url('/'.$locale.$path),
            'noindex' => true,
            'alternate' => [
                'bn' => url('/bn'.$path),
                'en' => url('/en'.$path),
            ],
        ];
    }

    public function showLogin(): View
    {
        return view('auth.login', ['seo' => $this->seo('auth.login_title', 'auth.login_description', '/login')]);
    }

    public function showRegister(): View
    {
        return view('auth.register', ['seo' => $this->seo('auth.register_title', 'auth.register_description', '/register')]);
    }

    public function showOtp(): View
    {
        return view('auth.otp', ['seo' => $this->seo('auth.otp_title', 'auth.otp_description', '/otp')]);
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password', ['seo' => $this->seo('auth.forgot_title', 'auth.forgot_description', '/forgot-password')]);
    }

    public function showResetPassword(): View
    {
        return view('auth.reset-password', ['seo' => $this->seo('auth.reset_title', 'auth.reset_description', '/reset-password')]);
    }

    public function logout(Request $request): RedirectResponse
    {
        // Logout is idempotent: a guest posting here is simply sent to the
        // localized login page. (The auth middleware cannot do this itself:
        // route('login') needs the {locale} parameter, so a guest hitting an
        // auth-guarded route gets a 500 until the coordinator configures a
        // locale-aware guest redirect in bootstrap/app.php.)
        if (! Auth::check()) {
            return redirect()->route('login', ['locale' => app()->getLocale()]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/'.app()->getLocale());
    }
}
