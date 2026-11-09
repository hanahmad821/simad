<?php

namespace App\Services;

use App\Models\GtkAttendance;
use App\Models\Holiday;
use App\Models\KbmDayPattern;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GtkAttendancePolicyService
{
    public function week(): array
    {
        $patterns = Schema::hasTable('kbm_day_patterns') ? KbmDayPattern::all()->keyBy('day_of_week') : collect();
        $times = app(KbmTimeService::class);

        return collect(range(1, 6))->mapWithKeys(function ($day) use ($patterns, $times) {
            $values = $patterns->get($day)?->toArray() ?? $times->defaults($day);
            $values['morning_start'] = substr($values['morning_start'], 0, 5);
            $values['morning_end'] = substr($values['morning_end'], 0, 5);
            $slots = collect($times->slots($values));

            return [$day => ['start' => $slots->first()['start_time'], 'end' => $slots->last()['end_time']]];
        })->all();
    }

    public function context(CarbonInterface $date, ?array $week = null, ?Collection $holidays = null): array
    {
        $week ??= $this->week();
        $holiday = $holidays !== null ? $holidays->get($date->toDateString())
            : (Schema::hasTable('holidays') ? Holiday::whereDate('date', $date)->where('is_active', true)->value('name') : null);
        $holiday ??= $date->isSunday() ? 'Libur Minggu' : null;
        $rule = $week[$date->dayOfWeekIso] ?? null;

        return ['holiday' => $holiday, 'is_workday' => $rule !== null && $holiday === null,
            'start' => $rule ? Carbon::parse($date->toDateString().' '.$rule['start']) : null,
            'end' => $rule ? Carbon::parse($date->toDateString().' '.$rule['end']) : null];
    }

    public function captureExpectedTimes(GtkAttendance $attendance): void
    {
        $context = $this->context($attendance->attendance_date);
        if ($context['is_workday']) {
            $attendance->scheduled_check_in_at ??= $context['start'];
            $attendance->scheduled_check_out_at ??= $context['end'];
        }
        if (in_array($attendance->status, ['present', 'late'], true)) {
            $attendance->status = $this->evaluate($attendance->attendance_date, $attendance, $context)['status'];
        }
    }

    public function evaluate(CarbonInterface $date, ?GtkAttendance $attendance, ?array $context = null): array
    {
        $context ??= $this->context($date);
        $start = $attendance?->scheduled_check_in_at ?? $context['start'];
        $end = $attendance?->scheduled_check_out_at ?? $context['end'];
        $past = $date->toDateString() < today()->toDateString();
        $hasPresence = (bool) ($attendance?->check_in_at || $attendance?->check_out_at);
        $status = match (true) {
            in_array($attendance?->status, ['leave', 'sick', 'absent'], true) => $attendance->status,
            $hasPresence => 'present',
            $context['holiday'] !== null => 'holiday',
            $date->toDateString() > today()->toDateString() => 'future',
            $past && $context['is_workday'] => 'absent',
            default => 'pending',
        };
        $lateMinutes = 0;
        $earlyMinutes = 0;
        $forgotten = false;
        if ($status === 'present' && $context['is_workday']) {
            $lateMinutes = $attendance?->check_in_at && $start
                ? max(0, (int) ceil($start->diffInSeconds($attendance->check_in_at, false) / 60)) : 0;
            $forgotten = (bool) $attendance?->forgotten_checkout_at
                || (bool) preg_match('/\[Lupa presensi [^\]]*pulang\]/i', $attendance?->notes ?? '')
                || ($past && $attendance?->check_in_at && ! $attendance?->check_out_at);
            $earlyMinutes = $forgotten ? null : ($attendance?->check_out_at && $end
                ? max(0, (int) ceil($attendance->check_out_at->diffInSeconds($end, false) / 60)) : 0);
            $status = $lateMinutes > 0 ? 'late' : 'present';
        }

        return ['status' => $status,
            'label' => ['present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Izin / Cuti / Dinas', 'sick' => 'Sakit',
                'absent' => 'Alpa', 'holiday' => 'Libur', 'future' => 'Belum waktunya', 'pending' => 'Belum presensi'][$status],
            'late_minutes' => $lateMinutes, 'late_level' => $this->level($lateMinutes, 'T'),
            'early_minutes' => $earlyMinutes, 'early_level' => $forgotten ? 'PSW3' : $this->level($earlyMinutes ?? 0, 'PSW'),
            'forgotten_checkout' => $forgotten, 'expected_start' => $start, 'expected_end' => $end,
            'is_workday' => $context['is_workday'], 'holiday' => $context['holiday']];
    }

    private function level(int $minutes, string $prefix): ?string
    {
        return match (true) {
            $minutes <= 0 => null,
            $minutes <= 30 => $prefix.'1',
            $minutes <= 60 => $prefix.'2',
            default => $prefix.'3',
        };
    }

    public function monthRows(int $gtkId, CarbonInterface $month): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];
        $attendances = GtkAttendance::where('gtk_id', $gtkId)->whereBetween('attendance_date', $range)
            ->get()->keyBy(fn ($row) => $row->attendance_date->toDateString());
        $holidays = Holiday::where('is_active', true)->whereBetween('date', $range)->get()
            ->mapWithKeys(fn ($holiday) => [$holiday->date->toDateString() => $holiday->name]);
        $week = $this->week();

        return collect($start->toPeriod($end))->map(function ($date) use ($attendances, $holidays, $week) {
            $attendance = $attendances->get($date->toDateString());
            $classification = $this->evaluate($date, $attendance, $this->context($date, $week, $holidays));

            return ['date' => $date->toDateString(), 'attendance' => $attendance,
                'classification' => $classification, 'holiday' => $classification['holiday']];
        });
    }
}
