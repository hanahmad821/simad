<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;

class TeacherScheduleService
{
    public function sessions(User $user): Collection
    {
        abort_unless($user->canTeach(), 403);
        if (! $user->gtk_id) {
            return collect();
        }

        $schedules = Schedule::with([
            'lessonPeriod', 'room', 'teachingAssignment.schoolClass', 'teachingAssignment.subject',
            'teachingAssignment.gtk', 'teachingAssignment.academicYear',
        ])->where('is_active', true)->whereBetween('day_of_week', [1, 6])
            ->whereHas('lessonPeriod', fn ($period) => $period->where('is_active', true)->where('is_break', false)->where('period_number', '>', 0))
            ->whereHas('teachingAssignment', fn ($assignment) => $assignment->where('gtk_id', $user->gtk_id)
                ->where('is_active', true)->whereHas('academicYear', fn ($year) => $year->where('is_active', true)))
            ->get();

        return app(ScheduleSessionService::class)->group($schedules)
            ->sortBy(fn ($session) => sprintf('%d-%s', $session->schedules->first()->day_of_week, $session->lesson_periods->first()->start_time))
            ->values();
    }
}
