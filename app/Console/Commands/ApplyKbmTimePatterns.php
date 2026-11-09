<?php

namespace App\Console\Commands;

use App\Models\LessonPeriod;
use App\Models\Schedule;
use Database\Seeders\KbmTimeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplyKbmTimePatterns extends Command
{
    protected $signature = 'kbm:apply-time-patterns {--reset-test-data : Hapus presensi siswa, jurnal mengajar, pengecualian KBM, dan JP jadwal yang tidak sesuai pola baru}';

    protected $description = 'Terapkan pola waktu harian 35 menit per JP dan konversi jadwal dari jam global';

    public function handle(): int
    {
        $reset = (bool) $this->option('reset-test-data');
        $counts = DB::transaction(function () use ($reset) {
            $counts = ['mapped' => 0, 'removed' => 0, 'attendance' => 0, 'journals' => 0, 'exceptions' => 0];
            if ($reset) {
                $counts['attendance'] = DB::table('student_attendances')->delete();
                if (Schema::hasTable('gtk_work_journals')) {
                    $counts['journals'] = DB::table('gtk_work_journals')->where('kind', 'teaching')->delete();
                }
                if (Schema::hasTable('gtk_kbm_exceptions')) {
                    $counts['exceptions'] = DB::table('gtk_kbm_exceptions')->delete();
                }
            }
            $legacyNumbers = LessonPeriod::whereNull('day_of_week')->where('is_break', false)->where('period_number', '>', 0)
                ->orderBy('start_time')->orderBy('period_number')->get()->values()->mapWithKeys(fn ($period, $index) => [$period->id => $index + 1]);
            (new KbmTimeSeeder)->run();
            foreach (Schedule::with('lessonPeriod')->orderBy('id')->get() as $schedule) {
                if ($schedule->lessonPeriod?->day_of_week !== null) {
                    continue;
                }
                $number = $legacyNumbers->get($schedule->lesson_period_id);
                $target = $number ? LessonPeriod::where('day_of_week', $schedule->day_of_week)->where('period_number', $number)
                    ->where('is_break', false)->where('is_active', true)->first() : null;
                $duplicate = $target && Schedule::where('id', '!=', $schedule->id)->where('teaching_assignment_id', $schedule->teaching_assignment_id)
                    ->where('day_of_week', $schedule->day_of_week)->where('lesson_period_id', $target->id)->exists();
                if (! $target || $duplicate) {
                    if (! $reset) {
                        throw new \RuntimeException('Jadwal #'.$schedule->id.' tidak sesuai pola baru. Sesuaikan data atau gunakan --reset-test-data hanya untuk data pengujian.');
                    }
                    $schedule->delete();
                    $counts['removed']++;

                    continue;
                }
                $schedule->update(['lesson_period_id' => $target->id]);
                $counts['mapped']++;
            }
            LessonPeriod::whereNull('day_of_week')->update(['is_active' => false]);

            return $counts;
        });
        $this->info('Pola waktu 6 hari diterapkan. '.$counts['mapped'].' JP jadwal dikonversi; '.$counts['removed'].' JP tidak sesuai dihapus.');
        if ($reset) {
            $this->info('Data uji dibersihkan: '.$counts['attendance'].' presensi siswa, '.$counts['journals'].' jurnal mengajar, '.$counts['exceptions'].' pengecualian KBM.');
        }

        return self::SUCCESS;
    }
}
