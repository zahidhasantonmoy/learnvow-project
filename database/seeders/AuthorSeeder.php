<?php

namespace Database\Seeders;

use App\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    /** Spec §2.2 — 7 authors, all user_id NULL, status approved. */
    public function run(): void
    {
        $authors = [
            [
                'name' => 'Rabindranath Tagore', 'slug' => 'rabindranath-tagore',
                'bio' => 'রবীন্দ্রনাথ ঠাকুর (১৮৬১–১৯৪১) — বাংলা সাহিত্যের শ্রেষ্ঠ কবি ও ঔপন্যাসিক, নোবেল পুরস্কার বিজয়ী।',
                'bio_en' => 'Rabindranath Tagore (1861–1941) — Bengal’s greatest poet and novelist, Nobel laureate in Literature (1913).',
            ],
            [
                'name' => 'মানিক বন্দ্যোপাধ্যায়', 'slug' => 'manik-bandopadhyay',
                'bio' => 'মানিক বন্দ্যোপাধ্যায় (১৯০৮–১৯৫৬) — বাংলা সাহিত্যের অন্যতম শ্রেষ্ঠ ঔপন্যাসিক, পদ্মা নদীর মাঝির লেখক।',
                'bio_en' => 'Manik Bandopadhyay (1908–1956) — one of Bengal’s greatest novelists, author of Padma Nadir Majhi.',
            ],
            [
                'name' => 'Kahlil Gibran', 'slug' => 'kahlil-gibran',
                'bio' => 'খলিল জিবরান (১৮৮৩–১৯৩১) — লেবানিজ-আমেরিকান কবি ও দার্শনিক, দ্য প্রফেটের লেখক।',
                'bio_en' => 'Kahlil Gibran (1883–1931) — Lebanese-American poet and philosopher, author of The Prophet.',
            ],
            [
                'name' => 'শরৎচন্দ্র চট্টোপাধ্যায়', 'slug' => 'saratchandra-chattopadhyay',
                'bio' => 'শরৎচন্দ্র চট্টোপাধ্যায় (১৮৭৬–১৯৩৮) — বাংলা সাহিত্যের জনপ্রিয়তম ঔপন্যাসিক, দেবদাসের লেখক।',
                'bio_en' => 'Sarat Chandra Chattopadhyay (1876–1938) — Bengal’s most beloved novelist, author of Devdas.',
            ],
            [
                'name' => 'Jane Austen', 'slug' => 'jane-austen',
                'bio' => 'জেন অস্টেন (১৭৭৫–১৮১৭) — ইংরেজি সাহিত্যের শ্রেষ্ঠ ঔপন্যাসিকদের একজন, প্রাইড অ্যান্ড প্রেজুডিসের লেখক।',
                'bio_en' => 'Jane Austen (1775–1817) — one of English literature’s greatest novelists, author of Pride and Prejudice.',
            ],
            [
                'name' => 'Marcus Aurelius', 'slug' => 'marcus-aurelius',
                'bio' => 'মার্কাস অরেলিয়াস (১২১–১৮০) — রোমান সম্রাট ও স্টোয়িক দার্শনিক, মেডিটেশনসের লেখক।',
                'bio_en' => 'Marcus Aurelius (121–180) — Roman emperor and Stoic philosopher, author of Meditations.',
            ],
            [
                'name' => 'LearnVow Commissioned Series', 'slug' => 'learnvow-commissioned-series', 'type' => 'publisher',
                'bio' => 'লার্নভো কমিশনড সিরিজ — LearnVow-এর নিজস্ব কমিশনে লেখা বইয়ের সিরিজ।',
                'bio_en' => 'LearnVow Commissioned Series — books written on commission for LearnVow.',
            ],
        ];

        foreach ($authors as $author) {
            Author::updateOrCreate(
                ['slug' => $author['slug']],
                array_merge(['user_id' => null, 'status' => 'approved', 'type' => 'author'], $author)
            );
        }
    }
}
