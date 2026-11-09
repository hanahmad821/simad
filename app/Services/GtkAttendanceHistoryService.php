<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\GtkAbsenceRequest;
use App\Models\GtkAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GtkAttendanceHistoryService
{
    public const MONTH_RULES = ['required', 'date_format:Y-m', 'before_or_equal:today'];

    public function days(User $user, string $month): Collection
    {
        abort_unless($user->gtk !== null, 403, 'Akun belum terhubung dengan data GTK.');
        Validator::make(['month' => $month], ['month' => self::MONTH_RULES])->validate();
        $start = Carbon::createFromFormat('!Y-m', $month);
        $end = $start->copy()->endOfMonth();
        $pendingAbsences = Schema::hasTable('gtk_absence_requests')
            ? GtkAbsenceRequest::where('gtk_id', $user->gtk_id)->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->get()
            : collect();

        return app(GtkAttendancePolicyService::class)->monthRows($user->gtk_id, $start)->map(function ($row) use ($user, $pendingAbsences) {
            $date = Carbon::parse($row['date']);
            $attendance = $row['attendance'];
            $hasAbsence = $pendingAbsences->contains(fn ($absence) => $absence->start_date->lte($date) && $absence->end_date->gte($date));
            $canCorrect = $user->gtk->is_active && $date->lt(today()) && ! $hasAbsence
                && $attendance?->check_in_at && ! $attendance->check_out_at && ! $attendance->forgotten_checkout_at
                && in_array($attendance->status, ['present', 'late'], true) && ! $attendance->gtk_absence_request_id;

            return array_replace($row, ['date' => $date, 'can_correct' => $canCorrect]);
        });
    }

    public function correctionDetails(User $user, string $date): ?GtkAttendance
    {
        abort_unless($user->hasActiveRole(['admin', 'teacher', 'staff', 'headmaster'])
            && $user->gtk?->is_active, 403, 'Akun harus terhubung dengan GTK aktif.');
        Validator::make(['correctionDate' => $date], [
            'correctionDate' => ['required', 'date_format:Y-m-d', 'before:today'],
        ], ['correctionDate.before' => 'Lupa presensi hanya dapat dilengkapi untuk tanggal yang sudah lewat.'])->validate();
        $attendance = GtkAttendance::where('gtk_id', $user->gtk_id)->whereDate('attendance_date', $date)->first();
        if (Schema::hasTable('gtk_absence_requests') && GtkAbsenceRequest::where('gtk_id', $user->gtk_id)
            ->whereIn('status', ['pending', 'approved'])->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['attendance' => 'Tanggal ini memiliki pengajuan absensi yang masih diproses atau sudah disetujui.']);
        }
        if ($attendance && ($attendance->gtk_absence_request_id || ! in_array($attendance->status, ['present', 'late'], true))) {
            throw ValidationException::withMessages(['attendance' => 'Lupa presensi tidak dapat mengubah catatan ketidakhadiran.']);
        }
        if ($attendance?->check_in_at && $attendance?->check_out_at) {
            throw ValidationException::withMessages(['attendance' => 'Presensi tanggal ini sudah lengkap. Jam yang sudah tercatat tidak dapat ditimpa.']);
        }
        if (! $attendance?->check_in_at) {
            throw ValidationException::withMessages(['attendance' => 'Tanpa presensi masuk, hari kerja dihitung alpa. Gunakan Pengajuan Absensi jika izin, cuti, atau sakit.']);
        }
        if ($attendance->forgotten_checkout_at) {
            throw ValidationException::withMessages(['attendance' => 'Lupa presensi pulang tanggal ini sudah dilaporkan.']);
        }

        return $attendance;
    }

    public function saveForgotten(User $user, array $input): void
    {
        DB::transaction(function () use ($user, $input) {
            abort_unless($user->gtk_id !== null, 403);
            $gtk = Gtk::whereKey($user->gtk_id)->lockForUpdate()->firstOrFail();
            abort_unless($gtk->is_active, 403);
            $attendance = $this->correctionDetails($user, (string) ($input['correctionDate'] ?? ''));
            $data = Validator::make($input, [
                'reason' => ['required', 'string', 'max:1000'],
            ])->validate();
            $date = Carbon::parse($input['correctionDate']);
            $before = $attendance->toArray();
            app(GtkAttendancePolicyService::class)->captureExpectedTimes($attendance);
            $attendance->forgotten_checkout_at = now();
            $attendance->notes = trim(($attendance->notes ? $attendance->notes."\n" : '').'[Lupa presensi pulang] '.trim($data['reason']));
            $attendance->save();
            app(GtkJournalService::class)->audit($user, $gtk->id, $date, 'attendance_forgotten', $before, $attendance->toArray());
        }, 3);
    }
}
