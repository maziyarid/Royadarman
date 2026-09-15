<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production and staging must not invent users. Organisational blog
        // starter articles attach to an existing owner when one is present.
        $this->call(CmsStarterContentSeeder::class);
    }
}
