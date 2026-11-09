<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentClassHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentLifecycleService
{
    public const LABELS = ['promote' => 'Naik kelas', 'repeat' => 'Tinggal kelas', 'graduate' => 'Lulus',
        'defer' => 'Ditunda', 'transfer' => 'Pindah sekolah', 'withdraw' => 'Keluar',
        'enroll' => 'Penempatan awal', 'move' => 'Pindah rombel', 'correct' => 'Koreksi', 'reconcile' => 'Rekonsiliasi'];

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['management' => $message]);
    }

    public function studentIds(array $data): array
    {
        if (($data['kind'] ?? '') === 'reconcile') return [(int) DB::table('student_data_issues')->where('id', $data['issue_id'] ?? 0)->value('student_id')];
        if (($data['kind'] ?? '') === 'correct') {
            return [(int) DB::table('student_management_events')->where('id', $data['event_id'] ?? 0)->value('student_id')];
        }
        return array_values(array_unique(array_map('intval', $data['student_ids'] ?? array_column($data['rows'] ?? [], 'student_id'))));
    }

    public function preview(User $actor, array $data): array
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(fn () => $this->plan($data));
    }

    public function commit(User $actor, array $data, string $key, string $fingerprint): string
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($actor, $data, $key, $fingerprint) {
            // The same lock ordering is used for all membership changes and class settings.
            SchoolClass::orderBy('id')->lockForUpdate()->get();
            Student::whereIn('id', $this->studentIds($data))->orderBy('id')->lockForUpdate()->get();
            if ($batch = DB::table('student_management_batches')->find($key)) {
                abort_unless($batch->actor_id === $actor->id, 403);
                return $key;
            }
            $plan = $this->plan($data);
            if (! hash_equals($fingerprint, $plan['fingerprint'])) {
                $this->fail('Data berubah setelah ringkasan ditampilkan. Periksa pilihan dan tinjau kembali.');
            }
            DB::table('student_management_batches')->insert([
                'id' => $key, 'actor_id' => $actor->id, 'kind' => $data['kind'], 'request' => json_encode($data),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($plan['items'] as $item) {
                $before = $this->snapshot($item['student_id']);
                $destinationId = null;
                if ($data['kind'] === 'correct') {
                    $this->restore($item);
                } else {
                    foreach ($item['history_updates'] as $id => $values) {
                        DB::table('student_class_histories')->where('id', $id)->update($values + ['updated_at' => now()]);
                    }
                    if ($item['new_history']) {
                        $destinationId = DB::table('student_class_histories')->insertGetId($item['new_history'] + ['created_at' => now(), 'updated_at' => now()]);
                    }
                    if ($item['student_update']) Student::whereKey($item['student_id'])->update($item['student_update']);
                }
                if (isset($item['resolve_issue'])) {
                    DB::table('student_data_issues')->where('id', $item['resolve_issue'])->update(['resolved_at' => now(), 'updated_at' => now()]);
                    if (isset($item['decision'])) DB::table('student_year_decisions')->updateOrInsert(
                        ['student_id' => $item['student_id'], 'academic_year_id' => $item['year_id']],
                        $item['decision'] + ['updated_at' => now(), 'created_at' => now()]
                    );
                }
                $after = $this->snapshot($item['student_id']);
                $eventId = DB::table('student_management_events')->insertGetId([
                    'batch_id' => $key, 'student_id' => $item['student_id'], 'academic_year_id' => $item['year_id'],
                    'action' => $item['action'], 'reason' => $item['reason'] ?: null,
                    'before' => json_encode($before), 'after' => json_encode($after),
                    'corrects_event_id' => $item['corrects'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($data['kind'] === 'year_end') {
                    DB::table('student_year_decisions')->updateOrInsert(
                        ['student_id' => $item['student_id'], 'academic_year_id' => $item['year_id']],
                        ['source_history_id' => $item['source_history_id'], 'destination_history_id' => $destinationId,
                            'event_id' => $eventId, 'action' => $item['action'], 'reason' => $item['reason'],
                            'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
            return $key;
        });
    }

    public function snapshot(int $studentId): array
    {
        $student = Student::findOrFail($studentId);
        return [
            'student' => $student->only('id', 'is_active', 'exit_status', 'exit_date', 'exit_academic_year_id', 'exit_reason', 'destination_school'),
            'histories' => DB::table('student_class_histories')->where('student_id', $studentId)->orderBy('id')->get()->map(fn ($h) => (array) $h)->all(),
            'decisions' => DB::table('student_year_decisions')->where('student_id', $studentId)->orderBy('id')->get()->map(fn ($d) => (array) $d)->all(),
        ];
    }

    private function plan(array $data): array
    {
        Validator::make($data, [
            'kind' => ['required', 'in:year_end,enroll,move,status,correct,reconcile'],
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $kind = $data['kind'];
        $items = [];
        if ($kind === 'reconcile') {
            $items[] = $this->reconciliation($data);
        } elseif ($kind === 'correct') {
            $items[] = $this->correction($data);
        } elseif ($kind === 'year_end') {
            Validator::make($data, [
                'source_class_id' => ['required', 'integer', 'exists:school_classes,id'],
                'rows' => ['required', 'array', 'min:1', 'max:500'],
                'rows.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id'],
                'rows.*.action' => ['required', 'in:promote,repeat,graduate,defer'],
                'rows.*.target_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
                'rows.*.reason' => ['nullable', 'string', 'max:2000'],
            ])->validate();
            $source = SchoolClass::with('academicYear')->findOrFail($data['source_class_id']);
            if (! $source->academicYear) $this->fail('Rombel asal belum memiliki tahun pelajaran.');
            if ($data['date'] < $source->academicYear->start_date->toDateString() || $data['date'] > $source->academicYear->end_date->toDateString() || $data['date'] > today()->toDateString()) $this->fail('Tanggal keputusan harus berada dalam tahun asal dan tidak di masa depan.');
            foreach ($data['rows'] as $row) {
                $student = $this->eligible($row['student_id']);
                $history = $this->currentHistory($student->id, $source->academic_year_id);
                if (! $history || $history->school_class_id !== $source->id) $this->fail($student->full_name.': bukan anggota terakhir kelas asal.');
                $decision = DB::table('student_year_decisions')->where('student_id', $student->id)->where('academic_year_id', $source->academic_year_id)->first();
                if ($decision && $decision->action !== 'defer') $this->fail($student->full_name.': keputusan tahun ini sudah final.');
                $item = $this->item($student, $source, $history, $row['action'], $row['reason'] ?? '');
                if ($row['action'] === 'defer') {
                    if (trim($item['reason']) === '') $this->fail($student->full_name.': alasan penundaan wajib diisi.');
                } elseif ($row['action'] === 'graduate') {
                    if ($source->grade !== 12) $this->fail('Kelulusan hanya untuk kelas XII.');
                    $this->exitChanges($item, $student, $history, $source, $data['date'], 'graduated', null);
                } else {
                    $next = AcademicYear::where('start_date', '>', $source->academicYear->end_date)->orderBy('start_date')->first();
                    $target = SchoolClass::with('academicYear')->find($row['target_class_id'] ?? null);
                    if (! $next || ! $target || $target->academic_year_id !== $next->id || ! $target->is_active) $this->fail($student->full_name.': pilih rombel aktif pada tahun pelajaran berikutnya.');
                    $grade = $source->grade + ($row['action'] === 'promote' ? 1 : 0);
                    if ($grade > 12 || $target->grade !== $grade) $this->fail($student->full_name.': tingkat kelas tujuan tidak sesuai hasil.');
                    if ($student->classHistories()->whereNull('voided_at')->whereHas('academicYear', fn ($q) => $q->where('start_date', '>', $source->academicYear->start_date))->exists()) $this->fail($student->full_name.': sudah memiliki penempatan tahun berikutnya.');
                    $item['new_history'] = $this->newHistory($student->id, $target, $next->start_date->toDateString());
                    $item['target'] = $target->name.' · '.$next->name;
                }
                $items[] = $item;
            }
        } else {
            Validator::make($data, ['student_ids' => ['required', 'array', 'min:1', 'max:500'], 'student_ids.*' => ['integer', 'distinct', 'exists:students,id']])->validate();
            if (in_array($kind, ['move', 'status']) && trim($data['reason'] ?? '') === '') $this->fail('Alasan wajib diisi.');
            if ($kind === 'status' && count($data['student_ids']) !== 1) $this->fail('Perubahan status dilakukan untuk satu siswa.');
            foreach ($data['student_ids'] as $id) {
                $student = $this->eligible($id);
                if ($kind === 'status') {
                    Validator::make($data, ['action' => ['required', 'in:transfer,withdraw'], 'destination_school' => ['required_if:action,transfer', 'nullable', 'string', 'max:255']])->validate();
                    $history = $student->classHistories()->whereNull('voided_at')->where('starts_on', '<=', $data['date'])->where('ends_on', '>=', $data['date'])->latest('starts_on')->first();
                    if (! $history) $this->fail('Tidak ada penempatan pada tanggal keluar. Periksa tanggal dan keanggotaan siswa.');
                    $source = $history->schoolClass()->with('academicYear')->firstOrFail();
                    $item = $this->item($student, $source, $history, $data['action'], $data['reason']);
                    $this->exitChanges($item, $student, $history, $source, $data['date'], $data['action'] === 'transfer' ? 'transferred' : 'withdrawn', $data['destination_school'] ?? null);
                } else {
                    Validator::make($data, ['target_class_id' => ['required', 'integer', 'exists:school_classes,id']])->validate();
                    $target = SchoolClass::with('academicYear')->findOrFail($data['target_class_id']);
                    if (! $target->is_active || ! $target->academicYear) $this->fail('Rombel tujuan harus aktif dan memiliki tahun pelajaran.');
                    $year = $target->academicYear;
                    if ($data['date'] < $year->start_date->toDateString() || $data['date'] > $year->end_date->toDateString()) $this->fail('Tanggal penempatan harus berada dalam tahun pelajaran rombel tujuan.');
                    $history = $this->currentHistory($student->id, $year->id);
                    if ($kind === 'enroll') {
                        if ($history) $this->fail($student->full_name.': sudah memiliki penempatan. Gunakan Pindah Rombel.');
                        if ($student->classHistories()->whereNull('voided_at')->whereHas('academicYear', fn ($q) => $q->where('start_date', '<', $year->start_date))->exists()) $this->fail($student->full_name.': gunakan Proses Akhir Tahun untuk penempatan lanjutan.');
                        $item = $this->item($student, $target, null, 'enroll', $data['reason'] ?? '');
                    } else {
                        if (! $history || $history->school_class_id != ($data['source_class_id'] ?? 0)) $this->fail($student->full_name.': kelas asal tidak sesuai penempatan terakhir.');
                        $source = $history->schoolClass;
                        if ($source->id === $target->id || $source->grade !== $target->grade) $this->fail('Tujuan pindah rombel harus berbeda dengan tingkat yang sama.');
                        if ($data['date'] <= $history->starts_on->toDateString() || $data['date'] > today()->toDateString()) $this->fail('Tanggal pindah harus setelah awal penempatan dan tidak melewati hari ini.');
                        if (DB::table('student_year_decisions')->where('student_id', $id)->where('academic_year_id', $year->id)->where('action', '!=', 'defer')->exists()) $this->fail('Keputusan akhir tahun sudah final. Koreksi keputusan terkait dahulu.');
                        $this->noAttendanceAfter($student, $data['date'], true);
                        $item = $this->item($student, $source, $history, 'move', $data['reason']);
                        $item['history_updates'][$history->id] = ['ends_on' => Carbon::parse($data['date'])->subDay()->toDateString(), 'is_active' => false];
                    }
                    $item['new_history'] = $this->newHistory($id, $target, $data['date']);
                    $item['target'] = $target->name.' · '.$year->name;
                }
                $items[] = $item;
            }
        }
        // Simulate every affected class, including restored reservations and reconciled intervals.
        $updates = [];
        foreach ($items as $item) {
            $updates += $item['history_updates'];
            if (isset($item['restore'])) {
                foreach (DB::table('student_class_histories')->where('student_id', $item['student_id'])->get() as $h) $updates[$h->id] = ['voided_at' => now()];
                foreach ($item['restore']['histories'] as $h) $updates[$h['id']] = $h;
            }
        }
        $additions = collect($items)->pluck('new_history')->filter()->values();
        $classIds = $additions->pluck('school_class_id')->merge(DB::table('student_class_histories')->whereIn('id', array_keys($updates))->pluck('school_class_id'))->unique();
        foreach ($classIds as $classId) {
            $class = SchoolClass::findOrFail($classId);
            if (! $class->capacity) continue;
            $all = DB::table('student_class_histories')->where('school_class_id', $classId)->get()
                ->map(fn ($h) => array_merge((array) $h, $updates[$h->id] ?? []))
                ->concat($additions->where('school_class_id', $classId))
                ->filter(fn ($h) => empty($h['voided_at']) && $h['starts_on'] && $h['ends_on']);
            foreach ($all->pluck('starts_on')->unique() as $date) {
                if ($all->filter(fn ($h) => $h['starts_on'] <= $date && $h['ends_on'] >= $date)->count() > $class->capacity) $this->fail('Kapasitas '.$class->name.' tidak mencukupi pada '.$date.'.');
            }
        }
        $ids = array_column($items, 'student_id');
        $snapshot = [
            'students' => Student::whereIn('id', $ids)->orderBy('id')->get()->toArray(),
            'histories' => DB::table('student_class_histories')->whereIn('student_id', $ids)->orderBy('id')->get()->toArray(),
            'decisions' => DB::table('student_year_decisions')->whereIn('student_id', $ids)->orderBy('id')->get()->toArray(),
            'attendance' => DB::table('student_attendances')->whereIn('student_id', $ids)->orderBy('id')->get()->toArray(),
            'classes' => SchoolClass::orderBy('id')->get()->toArray(),
            'years' => AcademicYear::orderBy('id')->get()->toArray(),
            'counts' => DB::table('student_class_histories')->whereNull('voided_at')->select('school_class_id')->selectRaw('COUNT(*) as total')->groupBy('school_class_id')->orderBy('school_class_id')->get()->toArray(),
        ];
        return ['items' => $items, 'fingerprint' => hash('sha256', json_encode($snapshot)), 'data' => $data];
    }

    private function reconciliation(array $data): array
    {
        Validator::make($data, [
            'issue_id' => ['required', 'integer', 'exists:student_data_issues,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'intervals' => ['required', 'array', 'min:1'],
            'intervals.*.id' => ['required', 'integer', 'distinct'],
            'intervals.*.starts_on' => ['nullable', 'date_format:Y-m-d'],
            'intervals.*.ends_on' => ['nullable', 'date_format:Y-m-d'],
            'intervals.*.void' => ['nullable', 'boolean'],
        ])->validate();
        $issue = DB::table('student_data_issues')->find($data['issue_id']);
        if ($issue->resolved_at) $this->fail('Masalah data ini sudah diselesaikan.');
        $student = Student::findOrFail($issue->student_id);
        $year = AcademicYear::findOrFail($issue->academic_year_id);
        $histories = $student->classHistories()->where('academic_year_id', $year->id)->get()->keyBy('id');
        $ids = collect($data['intervals'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($ids !== $histories->keys()->sort()->values()->all()) $this->fail('Semua riwayat pada tahun ini harus diperiksa.');
        $source = $histories->first()?->schoolClass;
        if (! $source) $this->fail('Riwayat kelas tidak ditemukan.');
        $item = $this->item($student, $source, null, 'reconcile', $data['reason']);
        $valid = [];
        foreach ($data['intervals'] as $row) {
            $h = $histories[$row['id']];
            if (! empty($row['void'])) {
                if (DB::table('student_year_decisions')->where('source_history_id', $h->id)->orWhere('destination_history_id', $h->id)->exists()) $this->fail('Riwayat yang menjadi dasar keputusan akhir tahun tidak boleh dibatalkan.');
                $item['history_updates'][$h->id] = ['voided_at' => now()->toDateTimeString(), 'is_active' => false];
                continue;
            }
            $start = $row['starts_on'] ?? '';
            $end = $row['ends_on'] ?? '';
            if (! $start || ! $end || $start > $end || $start < $year->start_date->toDateString() || $end > $year->end_date->toDateString()) $this->fail('Isi interval yang valid di dalam tahun pelajaran.');
            if ($student->exit_date && $end > $student->exit_date->toDateString()) $this->fail('Interval tidak boleh melewati tanggal keluar siswa.');
            foreach ($valid as $other) if ($start <= $other['ends_on'] && $end >= $other['starts_on']) $this->fail('Interval keanggotaan saling bertumpang tindih.');
            $valid[] = ['school_class_id' => $h->school_class_id, 'starts_on' => $start, 'ends_on' => $end];
            $item['history_updates'][$h->id] = ['starts_on' => $start, 'ends_on' => $end, 'voided_at' => null, 'is_active' => false];
        }
        if (! $valid) $this->fail('Pertahankan sedikitnya satu interval yang sah.');
        foreach ($student->attendances()->with('schedule.teachingAssignment')->get() as $attendance) {
            $assignment = $attendance->schedule?->teachingAssignment;
            if ($assignment?->academic_year_id !== $year->id) continue;
            $date = $attendance->attendance_date->toDateString();
            if (! collect($valid)->contains(fn ($h) => $h['school_class_id'] === $assignment->school_class_id && $h['starts_on'] <= $date && $h['ends_on'] >= $date)) $this->fail('Interval tidak sesuai presensi '.$date.'. Riwayat presensi harus tetap didukung penempatan yang benar.');
        }
        $latest = collect($data['intervals'])->filter(fn ($r) => empty($r['void']))->sortByDesc('starts_on')->first();
        $item['history_updates'][$latest['id']]['is_active'] = $student->is_active;
        // Duplicate legacy decisions require an explicit choice; preserve all original events.
        if (str_contains($issue->description, 'Keputusan')) {
            $event = DB::table('student_management_events')->where('student_id', $student->id)->where('academic_year_id', $year->id)
                ->whereIn('action', ['promote', 'repeat', 'graduate'])->find($data['decision_event_id'] ?? 0);
            if (! $event) $this->fail('Pilih keputusan lama yang dinyatakan sah.');
            $old = json_decode($event->after, true);
            if (! isset($old['source_history_id'])) $this->fail('Pilih catatan migrasi keputusan lama.');
            $item['decision'] = ['source_history_id' => $old['source_history_id'], 'destination_history_id' => $old['destination_history_id'],
                'action' => $event->action, 'event_id' => $event->id, 'reason' => $data['reason']];
        }
        $item['resolve_issue'] = $issue->id;
        $item['target'] = 'Tetapkan '.count($valid).' interval sesuai hasil pemeriksaan';
        return $item;
    }

    private function eligible(int $id): Student
    {
        $student = Student::findOrFail($id);
        if (! $student->is_active || $student->isArchived()) $this->fail($student->full_name.': siswa tidak aktif.');
        if (DB::table('student_data_issues')->where('student_id', $id)->whereNull('resolved_at')->exists()) $this->fail($student->full_name.': riwayat lama perlu direkonsiliasi pada halaman Masalah Data.');
        return $student;
    }

    private function currentHistory(int $student, int $year): ?StudentClassHistory
    {
        return StudentClassHistory::where('student_id', $student)->where('academic_year_id', $year)->whereNull('voided_at')->whereNotNull('starts_on')->latest('starts_on')->latest('id')->first();
    }

    private function newHistory(int $student, SchoolClass $class, string $date): array
    {
        $end = $class->academicYear->end_date->toDateString();
        if (StudentClassHistory::where('student_id', $student)->where('academic_year_id', $class->academic_year_id)->whereNull('voided_at')
            ->where('starts_on', '<=', $end)->where('ends_on', '>=', $date)->exists()) {
            // A move explicitly closes its original interval in the same transaction.
            $overlaps = StudentClassHistory::where('student_id', $student)->where('academic_year_id', $class->academic_year_id)->whereNull('voided_at')
                ->where('starts_on', '<=', $end)->where('ends_on', '>=', $date)->get();
            if ($overlaps->count() !== 1 || $overlaps->first()->school_class_id === $class->id || $overlaps->first()->starts_on->toDateString() >= $date) $this->fail('Penempatan tujuan bertumpang tindih.');
        }
        return ['student_id' => $student, 'school_class_id' => $class->id, 'academic_year_id' => $class->academic_year_id,
            'starts_on' => $date, 'ends_on' => $end, 'is_active' => true];
    }

    private function item(Student $student, SchoolClass $source, ?StudentClassHistory $history, string $action, string $reason): array
    {
        return ['student_id' => $student->id, 'name' => $student->full_name, 'year_id' => $source->academic_year_id,
            'source' => $source->name.' · '.$source->academicYear?->name, 'source_history_id' => $history?->id,
            'action' => $action, 'reason' => $reason, 'target' => self::LABELS[$action],
            'history_updates' => [], 'student_update' => [], 'new_history' => null];
    }

    private function noAttendanceAfter(Student $student, string $date, bool $inclusive = false): void
    {
        $conflict = $student->attendances()->whereDate('attendance_date', $inclusive ? '>=' : '>', $date)->orderBy('attendance_date')->first();
        if ($conflict) $this->fail($student->full_name.': terdapat presensi pada '.$conflict->attendance_date->format('d/m/Y').'. Periksa tanggal perubahan.');
    }

    private function exitChanges(array &$item, Student $student, StudentClassHistory $history, SchoolClass $source, string $date, string $status, ?string $school): void
    {
        if ($date < $history->starts_on->toDateString() || $date > $history->ends_on->toDateString() || $date > today()->toDateString()) $this->fail('Tanggal keluar/lulus harus dalam penempatan asal dan tidak di masa depan.');
        $this->noAttendanceAfter($student, $date);
        $later = $student->classHistories()->whereNull('voided_at')->where('starts_on', '>', $date)->get();
        foreach ($later as $future) {
            if (DB::table('student_year_decisions')->where('student_id', $student->id)->where('academic_year_id', $future->academic_year_id)->exists()) $this->fail('Ada keputusan lanjutan di tahun tujuan; koreksi keputusan itu dahulu.');
            $item['history_updates'][$future->id] = ['voided_at' => now()->toDateTimeString(), 'is_active' => false];
            $item['target'] .= ' · Batalkan penempatan '.$future->schoolClass->name;
        }
        $item['history_updates'][$history->id] = ['ends_on' => $date, 'is_active' => false];
        $item['student_update'] = ['is_active' => false, 'exit_status' => $status, 'exit_date' => $date,
            'exit_academic_year_id' => $source->academic_year_id, 'exit_reason' => $item['reason'] ?: null, 'destination_school' => $school];
    }

    private function correction(array $data): array
    {
        Validator::make($data, ['event_id' => ['required', 'integer', 'exists:student_management_events,id'], 'reason' => ['required', 'string', 'max:2000']])->validate();
        $event = DB::table('student_management_events')->find($data['event_id']);
        if (! $event->student_id || in_array($event->action, ['correct', 'reconcile'])) $this->fail('Catatan ini bukan hasil siswa yang dapat dikoreksi.');
        if (DB::table('student_management_events')->where('corrects_event_id', $event->id)->exists()) $this->fail('Hasil ini sudah dikoreksi.');
        $before = json_decode($event->before, true);
        $after = json_decode($event->after, true);
        if (empty($before['student']) || empty($after['histories'])) $this->fail('Proses lama tidak memiliki snapshot lengkap. Rekonsiliasi data diperlukan; pembatalan otomatis tidak tersedia.');
        $newer = DB::table('student_management_events')->where('student_id', $event->student_id)->where('id', '>', $event->id)->where('action', '!=', 'correct')
            ->whereNotIn('id', DB::table('student_management_events')->whereNotNull('corrects_event_id')->select('corrects_event_id'))->exists();
        if ($newer) $this->fail('Terdapat proses siswa yang lebih baru. Koreksi dari proses terbaru terlebih dahulu.');
        $newIds = array_diff(array_column($after['histories'], 'id'), array_column($before['histories'], 'id'));
        foreach ($after['histories'] as $history) {
            if (! in_array($history['id'], $newIds)) continue;
            if (StudentAttendance::where('student_id', $event->student_id)->whereHas('schedule.teachingAssignment', fn ($q) => $q->where('school_class_id', $history['school_class_id'])->where('academic_year_id', $history['academic_year_id']))->exists()) $this->fail('Kelas tujuan sudah memiliki presensi. Gunakan Pindah Rombel dengan tanggal baru untuk koreksi penempatan.');
        }
        $student = Student::findOrFail($event->student_id);
        $source = SchoolClass::with('academicYear')->findOrFail($before['histories'][0]['school_class_id'] ?? $after['histories'][0]['school_class_id']);
        $item = $this->item($student, $source, null, 'correct', $data['reason']);
        $item['restore'] = $before;
        $item['corrects'] = $event->id;
        $item['target'] = 'Pulihkan kondisi sebelum '.(self::LABELS[$event->action] ?? $event->action).'; penempatan baru tanpa aktivitas dibatalkan.';
        return $item;
    }

    private function restore(array $item): void
    {
        $before = $item['restore'];
        $ids = array_column($before['histories'], 'id');
        DB::table('student_class_histories')->where('student_id', $item['student_id'])->whereNotIn('id', $ids)->whereNull('voided_at')->update(['voided_at' => now(), 'is_active' => false, 'updated_at' => now()]);
        foreach ($before['histories'] as $history) {
            DB::table('student_class_histories')->where('id', $history['id'])->update(collect($history)->only('starts_on', 'ends_on', 'is_active', 'voided_at')->all() + ['updated_at' => now()]);
        }
        $state = $before['student'];
        unset($state['id']);
        if ($state['exit_date']) $state['exit_date'] = Carbon::parse($state['exit_date'])->toDateString();
        Student::whereKey($item['student_id'])->update($state);
        DB::table('student_year_decisions')->where('student_id', $item['student_id'])->delete();
        foreach ($before['decisions'] as $decision) {
            unset($decision['id']);
            DB::table('student_year_decisions')->insert($decision);
        }
    }
}
