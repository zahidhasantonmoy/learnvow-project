<?php

namespace App\Services;

use App\Exceptions\AiNotConfiguredException;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI seam ONLY (Phase 4 spec §7.1). No external calls, no API keys, no
 * provider — the provider is undecided (spec A13). Every method either
 * reports disabled or throws AiNotConfiguredException; when a provider is
 * chosen later, these signatures stay and the bodies get implemented.
 */
class AiService
{
    public function enabled(): bool
    {
        return $this->setting('ai.enabled', '0') === '1';
    }

    public function provider(): ?string
    {
        $provider = $this->setting('ai.provider', '');

        return $provider === null || $provider === '' ? null : $provider;
    }

    /** Book summary (from the description + sample chapters — future). */
    public function summarizeBook(Book $book): ?string
    {
        throw new AiNotConfiguredException('AI is not configured yet.');
    }

    /** AI recommendations (future; Phase 4 forYou is rule-based — spec §4.3). */
    public function recommendFor(User $user): array
    {
        throw new AiNotConfiguredException('AI is not configured yet.');
    }

    /** AI narration script (future; Phase 4 TTS is Web Speech API — Phase 2). */
    public function narrationScript(Book $book): ?string
    {
        throw new AiNotConfiguredException('AI is not configured yet.');
    }

    protected function setting(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }
}
