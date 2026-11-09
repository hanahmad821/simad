<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\StudentClassHistory;
use App\Services\StudentProgressionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentProgressionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'source_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'source_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);
        $year = isset($data['source_year_id']) ? AcademicYear::findOrFail($data['source_year_id']) : AcademicYear::where('is_active', true)->first();
        $class = isset($data['source_class_id']) ? SchoolClass::findOrFail($data['source_class_id']) : null;
        $members = $year && $class ? StudentClassHistory::with('student')
            ->where('academic_year_id', $year->id)->where('school_class_id', $class->id)->where('is_active', true)
            ->whereHas('student', fn ($q) => $q->where('is_active', true)->whereNull('exit_status'))
            ->whereNotIn('id', DB::table('student_progressions')->select('source_history_id'))
            ->get()->sortBy('student.full_name') : collect();

        return view('admin.student-progressions.index', [
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'classes' => SchoolClass::orderBy('grade')->orderBy('name')->get(),
            'sourceYear' => $year, 'sourceClass' => $class, 'members' => $members,
            'actions' => StudentProgressionService::ACTIONS,
            'recent' => DB::table('student_progressions as p')
                ->join('students as s', 's.id', '=', 'p.student_id')
                ->join('users as u', 'u.id', '=', 'p.processed_by')
                ->join('student_class_histories as h', 'h.id', '=', 'p.source_history_id')
                ->join('academic_years as y', 'y.id', '=', 'h.academic_year_id')
                ->join('school_classes as c', 'c.id', '=', 'h.school_class_id')
                ->leftJoin('student_class_histories as dh', 'dh.id', '=', 'p.destination_history_id')
                ->leftJoin('school_classes as dc', 'dc.id', '=', 'dh.school_class_id')
                ->leftJoin('academic_years as dy', 'dy.id', '=', 'dh.academic_year_id')
                ->select('p.*', 's.full_name', 'u.name as actor', 'y.name as year_name', 'c.name as class_name', 'dc.name as destination_class', 'dy.name as destination_year')
                ->orderByDesc('p.id')->limit(30)->get(),
        ]);
    }

    public function preview(Request $request, StudentProgressionService $service)
    {
        $input = $request->validate([
            'selected' => ['required', 'array', 'min:1', 'max:500'],
            'selected.*' => ['required', 'integer', 'distinct'],
            'default_action' => ['required', 'in:'.implode(',', array_keys(StudentProgressionService::ACTIONS))],
            'default_target_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'choices' => ['required', 'array'],
        ]);
        $data = $request->only('source_year_id', 'source_class_id', 'destination_year_id', 'effective_date');
        $data['rows'] = [];
        foreach ($input['selected'] as $id) {
            $choice = $input['choices'][$id] ?? [];
            $data['rows'][] = [
                'student_id' => $id,
                'action' => ($choice['action'] ?? '') ?: $input['default_action'],
                'target_class_id' => ($choice['target_class_id'] ?? '') ?: ($input['default_target_class_id'] ?? null),
            ];
        }
        $plan = $service->preview($request->user(), $data);
        $token = Crypt::encryptString(json_encode(['actor' => $request->user()->id, 'expires' => now()->addHour()->timestamp, 'data' => $data], JSON_THROW_ON_ERROR));

        return view('admin.student-progressions.preview', compact('plan', 'token', 'data'));
    }

    public function store(Request $request, StudentProgressionService $service)
    {
        $request->validate(['token' => ['required', 'string']]);
        try {
            $payload = json_decode(Crypt::decryptString($request->string('token')->toString()), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            return to_route('admin.student-progressions.index')->withErrors(['token' => 'Ringkasan tidak valid. Tampilkan ringkasan kembali.']);
        }
        abort_unless(($payload['actor'] ?? null) === $request->user()->id, 403);
        if (($payload['expires'] ?? 0) < now()->timestamp) {
            return to_route('admin.student-progressions.index')->withErrors(['token' => 'Ringkasan sudah kedaluwarsa. Tampilkan ringkasan kembali.']);
        }
        try {
            $count = $service->process($request->user(), $payload['data']);
        } catch (ValidationException $e) {
            return to_route('admin.student-progressions.index', [
                'source_year_id' => $payload['data']['source_year_id'],
                'source_class_id' => $payload['data']['source_class_id'],
            ])->withErrors($e->errors());
        }
        return to_route('admin.student-progressions.index', [
            'source_year_id' => $payload['data']['source_year_id'],
            'source_class_id' => $payload['data']['source_class_id'],
        ])->with('status', $count.' siswa berhasil diproses. Riwayat kelas dan presensi lama tetap tersimpan.');
    }

    public function createYear(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ]);
        AcademicYear::create($data + ['is_active' => false]);
        return to_route('admin.student-progressions.index')->with('status', 'Tahun pelajaran dibuat. Tahun aktif tidak berubah.');
    }

    public function activateYear(Request $request)
    {
        $data = $request->validate(['year_id' => ['required', 'integer', 'exists:academic_years,id']]);
        DB::transaction(function () use ($data) {
            AcademicYear::orderBy('id')->lockForUpdate()->get();
            AcademicYear::query()->update(['is_active' => false]);
            AcademicYear::whereKey($data['year_id'])->update(['is_active' => true]);
        });
        return to_route('admin.student-progressions.index')->with('status', 'Tahun pelajaran aktif diperbarui.');
    }
}
