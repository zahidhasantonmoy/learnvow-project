<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Demo users (spec §2.3). Phones 01700000000–01700000003, password `password`.
 * EXACTLY ONE super-admin. Real phone/password must be set on first login (A4).
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'LearnVow Owner',
                'phone' => '01700000000',
                'roles' => ['super-admin'],
            ],
            [
                'name' => 'Demo Admin',
                'phone' => '01700000001',
                'roles' => ['admin'],
            ],
            [
                'name' => 'Demo Reader',
                'phone' => '01700000002',
                'roles' => ['reader'],
            ],
            [
                'name' => 'Demo Author',
                'phone' => '01700000003',
                'roles' => ['reader', 'author'],
            ],
        ];

        $created = [];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'email' => null,
                    'password' => Hash::make('password'),
                    'locale' => 'bn',
                    'country' => 'BD',
                    'status' => 'active',
                ]
            );

            $user->syncRoles($data['roles']);
            $created[$data['phone']] = $user;
        }

        // Link the demo author to the Commissioned Series author row, if
        // Builder B's authors table + row exist already.
        if (Schema::hasTable('authors') && isset($created['01700000003'])) {
            $authorId = DB::table('authors')
                ->whereIn('slug', ['learnvow-commissioned-series', 'learnvow-commissioned'])
                ->orWhere('name', 'LearnVow Commissioned Series')
                ->value('id');

            if ($authorId) {
                DB::table('authors')
                    ->where('id', $authorId)
                    ->whereNull('user_id')
                    ->update(['user_id' => $created['01700000003']->id]);
            }
        }

        // Free-book entitlements for the demo reader (spec §1.8) — only if
        // Builder B's user_books + books tables exist already.
        if (Schema::hasTable('user_books') && Schema::hasTable('books') && isset($created['01700000002'])) {
            $freeBookIds = DB::table('books')->where('is_free', true)->pluck('id');

            foreach ($freeBookIds as $bookId) {
                DB::table('user_books')->updateOrInsert(
                    ['user_id' => $created['01700000002']->id, 'book_id' => $bookId],
                    ['source' => 'free', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
