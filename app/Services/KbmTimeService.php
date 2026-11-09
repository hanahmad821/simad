<?php

namespace App\Services;

use App\Models\KbmDayPattern;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KbmTimeService
{
    public const JP_MINUTES = 35;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    public function defaults(int $day): array
    {
        return [
            'morning_start' => '07:00',
            'morning_end' => match ($day) {
                4 => '07:50', 5 => '08:00', default => '07:30'
            },
            'teaching_periods' => $day === 5 ? 5 : 9,
            'breaks' => match ($day) {
                4 => [['start' => '10:10', 'end' => '10:30'], ['start' => '12:15', 'end' => '12:50']],
                5 => [['start' => '09:45', 'end' => '10:05']],
                default => [['start' => '09:50', 'end' => '10:25'], ['start' => '12:10', 'end' => '12:45']],
            },
        ];
    }

    public function validated(array $input): array
    {
        $data = Validator::make($input, [
            'morning_start' => ['required', 'date_format:H:i'],
            'morning_end' => ['required', 'date_format:H:i', 'after:morning_start'],
            'teaching_periods' => ['required', 'integer', 'min:1', 'max:12'],
            'breaks' => ['present', 'array', 'max:5'],
            'breaks.*.start' => ['required', 'date_format:H:i'],
            'breaks.*.end' => ['required', 'date_format:H:i'],
        ])->validate();
        $data['breaks'] = collect($data['breaks'])->sortBy('start')->values()->all();
        $lastEnd = $this->minutes($data['morning_end']);
        foreach ($data['breaks'] as $break) {
            $start = $this->minutes($break['start']);
            $end = $this->minutes($break['end']);
            if ($start <= $lastEnd || $end <= $start) {
                throw ValidationException::withMessages(['breaks' => 'Istirahat harus setelah kegiatan pagi, berurutan, dan tidak saling bertumpang tindih.']);
            }
            $lastEnd = $end;
        }

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function slots(array $input): array
    {
        $data = $this->validated($input);
        $slots = [['name' => 'Kegiatan Pagi', 'period_number' => 0, 'is_break' => false,
            'start_time' => $data['morning_start'], 'end_time' => $data['morning_end']]];
        $cursor = $this->minutes($data['morning_end']);
        $breakIndex = 0;
        foreach (range(1, (int) $data['teaching_periods']) as $number) {
            $break = $data['breaks'][$breakIndex] ?? null;
            if ($break && $this->minutes($break['start']) === $cursor) {
                $slots[] = ['name' => 'Istirahat '.($breakIndex + 1), 'period_number' => 0, 'is_break' => true,
                    'start_time' => $break['start'], 'end_time' => $break['end']];
                $cursor = $this->minutes($break['end']);
                $breakIndex++;
                $break = $data['breaks'][$breakIndex] ?? null;
            }
            $end = $cursor + self::JP_MINUTES;
            if ($break && $this->minutes($break['start']) < $end) {
                throw ValidationException::withMessages(['breaks' => 'Jam mulai istirahat harus tepat setelah kelipatan 35 menit pelajaran. Tidak boleh memotong satu JP.']);
            }
            if ($end >= 24 * 60) {
                throw ValidationException::withMessages(['teaching_periods' => 'Jam pelajaran tidak boleh melewati pergantian hari.']);
            }
            $slots[] = ['name' => 'Jam ke-'.$number, 'period_number' => $number, 'is_break' => false,
                'start_time' => $this->time($cursor), 'end_time' => $this->time($end)];
            $cursor = $end;
        }
        if ($breakIndex !== count($data['breaks'])) {
            throw ValidationException::withMessages(['breaks' => 'Ada istirahat di luar rentang JP yang dipilih. Sesuaikan jumlah JP atau hapus istirahat tersebut.']);
        }

        return $slots;
    }

    public function save(User $actor, array $days, array $input): void
    {
        abort_unless($actor->isAdmin(), 403);
        $days = Validator::make(['days' => $days], ['days' => ['required', 'array', 'min:1'], 'days.*' => ['required', 'integer', 'between:1,6']])->validate()['days'];
        $data = $this->validated($input);
        $this->slots($data);
        DB::transaction(function () use ($days, $data) {
            foreach (array_unique($days) as $day) {
                KbmDayPattern::updateOrCreate(['day_of_week' => $day], $data);
                $this->syncSlots((int) $day, $data);
            }
        }, 3);
    }

    public function syncSlots(int $day, array $data): void
    {
        $slots = $this->slots($data);
        $obsolete = LessonPeriod::where('day_of_week', $day)->whereNotIn('name', array_column($slots, 'name'))->pluck('id');
        if (Schedule::whereIn('lesson_period_id', $obsolete)->exists()) {
            throw ValidationException::withMessages(['teaching_periods' => 'JP yang akan dihapus masih digunakan jadwal. Sesuaikan atau hapus jadwal tersebut terlebih dahulu.']);
        }
        foreach ($slots as $index => $slot) {
            LessonPeriod::updateOrCreate(['day_of_week' => $day, 'name' => $slot['name']], $slot + ['sort_order' => $index, 'is_active' => true]);
        }
        LessonPeriod::whereIn('id', $obsolete)->delete();
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    private function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
