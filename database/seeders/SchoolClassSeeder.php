<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            [
                'name' => 'X-A',
                'grade' => 10,
                'major' => null,
                'capacity' => 36,
            ],
            [
                'name' => 'X-B',
                'grade' => 10,
                'major' => null,
                'capacity' => 36,
            ],
            [
                'name' => 'XI-A',
                'grade' => 11,
                'major' => null,
                'capacity' => 36,
            ],
            [
                'name' => 'XI-B',
                'grade' => 11,
                'major' => null,
                'capacity' => 36,
            ],
            [
                'name' => 'XII-A',
                'grade' => 12,
                'major' => null,
                'capacity' => 36,
            ],
            [
                'name' => 'XII-B',
                'grade' => 12,
                'major' => null,
                'capacity' => 36,
            ],
        ];

        foreach ($classes as $class) {
            SchoolClass::create([
                ...$class,
                'is_active' => true,
            ]);
        }
    }
}
