<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Демо-владелец для локального прогона и записи демо: owner@docsign.test / password.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@docsign.test',
        ]);
    }
}
