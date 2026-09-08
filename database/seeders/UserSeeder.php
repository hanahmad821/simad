<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@madrasah.local'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'role' => 'admin',
                'gtk_id' => null,
            ]
        );
    }
}
