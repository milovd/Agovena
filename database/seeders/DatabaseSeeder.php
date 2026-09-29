<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Keep normal application seeding free of demo and test identities.
     *
     * Official demo data is loaded only by `agovena:seed-demo`.
     */
    public function run(): void
    {
        // Intentionally empty.
    }
}
