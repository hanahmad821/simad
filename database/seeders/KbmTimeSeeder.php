<?php

namespace Database\Seeders;

use App\Models\KbmDayPattern;
use App\Services\KbmTimeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KbmTimeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $service = app(KbmTimeService::class);
            foreach (array_keys(KbmTimeService::DAYS) as $day) {
                $pattern = KbmDayPattern::firstOrCreate(['day_of_week' => $day], $service->defaults($day));
                $service->syncSlots($day, [
                    'morning_start' => substr($pattern->morning_start, 0, 5), 'morning_end' => substr($pattern->morning_end, 0, 5),
                    'teaching_periods' => $pattern->teaching_periods, 'breaks' => $pattern->breaks,
                ]);
            }
        });
    }
}
