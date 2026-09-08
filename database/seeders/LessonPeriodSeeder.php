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
                'period_number' => 0,
                'name' => 'Kegiatan Pagi',
                'start_time' => '07:00',
                'end_time' => '07:30',
                'is_break' => false,
            ],
            [
                'period_number' => 1,
                'name' => 'Jam ke-1',
                'start_time' => '07:30',
                'end_time' => '08:05',
                'is_break' => false,
            ],
            [
                'period_number' => 2,
                'name' => 'Jam ke-2',
                'start_time' => '08:05',
                'end_time' => '08:40',
                'is_break' => false,
            ],
            [
                'period_number' => 3,
                'name' => 'Jam ke-3',
                'start_time' => '08:40',
                'end_time' => '09:15',
                'is_break' => false,
            ],
            [
                'period_number' => 4,
                'name' => 'Jam ke-4',
                'start_time' => '09:15',
                'end_time' => '09:50',
                'is_break' => false,
            ],
            [
                'period_number' => 5,
                'name' => 'Istirahat 1',
                'start_time' => '09:50',
                'end_time' => '10:25',
                'is_break' => true,
            ],
            [
                'period_number' => 6,
                'name' => 'Jam ke-5',
                'start_time' => '10:25',
                'end_time' => '11:00',
                'is_break' => false,
            ],
            [
                'period_number' => 7,
                'name' => 'Jam ke-6',
                'start_time' => '11:00',
                'end_time' => '11:35',
                'is_break' => false,
            ],
            [
                'period_number' => 8,
                'name' => 'Jam ke-7',
                'start_time' => '11:35',
                'end_time' => '12:10',
                'is_break' => false,
            ],
            [
                'period_number' => 9,
                'name' => 'Istirahat 2',
                'start_time' => '12:10',
                'end_time' => '12:45',
                'is_break' => true,
            ],
            [
                'period_number' => 10,
                'name' => 'Jam ke-8',
                'start_time' => '12:45',
                'end_time' => '13:20',
                'is_break' => false,
            ],
            [
                'period_number' => 11,
                'name' => 'Jam ke-9',
                'start_time' => '13:20',
                'end_time' => '13:55',
                'is_break' => false,
            ],
        ];

        foreach ($periods as $period) {
            LessonPeriod::updateOrCreate(
                [
                    'period_number' => $period['period_number'],
                ],
                [
                    'name' => $period['name'],
                    'start_time' => $period['start_time'],
                    'end_time' => $period['end_time'],
                    'is_break' => $period['is_break'],
                    'is_active' => true,
                ]
            );
        }
    }
}
