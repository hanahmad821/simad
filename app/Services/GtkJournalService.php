<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\GtkAbsenceRequest;
use App\Models\GtkAttendance;
use App\Models\GtkDailyApproval;
use App\Models\GtkKbmException;
use App\Models\GtkWorkAudit;
use App\Models\GtkWorkJournal;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type JournalSession array{key: string, label: string, schedule_ids: array<int, int>}
 * @phpstan-type Requirements array{sessions: Collection<string, JournalSession>, journals: Collection<string, GtkWorkJournal>, missing: Collection<string, JournalSession>, lckh: bool, ready: bool}
 */
class GtkJournalService
{
    /** @return Collection<string, JournalSession> */
    public function sessions(int $gtkId, CarbonInterface $date): Collection
    {
        if (! User::where('gtk_id', $gtkId)->withRoles(['teacher', 'headmaster'])->exists()) {
            return collect();
        }
        if (Schema::hasTable('gtk_absence_requests')
            && GtkAbsenceRequest::where('gtk_id', $gtkId)->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
            return collect();
        }
        $sessions = collect();
        if (! $date->isSunday() && ! Holiday::whereDate('date', $date)->where('is_active', true)->exists()) {
            $schedules = Schedule::with(['lessonPeriod', 'room', 'teachingAssignment.subject', 'teachingAssignment.schoolClass'])
                ->where('is_active', true)->where('day_of_week', $date->dayOfWeekIso)
                ->whereHas('lessonPeriod', fn ($q) => $q->where('is_active', true)->where('is_break', false)->where('period_number', '>', 0))
                ->whereHas('teachingAssignment', fn ($q) => $q->where('gtk_id', $gtkId)->where('is_active', true)
                    ->whereHas('academicYear', fn ($q) => $q->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)))
                ->get();
            $sessions = app(ScheduleSessionService::class)->group($schedules)->mapWithKeys(function ($session) {
                $label = $session->teachingAssignment->subject->name.' / '.$session->teachingAssignment->schoolClass->name
                    .' / JP '.$session->lesson_periods->pluck('period_number')->implode(', ');

                return ['kbm-'.$session->id => ['key' => 'kbm-'.$session->id, 'label' => $label, 'schedule_ids' => $session->schedules->pluck('id')->all()]];
            });
        }

        // Stored snapshots retain obligations and labels even after a timetable is edited.
        foreach (GtkWorkJournal::where('gtk_id', $gtkId)->whereDate('journal_date', $date)->where('kind', 'teaching')->get() as $journal) {
            $sessions->put($journal->session_key, ['key' => $journal->session_key, 'label' => $journal->label, 'schedule_ids' => $journal->schedule_ids]);
        }
        foreach (GtkKbmException::whereDate('date', $date)->where(fn ($q) => $q->where('gtk_id', $gtkId)->orWhere('substitute_gtk_id', $gtkId))->get() as $exception) {
            $key = 'kbm-'.$exception->schedule_id;
            if ($exception->gtk_id === $gtkId) {
                $sessions->forget($key);
            } else {
                $sessions->put($key, ['key' => $key, 'label' => $exception->label.' (pengganti)', 'schedule_ids' => $exception->schedule_ids]);
            }
        }

        return $sessions;
    }

    /** @return Requirements */
    public function requirements(User $user, CarbonInterface $date): array
    {
        abort_unless($user->gtk_id !== null, 403, 'Akun belum terhubung dengan GTK.');

        return $this->requirementsForGtk($user->gtk_id, $date);
    }

    /** @return Requirements */
    public function requirementsForGtk(int $gtkId, CarbonInterface $date): array
    {
        $journals = GtkWorkJournal::where('gtk_id', $gtkId)->whereDate('journal_date', $date)->get()->keyBy('session_key');
        $sessions = $this->sessions($gtkId, $date);
        $missing = $sessions->filter(fn ($session) => ! $journals->get($session['key'])?->isComplete()
            || ! $this->studentAttendanceProgress($session['schedule_ids'], $date)['complete']);
        $lckh = $journals->get('lckh')?->isComplete() ?? false;

        return ['sessions' => $sessions, 'journals' => $journals, 'missing' => $missing, 'lckh' => $lckh, 'ready' => $lckh && $missing->isEmpty()];
    }

    /** @return list<string> */
    public function completionErrors(User $user, ?CarbonInterface $date = null): array
    {
        $date ??= today();
        $requirements = $this->requirements($user, $date);
        $errors = $requirements['lckh'] ? [] : [$date->isToday() ? 'LCKH hari ini belum lengkap.' : 'LCKH tanggal '.$date->format('d/m/Y').' belum lengkap.'];
        foreach ($requirements['missing'] as $session) {
            $errors[] = $this->studentAttendanceProgress($session['schedule_ids'], $date)['complete']
                ? 'Jurnal mengajar belum lengkap: '.$session['label'].'.'
                : 'Presensi siswa belum lengkap; jurnal mengajar masih terkunci: '.$session['label'].'.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $input */
    public function save(User $user, CarbonInterface $date, string $key, array $input): void
    {
        abort_unless($user->gtk_id && $date->lte(today()), 403);
        abort_unless($key === 'lckh' || $user->canTeach(), 403);
        $data = Validator::make($input, [
            'status' => ['required', 'in:draft,completed'],
            'material' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'reflection' => ['nullable', 'string', 'max:20000'],
            'activities' => ['nullable', 'string', 'max:30000'],
        ])->validate();
        DB::transaction(function () use ($user, $date, $key, $data) {
            Gtk::whereKey($user->gtk_id)->lockForUpdate()->firstOrFail();
            $session = $key === 'lckh' ? null : $this->sessions($user->gtk_id, $date)->get($key);
            abort_unless($key === 'lckh' || $session, 403);
            if ($session && ! $this->studentAttendanceProgress($session['schedule_ids'], $date)['complete']) {
                throw ValidationException::withMessages(['journal' => 'Lengkapi dan simpan presensi seluruh siswa pada sesi pembelajaran dan tanggal ini sebelum mengisi jurnal mengajar.']);
            }
            $journal = GtkWorkJournal::where('gtk_id', $user->gtk_id)->whereDate('journal_date', $date)->where('session_key', $key)->first()
                ?? new GtkWorkJournal(['gtk_id' => $user->gtk_id, 'journal_date' => $date->toDateString(), 'session_key' => $key]);
            $before = $journal->exists ? $journal->toArray() : null;
            $journal->fill($data);
            $journal->kind = $key === 'lckh' ? 'daily' : 'teaching';
            $journal->label = $session['label'] ?? 'LCKH';
            $journal->schedule_ids = $session['schedule_ids'] ?? null;
            if ($journal->status === 'completed' && ! $journal->isComplete()) {
                throw ValidationException::withMessages(['journal' => $key === 'lckh'
                    ? 'Uraian kegiatan LCKH wajib diisi.' : 'Uraian materi, catatan, dan refleksi wajib diisi.']);
            }
            $journal->completed_at = $journal->isComplete() ? now() : null;
            $journal->save();
            $this->audit($user, $user->gtk_id, $date, 'journal_saved', $before, $journal->toArray());
            if ($key === 'lckh') {
                app(GtkDailyApprovalService::class)->invalidate($user->gtk_id, $date);
            }
        });
    }

    public function delete(User $user, GtkWorkJournal $journal): void
    {
        abort_unless($user->gtk_id !== null && $journal->gtk_id === $user->gtk_id, 403);
        abort_unless($journal->kind === 'daily' || ($journal->kind === 'teaching' && $user->canTeach()), 403);

        DB::transaction(function () use ($user, $journal) {
            Gtk::whereKey($user->gtk_id)->lockForUpdate()->firstOrFail();
            $journal = GtkWorkJournal::whereKey($journal->id)->where('gtk_id', $user->gtk_id)->lockForUpdate()->firstOrFail();
            $date = $journal->journal_date;
            abort_unless($date->lte(today()), 403);
            if (GtkDailyApproval::where('gtk_id', $user->gtk_id)->whereDate('date', $date)->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages(['journal' => 'LCKH atau jurnal yang sudah disetujui tidak dapat dihapus.']);
            }

            $before = $journal->toArray();
            $journal->delete();
            $this->audit($user, $user->gtk_id, $date, 'journal_deleted', $before, []);
            if ($journal->kind === 'daily') {
                app(GtkDailyApprovalService::class)->invalidate($user->gtk_id, $date);
            }
        });
    }

    /**
     * @param  array<int, int>  $scheduleIds
     * @return array{total: int, recorded: int, complete: bool}
     */
    public function studentAttendanceProgress(array $scheduleIds, CarbonInterface $date): array
    {
        $schedule = isset($scheduleIds[0]) ? Schedule::with('teachingAssignment')->find($scheduleIds[0]) : null;
        if (! $schedule || ! $schedule->teachingAssignment) {
            return ['total' => 0, 'recorded' => 0, 'complete' => false];
        }
        $assignment = $schedule->teachingAssignment;
        $studentIds = Student::where('is_active', true)->whereHas('classHistories', fn ($q) => $q
            ->where('school_class_id', $assignment->school_class_id)
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('is_active', true))->pluck('id');
        // The first schedule is the attendance anchor for the whole consecutive session.
        $recorded = StudentAttendance::where('schedule_id', $schedule->id)->whereDate('attendance_date', $date)
            ->whereIn('student_id', $studentIds)->whereIn('status', ['present', 'late', 'excused', 'sick', 'absent'])
            ->distinct()->count('student_id');
        $total = $studentIds->count();

        return ['total' => $total, 'recorded' => $recorded, 'complete' => $total > 0 && $recorded === $total];
    }

    public function exception(User $actor, User $teacher, CarbonInterface $date, string $key, ?int $substitute, string $reason): void
    {
        abort_unless($actor->isAdmin() || $actor->isHeadmaster(), 403);
        abort_unless($teacher->gtk_id && $date->lte(today()), 403);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:5000']])->validate();
        DB::transaction(function () use ($actor, $teacher, $date, $key, $substitute, $reason) {
            $ids = array_filter([$teacher->gtk_id, $substitute]);
            Gtk::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $session = $this->sessions($teacher->gtk_id, $date)->get($key);
            abort_unless($session && str_starts_with($key, 'kbm-'), 422, 'Sesi tidak tersedia.');
            abort_if(GtkKbmException::whereDate('date', $date)->where('schedule_id', (int) substr($key, 4))->exists(), 422, 'Perubahan sesi ini sudah tercatat.');
            if ($substitute) {
                abort_unless($substitute !== $teacher->gtk_id && User::where('gtk_id', $substitute)->withRoles(['teacher', 'headmaster'])->exists(), 422, 'Guru pengganti tidak valid.');
                if (GtkAttendance::where('gtk_id', $substitute)->whereDate('attendance_date', $date)->whereNotNull('check_out_at')->exists()) {
                    throw ValidationException::withMessages(['exception' => 'Guru pengganti sudah checkout pada tanggal ini.']);
                }
            }
            $exception = GtkKbmException::create([
                'date' => $date->toDateString(), 'schedule_id' => (int) substr($key, 4), 'gtk_id' => $teacher->gtk_id,
                'substitute_gtk_id' => $substitute, 'label' => $session['label'], 'schedule_ids' => $session['schedule_ids'],
                'reason' => trim($reason), 'created_by' => $actor->id,
            ]);
            $this->audit($actor, $teacher->gtk_id, $date, 'kbm_exception', null, $exception->toArray());
            if ($substitute) {
                $this->audit($actor, $substitute, $date, 'kbm_substitute', null, $exception->toArray());
            }
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public function audit(User $actor, int $gtkId, CarbonInterface $date, string $event, ?array $before, array $after): void
    {
        GtkWorkAudit::create(['gtk_id' => $gtkId, 'actor_id' => $actor->id, 'date' => $date->toDateString(), 'event' => $event, 'before' => $before, 'after' => $after]);
    }
}
