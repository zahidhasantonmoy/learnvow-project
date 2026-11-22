<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Header language toggle (spec §5): sets the lv_locale cookie, saves the
 * choice on the logged-in user's account, and reloads the same page in
 * the new locale.
 *
 * Note: the header currently renders the coordinator's <x-language-switcher />
 * Blade component (plain links); this Livewire component is the interactive
 * equivalent for pages that mount it.
 */
class LanguageSwitcher extends Component
{
    public function switchTo(string $locale)
    {
        if (! in_array($locale, ['bn', 'en'], true)) {
            return;
        }

        if (Auth::check()) {
            Auth::user()->forceFill(['locale' => $locale])->save();
        }

        // The page that rendered this component is the "previous" URL
        // (StartSession stores it; Livewire update requests do not change
        // it). Fall back to the site root.
        $previous = url()->previous('/');
        $path = parse_url($previous, PHP_URL_PATH) ?? '/';
        $segments = array_values(array_filter(explode('/', $path)));

        if (! empty($segments[0]) && in_array($segments[0], ['bn', 'en'], true)) {
            $segments[0] = $locale;
        } else {
            array_unshift($segments, $locale);
        }

        $url = '/'.implode('/', $segments);

        if ($query = request()->getQueryString()) {
            $url .= '?'.$query;
        }

        // Queue the cookie (rather than attaching it to the redirect) so it
        // is set on the Livewire update response as well as on plain
        // redirects. SetLocale also re-queues it on the next page load.
        cookie()->queue(cookie('lv_locale', $locale, 60 * 24 * 365));

        return redirect()->to($url);
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
