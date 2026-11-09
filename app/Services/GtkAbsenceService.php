<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\GtkAbsenceRequest;
use App\Models\GtkAttendance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GtkAbsenceService
{
    /** @param array<string, mixed> $input */
    public function submit(User $actor, array $input, mixed $evidence): GtkAbsenceRequest
    {
        abort_unless($actor->gtk_id && $actor->gtk?->is_active, 403, 'Akun harus terhubung dengan GTK aktif.');
        $data = Validator::make(array_merge($input, ['evidence' => $evidence]), [
            'type' => ['required', Rule::in(array_keys(GtkAbsenceRequest::TYPES))],
            'description' => ['required', 'string', 'max:1000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:500'],
        ], [
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'evidence.max' => 'Ukuran bukti maksimal 500 KB.',
            'evidence.mimes' => 'Bukti harus berupa JPG, PNG, atau PDF.',
            'evidence.extensions' => 'Ekstensi bukti harus JPG, JPEG, PNG, atau PDF.',
        ])->validate();
        assert($evidence instanceof UploadedFile);
        unset($data['evidence']);
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $data, $evidence, &$path) {
                $gtk = Gtk::whereKey($actor->gtk_id)->lockForUpdate()->firstOrFail();
                abort_unless($gtk->is_active, 403);
                $this->assertAvailable($gtk->id, $data['start_date'], $data['end_date']);
                $path = $evidence->store('gtk-absence-evidence', 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['evidence' => 'Bukti gagal disimpan. Silakan coba kembali.']);
                }
                $absence = GtkAbsenceRequest::create($data + ['gtk_id' => $gtk->id, 'submitted_by' => $actor->id,
                    'status' => 'pending', 'evidence_path' => $path]);
                $this->audit($actor, $absence, 'absence_submitted', null);

                return $absence;
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    private function assertAvailable(int $gtkId, string $start, string $end, ?int $except = null): void
    {
        if (GtkAbsenceRequest::where('gtk_id', $gtkId)->whereIn('status', ['pending', 'approved'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal bertumpang tindih dengan pengajuan yang diajukan atau disetujui.']);
        }
        if (GtkAttendance::where('gtk_id', $gtkId)->whereDate('attendance_date', '>=', $start)->whereDate('attendance_date', '<=', $end)->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Sudah ada presensi pada rentang tanggal ini. Data presensi tidak ditimpa.']);
        }
    }

    /** @param array<string, mixed> $input */
    public function decide(User $actor, GtkAbsenceRequest $absence, array $input): void
    {
        abort_unless($actor->isHeadmaster(), 403);
        abort_if($actor->id === $absence->submitted_by || $actor->gtk_id === $absence->gtk_id, 403, 'Pengajuan sendiri tidak dapat disetujui atau ditolak sendiri.');
        $data = Validator::make($input, [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ], ['review_notes.required_if' => 'Alasan penolakan wajib diisi.'])->validate();
        DB::transaction(function () use ($actor, $absence, $data) {
            Gtk::whereKey($absence->gtk_id)->lockForUpdate()->firstOrFail();
            $absence = GtkAbsenceRequest::whereKey($absence->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($absence);
            $before = $absence->toArray();
            if ($data['status'] === 'approved') {
                $this->assertAvailable($absence->gtk_id, $absence->start_date->toDateString(), $absence->end_date->toDateString(), $absence->id);
                foreach ($absence->start_date->toPeriod($absence->end_date) as $date) {
                    GtkAttendance::create(['gtk_id' => $absence->gtk_id, 'attendance_date' => $date->toDateString(),
                        'gtk_absence_request_id' => $absence->id, 'status' => $absence->attendanceStatus(),
                        'notes' => '['.$absence->type.'] '.GtkAbsenceRequest::TYPES[$absence->type].': '.$absence->description]);
                }
            }
            $absence->fill($data + ['reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            $this->audit($actor, $absence, 'absence_'.$data['status'], $before);
        });
    }

    public function cancel(User $actor, GtkAbsenceRequest $absence): void
    {
        abort_unless($actor->gtk_id && $actor->gtk_id === $absence->gtk_id, 403);
        DB::transaction(function () use ($actor, $absence) {
            Gtk::whereKey($absence->gtk_id)->lockForUpdate()->firstOrFail();
            $absence = GtkAbsenceRequest::whereKey($absence->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($absence);
            $before = $absence->toArray();
            $absence->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->audit($actor, $absence, 'absence_cancelled', $before);
        });
    }

    private function assertPending(GtkAbsenceRequest $absence): void
    {
        if ($absence->status !== 'pending') {
            throw ValidationException::withMessages(['absence' => 'Pengajuan sudah diproses. Muat ulang halaman untuk melihat status terbaru.']);
        }
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, GtkAbsenceRequest $absence, string $event, ?array $before): void
    {
        app(GtkJournalService::class)->audit($actor, $absence->gtk_id, $absence->start_date, $event, $before, $absence->toArray());
    }
}
