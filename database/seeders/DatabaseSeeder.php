<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (Phase 1 order — spec §6).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            AuthorSeeder::class,
            BookSeeder::class,
            // DemoUserSeeder LAST: it links the demo reader's free-book
            // entitlements and the demo author to the seeded books/authors.
            DemoUserSeeder::class,
        ]);
    }
}
