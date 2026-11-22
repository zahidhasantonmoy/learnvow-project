<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** Spec §1.4 — the 8 categories from the prototype's CATS, verbatim. */
    public function run(): void
    {
        $categories = [
            ['name' => 'কবিতা', 'name_en' => 'Poetry', 'slug' => 'poetry', 'sort_order' => 1,
                'description' => 'কবিতার বই — ছন্দ, অনুভূতি আর ভাষার সৌন্দর্য।',
                'description_en' => 'Poetry books — rhythm, feeling and the beauty of language.'],
            ['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel', 'sort_order' => 2,
                'description' => 'উপন্যাস — ক্লাসিক থেকে সমকালীন, বড় গল্পের বই।',
                'description_en' => 'Novels — classic to contemporary, books of big stories.'],
            ['name' => 'দর্শন', 'name_en' => 'Philosophy', 'slug' => 'philosophy', 'sort_order' => 3,
                'description' => 'দর্শনের বই — জীবন, মন আর জগৎ নিয়ে গভীর চিন্তা।',
                'description_en' => 'Philosophy books — deep thinking about life, mind and the world.'],
            ['name' => 'দক্ষতা', 'name_en' => 'Skills', 'slug' => 'skills', 'sort_order' => 4,
                'description' => 'দক্ষতা উন্নয়নের বই — শেখা আর কাজে লাগানোর গাইড।',
                'description_en' => 'Skill-building books — practical guides to learn and apply.'],
            ['name' => 'ইতিহাস', 'name_en' => 'History', 'slug' => 'history', 'sort_order' => 5,
                'description' => 'ইতিহাসের বই — অতীতের গল্প আর শিক্ষা।',
                'description_en' => 'History books — stories and lessons from the past.'],
            ['name' => 'শিশু', 'name_en' => 'Children', 'slug' => 'children', 'sort_order' => 6,
                'description' => 'শিশুদের বই — ছোটদের জন্য গল্প আর শেখা।',
                'description_en' => 'Children’s books — stories and learning for young readers.'],
            ['name' => 'ইসলামিক', 'name_en' => 'Islamic', 'slug' => 'islamic', 'sort_order' => 7,
                'description' => 'ইসলামিক বই — দ্বীন, জীবন আর আত্মশুদ্ধির বই।',
                'description_en' => 'Islamic books — faith, life and self-purification.'],
            ['name' => 'বিজ্ঞান', 'name_en' => 'Science', 'slug' => 'science', 'sort_order' => 8,
                'description' => 'বিজ্ঞানের বই — জানা আর আবিষ্কারের জগৎ।',
                'description_en' => 'Science books — the world of knowledge and discovery.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
