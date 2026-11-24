<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Serial;
use App\Models\SerialEpisode;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Phase 4 §4.7 — ONE demo serial so the detail screen is verifiable.
 * Owned by the LearnVow Commissioned Series author (the demo author
 * user's Author row). Two episodes: one published, one upcoming.
 * Frozen figures (Prophet etc.) are untouched.
 */
class SerialSeeder extends Seeder
{
    public function run(): void
    {
        $author = Author::where('slug', 'learnvow-commissioned-series')->firstOrFail();

        $superAdmin = User::where('phone', '01700000000')->first();

        $serial = Serial::updateOrCreate(
            ['title' => 'ধারাবাহিক: প্রথম গল্প'],
            [
                'author_id' => $author->id,
                'title_en' => 'Serial: First Story',
                'description' => 'LearnVow-এর কমিশনড ধারাবাহিক — পর্ব ধরে ধরে প্রকাশিত গল্প।',
                'status' => 'ongoing',
                'is_free' => true,
                'is_subscription_included' => true,
                'approved_at' => now(),
                'approved_by' => $superAdmin?->id,
            ]
        );

        SerialEpisode::updateOrCreate(
            ['serial_id' => $serial->id, 'episode_no' => 1],
            [
                'title' => 'প্রথম পর্ব',
                'content' => 'গল্পের শুরু — ধারাবাহিকের প্রথম পর্ব।',
                'published_at' => now()->subDay(),
                'is_free' => true,
            ]
        );

        SerialEpisode::updateOrCreate(
            ['serial_id' => $serial->id, 'episode_no' => 2],
            [
                'title' => 'দ্বিতীয় পর্ব',
                'content' => 'গল্প চলছে — দ্বিতীয় পর্ব শীঘ্রই আসছে।',
                'published_at' => null,
                'is_free' => true,
            ]
        );
    }
}
