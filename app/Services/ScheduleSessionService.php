<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScheduleSessionService
{
    public function periodsForDay(int $day, ?Collection $periods = null): Collection
    {
        $periods ??= LessonPeriod::where('is_active', true)->orderBy('start_time')->orderBy('period_number')->get();
        $daily = $periods->where('day_of_week', $day);

        // Legacy global slots remain readable until the existing test timetable is converted.
        return ($daily->isNotEmpty() ? $daily : $periods->whereNull('day_of_week'))
            ->sortBy(fn ($period) => $period->start_time.'-'.sprintf('%03d', $period->sort_order ?? $period->period_number))->values();
    }

    public function periodSpan(int $startId, int $duration, int $day): Collection
    {
        if ($duration < 1 || $duration > 12) {
            throw ValidationException::withMessages(['duration' => 'Durasi harus antara 1 sampai 12 JP.']);
        }
        $periods = $this->periodsForDay($day);
        $teaching = $periods->filter(fn ($period) => ! $period->is_break && $period->period_number > 0)->values();
        $index = $teaching->search(fn ($period) => $period->id === $startId);
        if ($index === false) {
            throw ValidationException::withMessages(['lessonPeriodId' => 'Jam mulai tidak tersedia pada hari ini. Pilih JP sesuai pola waktu hari terpilih.']);
        }
        $span = $teaching->slice($index, $duration)->values();
        if ($span->count() !== $duration) {
            throw ValidationException::withMessages(['duration' => 'Durasi melewati akhir jadwal. Pilih jam mulai atau durasi lain.']);
        }
        foreach ($span as $offset => $period) {
            if ($period->day_of_week !== null && Carbon::parse($period->start_time)->diffInMinutes(Carbon::parse($period->end_time)) != KbmTimeService::JP_MINUTES) {
                throw ValidationException::withMessages(['lessonPeriodId' => 'Durasi satu JP harus tepat 35 menit. Periksa Pengaturan Waktu KBM.']);
            }
            if ($offset && ! $this->consecutive($span[$offset - 1], $period, $periods)) {
                throw ValidationException::withMessages(['duration' => 'Jam pelajaran dalam satu sesi harus berurutan.']);
            }
        }

        return $span;
    }

    public function group(Collection $schedules): Collection
    {
        $periods = LessonPeriod::where('is_active', true)->get();
        $schedules = $schedules->filter(fn ($schedule) => $schedule->lessonPeriod
            && ! $schedule->lessonPeriod->is_break && $schedule->lessonPeriod->period_number > 0
            && ($schedule->lessonPeriod->day_of_week === null || $schedule->lessonPeriod->day_of_week === $schedule->day_of_week));
        $sessions = collect();
        foreach ($schedules->groupBy(fn ($schedule) => $schedule->teaching_assignment_id.'-'.$schedule->day_of_week.'-'.(int) $schedule->is_active) as $items) {
            $last = null;
            $timeline = $this->periodsForDay($items->first()->day_of_week, $periods);
            foreach ($items->sortBy(fn ($schedule) => $schedule->lessonPeriod->period_number) as $schedule) {
                if ($last && $this->consecutive($last->lesson_periods->last(), $schedule->lessonPeriod, $timeline)) {
                    $last->schedules->push($schedule);
                    $last->lesson_periods->push($schedule->lessonPeriod);
                } else {
                    $last = (object) [
                        'id' => $schedule->id, 'teaching_assignment_id' => $schedule->teaching_assignment_id,
                        'teachingAssignment' => $schedule->teachingAssignment, 'room' => $schedule->room,
                        'schedules' => collect([$schedule]), 'lesson_periods' => collect([$schedule->lessonPeriod]),
                    ];
                    $sessions->push($last);
                }
            }
        }

        return $sessions->each(function ($session) {
            $session->segments = collect();
            foreach ($session->schedules as $schedule) {
                $segment = $session->segments->last();
                if ($segment && ($schedule->lessonPeriod->day_of_week === null
                    ? $segment->schedules->last()->lessonPeriod->period_number + 1 === $schedule->lessonPeriod->period_number
                    : substr($segment->schedules->last()->lessonPeriod->end_time, 0, 5) === substr($schedule->lessonPeriod->start_time, 0, 5))) {
                    $segment->schedules->push($schedule);
                } else {
                    $session->segments->push((object) ['id' => $schedule->id, 'schedules' => collect([$schedule])]);
                }
            }
        })->sortBy(fn ($session) => $session->lesson_periods->first()->period_number)->values();
    }

    private function consecutive(LessonPeriod $previous, LessonPeriod $next, Collection $timeline): bool
    {
        if ($next->day_of_week === null) {
            if ($previous->period_number + 1 === $next->period_number) {
                return true;
            }
            $between = $timeline->where('period_number', '>', $previous->period_number)->where('period_number', '<', $next->period_number);

            return $between->isNotEmpty() && $between->every(fn ($period) => $period->is_break);
        }
        if ($previous->period_number + 1 !== $next->period_number) {
            return false;
        }
        $end = substr($previous->end_time, 0, 5);
        $start = substr($next->start_time, 0, 5);
        foreach ($timeline->where('is_break', true) as $break) {
            if (substr($break->start_time, 0, 5) === $end && substr($break->end_time, 0, 5) <= $start) {
                $end = substr($break->end_time, 0, 5);
            }
        }

        return $end === $start;
    }

    public function sessionFor(int $id): object
    {
        $schedule = Schedule::findOrFail($id);
        $items = Schedule::with(['lessonPeriod', 'room', 'teachingAssignment'])
            ->where('teaching_assignment_id', $schedule->teaching_assignment_id)->where('day_of_week', $schedule->day_of_week)
            ->where('is_active', $schedule->is_active)->get();

        return $this->group($items)->first(fn ($session) => $session->schedules->contains('id', $id));
    }

    public function save(User $user, array $data, ?int $editingId = null): void
    {
        abort_unless($user->isAdmin(), 403);
        $data = Validator::make($data, [
            'academicYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'teachingAssignmentId' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'lessonPeriodId' => ['required', 'integer', 'exists:lesson_periods,id'],
            'duration' => ['required', 'integer', 'min:1', 'max:12'],
            'roomId' => ['nullable', 'integer', 'exists:rooms,id'],
            'dayOfWeek' => ['required', 'integer', 'in:1,2,3,4,5,6'],
            'isActive' => ['required', 'boolean'],
        ], [
            'duration.min' => 'Durasi minimal 1 JP.', 'duration.max' => 'Durasi maksimal 12 JP.',
        ])->validate();

        DB::transaction(function () use ($data, $editingId) {
            $assignment = TeachingAssignment::findOrFail($data['teachingAssignmentId']);
            // Serialize writes that share a teacher, class, room or assignment.
            Gtk::whereKey($assignment->gtk_id)->lockForUpdate()->first();
            $class = SchoolClass::whereKey($assignment->school_class_id)->lockForUpdate()->firstOrFail();
            $roomId = $data['roomId'] ?? $class->room_id;
            if (! $roomId) {
                throw ValidationException::withMessages(['roomId' => 'Tentukan ruang kelas bawaan atau pilih ruangan untuk sesi ini.']);
            }
            $room = Room::whereKey($roomId)->lockForUpdate()->firstOrFail();
            $assignment = TeachingAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (! $assignment->is_active || $assignment->academic_year_id !== (int) $data['academicYearId']) {
                throw ValidationException::withMessages(['teachingAssignmentId' => 'Pilih penugasan aktif yang sesuai dengan tahun pelajaran.']);
            }
            if (! $room->is_active) {
                throw ValidationException::withMessages(['roomId' => 'Pilih ruangan yang aktif.']);
            }
            $span = $this->periodSpan((int) $data['lessonPeriodId'], (int) $data['duration'], (int) $data['dayOfWeek']);
            $periodIds = $span->pluck('id')->all();
            $old = $editingId ? $this->sessionFor($editingId)->schedules : collect();
            $oldIds = $old->pluck('id')->all();
            Schedule::whereIn('id', $oldIds)->lockForUpdate()->get();
            if ($old->isNotEmpty()) {
                $previous = $old->first()->teachingAssignment;
                if ($previous->academic_year_id !== (int) $data['academicYearId']) {
                    throw ValidationException::withMessages(['schedule' => 'Sesi yang diedit tidak sesuai dengan filter tahun pelajaran.']);
                }
                $hasAttendance = Schedule::whereIn('id', $oldIds)->whereHas('studentAttendances')->exists();
                if ($hasAttendance && ($old->first()->teaching_assignment_id !== $assignment->id
                    || $old->first()->day_of_week !== (int) $data['dayOfWeek'] || $old->pluck('lesson_period_id')->all() !== $periodIds)) {
                    throw ValidationException::withMessages(['schedule' => 'Sesi ini sudah memiliki presensi. Jam, durasi, hari, dan penugasannya tidak dapat diubah. Nonaktifkan sesi lalu buat jadwal baru.']);
                }
            }

            $conflicts = Schedule::where('day_of_week', $data['dayOfWeek'])->whereIn('lesson_period_id', $periodIds)
                ->where('is_active', true)->whereNotIn('id', $oldIds)
                ->whereHas('teachingAssignment', fn ($query) => $query->where('academic_year_id', $assignment->academic_year_id)->where('is_active', true));
            if ($data['isActive']) {
                if ((clone $conflicts)->whereHas('teachingAssignment', fn ($query) => $query->where('gtk_id', $assignment->gtk_id))->exists()) {
                    throw ValidationException::withMessages(['lessonPeriodId' => 'Guru sudah memiliki jadwal pada salah satu JP dalam sesi ini.']);
                }
                if ((clone $conflicts)->whereHas('teachingAssignment', fn ($query) => $query->where('school_class_id', $assignment->school_class_id))->exists()) {
                    throw ValidationException::withMessages(['lessonPeriodId' => 'Kelas sudah memiliki jadwal pada salah satu JP dalam sesi ini.']);
                }
                if ((clone $conflicts)->where('room_id', $room->id)->exists()) {
                    throw ValidationException::withMessages(['roomId' => 'Ruangan sudah digunakan pada salah satu JP dalam sesi ini.']);
                }
            }

            // Reuse rows at unchanged periods, keeping attendance anchors intact.
            $kept = [];
            foreach ($periodIds as $periodId) {
                $schedule = Schedule::firstOrNew([
                    'teaching_assignment_id' => $assignment->id,
                    'lesson_period_id' => $periodId, 'day_of_week' => $data['dayOfWeek'],
                ]);
                $schedule->fill(['room_id' => $room->id, 'is_active' => $data['isActive']])->save();
                $kept[] = $schedule->id;
            }
            if ($data['isActive']) {
                $resultingSchedules = Schedule::with(['lessonPeriod', 'room', 'teachingAssignment'])->withCount('studentAttendances')
                    ->where('teaching_assignment_id', $assignment->id)->where('day_of_week', $data['dayOfWeek'])
                    ->where('is_active', true)->whereNotIn('id', array_diff($oldIds, $kept))->get();
                foreach ($this->group($resultingSchedules) as $session) {
                    if ($session->schedules->contains(fn ($schedule) => in_array($schedule->id, $kept, true))
                        && $session->schedules->contains(fn ($schedule) => $schedule->student_attendances_count > 0 && $schedule->id !== $session->id)) {
                        throw ValidationException::withMessages(['schedule' => 'Jam baru akan mengubah awal sesi yang sudah memiliki presensi. Pilih sesi terpisah agar riwayat tetap sesuai.']);
                    }
                }
            }
            Schedule::whereIn('id', array_diff($oldIds, $kept))->delete();
        }, 3);
    }

    public function delete(User $user, int $id): void
    {
        abort_unless($user->isAdmin(), 403);
        DB::transaction(function () use ($id) {
            $ids = $this->sessionFor($id)->schedules->pluck('id')->all();
            Schedule::whereIn('id', $ids)->lockForUpdate()->get();
            if (Schedule::whereIn('id', $ids)->whereHas('studentAttendances')->exists()) {
                throw ValidationException::withMessages(['schedule' => 'Sesi sudah memiliki presensi dan tidak dapat dihapus. Nonaktifkan sesi agar riwayat tetap tersimpan.']);
            }
            Schedule::whereIn('id', $ids)->delete();
        });
    }
}
