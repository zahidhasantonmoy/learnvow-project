<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * Phase 4 §13.2 — the 8 badge definitions (§4.5). Definitions only:
 * no user_badges rows are ever seeded (§13.3).
 */
class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'slug' => 'first_book', 'name' => 'প্রথম বই', 'name_en' => 'First Book',
                'description' => 'প্রথম বইটি শেষ করলে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you finish your first book.',
                'icon' => 'book-open', 'rule_key' => 'first_finish',
            ],
            [
                'slug' => 'books_10', 'name' => '১০টি বই', 'name_en' => '10 Books',
                'description' => '১০টি বই শেষ করলে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you finish 10 books.',
                'icon' => 'books', 'rule_key' => 'books_10',
            ],
            [
                'slug' => 'books_50', 'name' => '৫০টি বই', 'name_en' => '50 Books',
                'description' => '৫০টি বই শেষ করলে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you finish 50 books.',
                'icon' => 'library', 'rule_key' => 'books_50',
            ],
            [
                'slug' => 'streak_7', 'name' => 'টানা ৭ দিন', 'name_en' => '7-Day Streak',
                'description' => 'টানা ৭ দিন পড়লে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you read 7 days in a row.',
                'icon' => 'flame', 'rule_key' => 'streak_7',
            ],
            [
                'slug' => 'streak_30', 'name' => 'টানা ৩০ দিন', 'name_en' => '30-Day Streak',
                'description' => 'টানা ৩০ দিন পড়লে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you read 30 days in a row.',
                'icon' => 'flame', 'rule_key' => 'streak_30',
            ],
            [
                'slug' => 'night_owl', 'name' => 'রাতের পেঁচা', 'name_en' => 'Night Owl',
                'description' => 'রাত ১২টা থেকে ভোর ৪টার মধ্যে বই শেষ করলে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you finish a book between midnight and 4 a.m.',
                'icon' => 'moon', 'rule_key' => 'night_owl',
            ],
            [
                'slug' => 'club_joiner', 'name' => 'ক্লাবে যোগদানকারী', 'name_en' => 'Book Club Joiner',
                'description' => 'প্রথম ক্লাবে যোগ দিলে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you join your first club.',
                'icon' => 'users', 'rule_key' => 'club_joiner',
            ],
            [
                'slug' => 'goal_completed', 'name' => 'লক্ষ্য পূরণ', 'name_en' => 'Goal Completed',
                'description' => 'পড়ার লক্ষ্য পূর্ণ করলে এই ব্যাজ পাওয়া যায়।',
                'description_en' => 'You get this badge when you complete a reading goal.',
                'icon' => 'trophy', 'rule_key' => 'goal_completed',
            ],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
