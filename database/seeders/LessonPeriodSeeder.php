<?php

namespace Database\Seeders;

use App\Models\LessonPeriod;
use Illuminate\Database\Seeder;

class LessonPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $periods = [
            [
                'period_number' => 1,
                'name' => 'Jam ke-1',
                'start_time' => '07:00',
                'end_time' => '07:45',
                'is_break' => false,
            ],
            [
                'period_number' => 2,
                'name' => 'Jam ke-2',
                'start_time' => '07:45',
                'end_time' => '08:30',
                'is_break' => false,
            ],
            [
                'period_number' => 3,
                'name' => 'Jam ke-3',
                'start_time' => '08:30',
                'end_time' => '09:15',
                'is_break' => false,
            ],
            [
                'period_number' => 4,
                'name' => 'Istirahat',
                'start_time' => '09:15',
                'end_time' => '09:30',
                'is_break' => true,
            ],
            [
                'period_number' => 5,
                'name' => 'Jam ke-4',
                'start_time' => '09:30',
                'end_time' => '10:15',
                'is_break' => false,
            ],
            [
                'period_number' => 6,
                'name' => 'Jam ke-5',
                'start_time' => '10:15',
                'end_time' => '11:00',
                'is_break' => false,
            ],
            [
                'period_number' => 7,
                'name' => 'Jam ke-6',
                'start_time' => '11:00',
                'end_time' => '11:45',
                'is_break' => false,
            ],
            [
                'period_number' => 8,
                'name' => 'Jam ke-7',
                'start_time' => '11:45',
                'end_time' => '12:30',
                'is_break' => false,
            ],
        ];

        foreach ($periods as $period) {
            LessonPeriod::create([
                ...$period,
                'is_active' => true,
            ]);
        }
    }
}
