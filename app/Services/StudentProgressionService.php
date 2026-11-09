<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentProgressionService
{
    public const ACTIONS = [
        'promote' => 'Naik kelas', 'repeat' => 'Tinggal kelas',
        'graduate' => 'Lulus', 'transfer' => 'Pindah', 'withdraw' => 'Keluar',
    ];

    public function validate(array $input): array
    {
        return Validator::make($input, [
            'source_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'source_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'destination_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['required', 'array:student_id,action,target_class_id'],
            'rows.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'rows.*.action' => ['required', 'in:'.implode(',', array_keys(self::ACTIONS))],
            'rows.*.target_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ])->validate();
    }

    public function preview(User $actor, array $input): array
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(fn () => $this->plan($this->validate($input)));
    }

    public function process(User $actor, array $input): int
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($actor, $input) {
            $data = $this->validate($input);
            // Lock classes in a stable order, then students, so capacity and enrollment are checked again.
            $ids = collect($data['rows'])->pluck('target_class_id')->filter()->push($data['source_class_id'])->unique()->sort();
            SchoolClass::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            Student::whereIn('id', collect($data['rows'])->pluck('student_id'))->orderBy('id')->lockForUpdate()->get();
            $plan = $this->plan($data);
            foreach ($plan['rows'] as $row) {
                $target = null;
                if (in_array($row['action'], ['promote', 'repeat'], true)) {
                    $target = StudentClassHistory::create([
                        'student_id' => $row['student']->id, 'school_class_id' => $row['target']->id,
                        'academic_year_id' => $plan['destinationYear']->id, 'is_active' => true,
                    ]);
                } else {
                    $row['student']->update([
                        'is_active' => false,
                        'exit_status' => ['graduate' => 'graduated', 'transfer' => 'transferred', 'withdraw' => 'withdrawn'][$row['action']],
                        'exit_date' => $data['effective_date'], 'exit_academic_year_id' => $plan['sourceYear']->id,
                    ]);
                    $row['history']->update(['is_active' => false]);
                }
                DB::table('student_progressions')->insert([
                    'student_id' => $row['student']->id, 'source_history_id' => $row['history']->id,
                    'destination_history_id' => $target?->id, 'processed_by' => $actor->id,
                    'action' => $row['action'], 'effective_date' => $data['effective_date'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return count($plan['rows']);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['rows' => $message]);
    }

    private function plan(array $data): array
    {
        $sourceYear = AcademicYear::findOrFail($data['source_year_id']);
        $sourceClass = SchoolClass::findOrFail($data['source_class_id']);
        $destinationYear = ! empty($data['destination_year_id']) ? AcademicYear::findOrFail($data['destination_year_id']) : null;
        $rows = [];
        $counts = [];
        foreach ($data['rows'] as $row) {
            $student = Student::findOrFail($row['student_id']);
            $history = StudentClassHistory::where('student_id', $student->id)
                ->where('school_class_id', $sourceClass->id)->where('academic_year_id', $sourceYear->id)
                ->where('is_active', true)->first();
            if (! $student->is_active || $student->exit_status || ! $history) {
                $this->fail($student->full_name.': siswa bukan anggota aktif kelas asal.');
            }
            if (DB::table('student_progressions')->where('source_history_id', $history->id)->exists()) {
                $this->fail($student->full_name.': sudah diproses untuk kelas dan tahun pelajaran asal ini.');
            }
            $action = $row['action'];
            $target = null;
            if (in_array($action, ['promote', 'repeat'], true)) {
                if (! $destinationYear || $destinationYear->start_date->lte($sourceYear->end_date)) {
                    $this->fail('Pilih tahun pelajaran tujuan setelah tahun pelajaran asal.');
                }
                $target = ! empty($row['target_class_id']) ? SchoolClass::findOrFail($row['target_class_id']) : null;
                $grade = $sourceClass->grade + ($action === 'promote' ? 1 : 0);
                if (! $target || ! $target->is_active || $target->grade != $grade || $grade > 12) {
                    $this->fail($student->full_name.': kelas tujuan harus aktif dan sesuai tingkat '.($action === 'promote' ? 'berikutnya.' : 'yang sama.'));
                }
                if (StudentClassHistory::where('student_id', $student->id)->where('academic_year_id', $destinationYear->id)->exists()) {
                    $this->fail($student->full_name.': sudah memiliki riwayat pada tahun pelajaran tujuan.');
                }
                // Do not insert older transitions behind an existing future enrollment.
                if ($student->classHistories()->whereHas('academicYear', fn ($q) => $q->where('start_date', '>', $sourceYear->start_date))->exists()) {
                    $this->fail($student->full_name.': sudah terdaftar pada tahun pelajaran yang lebih baru.');
                }
                $counts[$target->id] = ($counts[$target->id] ?? 0) + 1;
                $occupied = $target->studentClassHistories()->where('academic_year_id', $destinationYear->id)->where('is_active', true)->count();
                if ($target->capacity && $occupied + $counts[$target->id] > $target->capacity) {
                    $this->fail('Kapasitas kelas '.$target->name.' tidak mencukupi.');
                }
            } else {
                if ($action === 'graduate' && $sourceClass->grade != 12) {
                    $this->fail('Kelulusan hanya dapat diproses untuk kelas XII.');
                }
                if ($data['effective_date'] < $sourceYear->start_date->toDateString() || $data['effective_date'] > today()->toDateString()) {
                    $this->fail('Tanggal lulus/pindah/keluar harus sejak awal tahun pelajaran asal dan tidak melewati hari ini.');
                }
                if ($student->classHistories()->whereHas('academicYear', fn ($q) => $q->where('start_date', '>', $sourceYear->start_date))->exists()) {
                    $this->fail($student->full_name.': memiliki riwayat tahun pelajaran yang lebih baru.');
                }
            }
            $rows[] = compact('student', 'history', 'action', 'target');
        }
        return compact('sourceYear', 'sourceClass', 'destinationYear', 'rows');
    }
}
