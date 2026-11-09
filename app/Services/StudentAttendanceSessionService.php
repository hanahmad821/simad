<?php

namespace App\Services;

use App\Models\GtkKbmException;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StudentAttendanceSessionService
{
    /** @return Collection<int, object> */
    public function forUser(User $user, string $date): Collection
    {
        abort_unless($user->hasActiveRole(['admin', 'staff', 'teacher', 'headmaster']), 403);
        $personalTeaching = $user->canTeach();

        if ($personalTeaching && ! $user->gtk_id) {
            return collect();
        }

        $schedules = Schedule::query()
            ->with(['teachingAssignment.schoolClass', 'teachingAssignment.subject', 'teachingAssignment.gtk', 'lessonPeriod', 'room'])
            ->where('day_of_week', Carbon::parse($date)->dayOfWeekIso)
            ->where('is_active', true)
            ->whereHas('lessonPeriod', fn ($query) => $query->where('is_active', true)->where('is_break', false))
            ->whereHas('teachingAssignment', function ($query) use ($user, $personalTeaching) {
                $query->where('is_active', true)
                    ->whereHas('academicYear', fn ($year) => $year->where('is_active', true))
                    ->when($personalTeaching, fn ($assignment) => $assignment->where('gtk_id', $user->gtk_id));
            })->get();

        $sessions = app(ScheduleSessionService::class)->group($schedules);
        if ($personalTeaching && Schema::hasTable('gtk_kbm_exceptions')) {
            foreach (GtkKbmException::whereDate('date', $date)->where(fn ($q) => $q
                ->where('gtk_id', $user->gtk_id)->orWhere('substitute_gtk_id', $user->gtk_id))->get() as $exception) {
                $sessions = $sessions->reject(fn ($session) => $session->id === $exception->schedule_id)->values();
                if ($exception->substitute_gtk_id === $user->gtk_id && Schedule::whereKey($exception->schedule_id)->exists()) {
                    $sessions->push(app(ScheduleSessionService::class)->sessionFor($exception->schedule_id));
                }
            }
        }

        return $sessions;
    }
}
