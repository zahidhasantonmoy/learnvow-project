<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Self-hosted avatar: user initials on a solid background as an SVG
 * data URI. No external request — Filament's default UiAvatarsProvider
 * calls ui-avatars.com, which fails offline and logs console errors
 * on every admin page load.
 */
class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) ($record->name ?? $record->phone ?? '?'));

        $initials = collect(preg_split('/\s+/u', $name))
            ->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');

        $initials = $initials !== '' ? mb_strtoupper($initials) : '?';

        // Deterministic dark background from the name hash (LearnVow palette).
        $palette = ['17573a', '0d3a26', '2e7d54', '7a4a21', '6d2137', '33415c'];
        $bg = $palette[abs(crc32($name)) % count($palette)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="#'.$bg.'"/><text x="32" y="41" font-family="sans-serif" font-size="26" font-weight="bold" fill="#ffffff" text-anchor="middle">'.e($initials).'</text></svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
