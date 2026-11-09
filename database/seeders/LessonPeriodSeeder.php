<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LessonPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(KbmTimeSeeder::class);
    }
}
