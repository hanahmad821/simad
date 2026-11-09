<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentAttendanceHistoryService
{
    public const STATUSES = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'excused' => 'Izin',
        'sick' => 'Sakit',
        'absent' => 'Alpa',
    ];

    public function recordsForUser(User $user): Builder
    {
        abort_unless($user->hasActiveRole(['admin', 'staff', 'teacher', 'headmaster']), 403);

        return StudentAttendance::query()->whereHas('schedule.teachingAssignment', function ($query) use ($user) {
            $this->scopeAssignment($query, $user);
        });
    }

    private function scopeAssignment(Builder $query, User $user): void
    {
        if ($user->isTeacher()) {
            $user->gtk_id ? $query->where('gtk_id', $user->gtk_id) : $query->whereRaw('1 = 0');
        }
    }

    public function filteredRecords(User $user, array $filters): Builder
    {
        return $this->recordsForUser($user)
            ->where('attendance_date', '>=', $filters['fromDate'])
            ->where('attendance_date', '<', Carbon::parse($filters['toDate'])->addDay()->toDateString())
            ->when($filters['schoolClassId'] ?? '', fn ($query, $id) => $query->whereHas('schedule.teachingAssignment', fn ($assignment) => $assignment->where('school_class_id', $id)))
            ->when($filters['subjectId'] ?? '', fn ($query, $id) => $query->whereHas('schedule.teachingAssignment', fn ($assignment) => $assignment->where('subject_id', $id)))
            ->when($filters['status'] ?? '', fn ($query, $status) => $query->where('status', $status))
            ->when(trim($filters['search'] ?? '') !== '', fn ($query) => $query->whereHas('student', fn ($student) => $student->where(function ($student) use ($filters) {
                $search = trim($filters['search']);
                $student->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('nis', 'like', '%'.$search.'%')->orWhere('nisn', 'like', '%'.$search.'%');
            })));
    }

    public function sessions(User $user, array $filters): Builder
    {
        $query = $this->filteredRecords($user, $filters)
            ->select('schedule_id')->selectRaw('DATE(attendance_date) as session_date, COUNT(*) as total');
        foreach (array_keys(self::STATUSES) as $status) {
            $query->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as {$status}_count", [$status]);
        }

        return $query->with(['schedule.teachingAssignment.schoolClass', 'schedule.teachingAssignment.subject', 'schedule.teachingAssignment.gtk', 'schedule.lessonPeriod'])
            ->groupBy('schedule_id')->groupByRaw('DATE(attendance_date)')->orderByDesc('session_date')->orderBy('schedule_id');
    }

    public function summary(User $user, array $filters): array
    {
        return $this->filteredRecords($user, $filters)->select('status')->selectRaw('COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all();
    }

    public function sessionRecords(User $user, int $scheduleId, string $date): Collection
    {
        return $this->recordsForUser($user)->with([
            'student', 'recordedBy', 'schedule.teachingAssignment.schoolClass',
            'schedule.teachingAssignment.subject', 'schedule.teachingAssignment.gtk',
            'schedule.teachingAssignment.academicYear', 'schedule.lessonPeriod',
        ])->where('schedule_id', $scheduleId)->whereDate('attendance_date', $date)
            ->get()->sortBy(fn ($record) => $record->student?->full_name)->values();
    }

    public function classes(User $user): Collection
    {
        $this->recordsForUser($user);

        return SchoolClass::whereHas('teachingAssignments', function ($query) use ($user) {
            $this->scopeAssignment($query, $user);
            $query->whereHas('schedules.studentAttendances');
        })->orderBy('name')->get();
    }

    public function subjects(User $user): Collection
    {
        $this->recordsForUser($user);

        return Subject::whereHas('teachingAssignments', function ($query) use ($user) {
            $this->scopeAssignment($query, $user);
            $query->whereHas('schedules.studentAttendances');
        })->orderBy('name')->get();
    }
}
