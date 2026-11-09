<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudentLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentManagementController extends Controller
{
    private function draft(Request $request): array
    {
        $id = $request->query('draft');
        return $id ? ($request->session()->get('student_management.'.$id.'.data', [])) : [];
    }

    public function index(Request $request)
    {
        $request->validate(['source_year_id' => ['nullable', 'integer', 'exists:academic_years,id'], 'source_class_id' => ['nullable', 'integer', 'exists:school_classes,id']]);
        $draft = $this->draft($request);
        $year = AcademicYear::find($request->query('source_year_id', $draft['source_year_id'] ?? null)) ?? AcademicYear::where('is_active', true)->first();
        $classes = SchoolClass::with('academicYear')->where('academic_year_id', $year?->id)->orderBy('grade')->orderBy('name')->get();
        $source = $classes->firstWhere('id', (int) $request->query('source_class_id', $draft['source_class_id'] ?? 0));
        $decisions = DB::table('student_year_decisions')->where('academic_year_id', $year?->id)->get()->keyBy('student_id');
        $histories = StudentClassHistory::with('student')->where('academic_year_id', $year?->id)->whereNull('voided_at')
            ->orderByDesc('starts_on')->orderByDesc('id')->get()->unique('student_id');
        $progress = $classes->mapWithKeys(function ($class) use ($histories, $decisions) {
            $members = $histories->where('school_class_id', $class->id)->filter(fn ($h) => $h->student->is_active || isset($decisions[$h->student_id]));
            $final = $members->filter(fn ($h) => isset($decisions[$h->student_id]) && $decisions[$h->student_id]->action !== 'defer')->count();
            $deferred = $members->filter(fn ($h) => ($decisions[$h->student_id]->action ?? null) === 'defer')->count();
            return [$class->id => ['total' => $members->count(), 'final' => $final, 'deferred' => $deferred, 'pending' => $members->count() - $final - $deferred]];
        });
        $saved = collect($draft['rows'] ?? [])->keyBy('student_id');
        $rows = $source ? $histories->where('school_class_id', $source->id)
            ->filter(fn ($h) => $h->student->is_active || isset($decisions[$h->student_id]))
            ->sortBy('student.full_name')->map(function ($h) use ($decisions, $saved) {
                $decision = $decisions[$h->student_id] ?? null;
                return [
                    'student_id' => $h->student_id, 'name' => $h->student->full_name, 'nis' => $h->student->nis,
                    'final' => $decision && $decision->action !== 'defer',
                    'action' => $saved[$h->student_id]['action'] ?? ($decision->action ?? ''),
                    'target_class_id' => (string) ($saved[$h->student_id]['target_class_id'] ?? ($decision?->destination_history_id ? StudentClassHistory::find($decision->destination_history_id)?->school_class_id : '')),
                    'reason' => $saved[$h->student_id]['reason'] ?? ($decision->reason ?? ''), 'selected' => false,
                ];
            })->values()->all() : [];
        $next = $year ? AcademicYear::where('start_date', '>', $year->end_date)->orderBy('start_date')->first() : null;
        return view('admin.student-management.year-end', [
            'years' => AcademicYear::orderByDesc('start_date')->get(), 'year' => $year, 'classes' => $classes,
            'source' => $source, 'progress' => $progress, 'rows' => $rows, 'next' => $next,
            'targets' => SchoolClass::where('academic_year_id', $next?->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'grade']),
            'date' => $draft['date'] ?? today()->toDateString(),
        ]);
    }

    public function status(Request $request, Student $student)
    {
        return view('admin.student-management.status', ['student' => $student, 'draft' => $this->draft($request),
            'histories' => $student->classHistories()->with(['schoolClass', 'academicYear'])->whereNull('voided_at')->orderByDesc('starts_on')->get()]);
    }

    public function placement(Request $request, SchoolClass $class)
    {
        $request->validate(['mode' => ['nullable', 'in:enroll,move'], 'q' => ['nullable', 'string', 'max:150']]);
        $draft = $this->draft($request);
        $mode = $request->query('mode', $draft['kind'] ?? 'enroll');
        $year = $class->academicYear;
        abort_unless($year, 422, 'Rombel belum memiliki tahun pelajaran.');
        $students = Student::where('is_active', true)->whereNull('exit_status')
            ->when($mode === 'move',
                fn ($q) => $q->whereHas('classHistories', fn ($h) => $h->where('school_class_id', $class->id)->whereNull('voided_at')->where('is_active', true)),
                fn ($q) => $q->whereDoesntHave('classHistories', fn ($h) => $h->whereNull('voided_at')->where('academic_year_id', $year->id))
                    ->whereDoesntHave('classHistories', fn ($h) => $h->whereNull('voided_at')->whereHas('academicYear', fn ($y) => $y->where('start_date', '<', $year->start_date))))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('full_name', 'like', '%'.$request->query('q').'%')->orWhere('nis', 'like', '%'.$request->query('q').'%')))
            ->orderBy('full_name')->limit(500)->get();
        return view('admin.student-management.placement', compact('class', 'year', 'students', 'mode', 'draft') + [
            'targets' => SchoolClass::where('academic_year_id', $year->id)->where('grade', $class->grade)->where('is_active', true)->where('id', '!=', $class->id)->get(),
        ]);
    }

    private function editUrl(array $data, ?string $draft = null): string
    {
        return match ($data['kind']) {
            'year_end' => route('admin.student-progressions.index', ['source_class_id' => $data['source_class_id'], 'source_year_id' => SchoolClass::find($data['source_class_id'])?->academic_year_id, 'draft' => $draft]),
            'status' => route('admin.students.status', ['student' => $data['student_ids'][0], 'draft' => $draft]),
            'enroll', 'move' => route('admin.classes.placement', ['class' => $data['source_class_id'] ?? $data['target_class_id'], 'mode' => $data['kind'], 'draft' => $draft]),
            'reconcile' => route('admin.student-management.reconcile', ['issue' => $data['issue_id'], 'draft' => $draft]),
            default => route('admin.student-management.history', ['draft' => $draft]),
        };
    }

    public function preview(Request $request, StudentLifecycleService $service)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:year_end,status,enroll,move,correct,reconcile'],
            'source_class_id' => ['required_if:kind,year_end,move', 'nullable', 'integer', 'exists:school_classes,id'],
            'target_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'student_ids' => ['required_if:kind,status,enroll,move', 'nullable', 'array', 'min:1', 'max:500'], 'student_ids.*' => ['integer', 'exists:students,id'],
            'date' => ['required', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'max:2000'],
            'destination_school' => ['nullable', 'string', 'max:255'], 'action' => ['nullable', 'string'],
            'event_id' => ['nullable', 'integer', 'exists:student_management_events,id'],
            'rows' => ['nullable', 'array', 'max:500'], 'rows.*' => ['array'],
            'issue_id' => ['nullable', 'integer', 'exists:student_data_issues,id'], 'intervals' => ['nullable', 'array', 'max:500'], 'decision_event_id' => ['nullable', 'integer'],
        ]);
        $data['rows'] = collect($data['rows'] ?? [])->filter(fn ($r) => ! empty($r['action']))->values()->all();
        $id = (string) Str::uuid();
        $request->session()->put('student_management.'.$id, ['data' => $data, 'actor' => $request->user()->id, 'expires' => now()->addHour()->timestamp]);
        try {
            $plan = $service->preview($request->user(), $data);
        } catch (ValidationException $e) {
            return redirect($this->editUrl($data, $id))->withErrors($e->errors())->withInput();
        }
        $request->session()->put('student_management.'.$id.'.fingerprint', $plan['fingerprint']);
        return to_route('admin.student-management.review', $id);
    }

    public function review(Request $request, string $draft, StudentLifecycleService $service)
    {
        $state = $request->session()->get('student_management.'.$draft);
        abort_unless($state && isset($state['fingerprint']) && $state['actor'] === $request->user()->id, 404);
        try {
            $plan = $service->preview($request->user(), $state['data']);
            if (! hash_equals($state['fingerprint'], $plan['fingerprint'])) throw ValidationException::withMessages(['management' => 'Data berubah. Tinjau ulang pilihan.']);
        } catch (ValidationException $e) {
            return redirect($this->editUrl($state['data'], $draft))->withErrors($e->errors());
        }
        return view('admin.student-management.review', ['plan' => $plan, 'draftId' => $draft, 'back' => $this->editUrl($state['data'], $draft)]);
    }

    public function commit(Request $request, string $draft, StudentLifecycleService $service)
    {
        $state = $request->session()->get('student_management.'.$draft);
        abort_unless($state && isset($state['fingerprint']) && $state['actor'] === $request->user()->id, 404);
        if ($state['expires'] < now()->timestamp) return redirect($this->editUrl($state['data'], $draft))->withErrors(['management' => 'Ringkasan kedaluwarsa. Tinjau kembali sebelum menyimpan.']);
        try {
            $batch = $service->commit($request->user(), $state['data'], $draft, $state['fingerprint']);
        } catch (ValidationException $e) {
            return redirect($this->editUrl($state['data'], $draft))->withErrors($e->errors());
        }
        return to_route('admin.student-management.history', ['batch' => $batch])->with('status', 'Perubahan tersimpan. Pilihan Ditunda tetap perlu ditindaklanjuti.');
    }

    public function history(Request $request)
    {
        $events = DB::table('student_management_events as e')
            ->join('student_management_batches as b', 'b.id', '=', 'e.batch_id')
            ->join('users as u', 'u.id', '=', 'b.actor_id')
            ->leftJoin('students as s', 's.id', '=', 'e.student_id')
            ->leftJoin('academic_years as y', 'y.id', '=', 'e.academic_year_id')
            ->select('e.*', 'b.kind', 'u.name as actor', 's.full_name', 'y.name as year_name')
            ->when($request->filled('q'), fn ($q) => $q->where('s.full_name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('batch'), fn ($q) => $q->where('e.batch_id', $request->query('batch')))
            ->when($request->filled('year_id'), fn ($q) => $q->where('e.academic_year_id', $request->query('year_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('e.action', $request->query('action')))
            ->orderByDesc('e.id')->paginate(30)->withQueryString();
        $corrected = DB::table('student_management_events')->whereNotNull('corrects_event_id')->pluck('corrects_event_id')->all();
        return view('admin.student-management.history', compact('events', 'corrected') + ['draft' => $this->draft($request), 'years' => AcademicYear::orderByDesc('start_date')->get()]);
    }

    public function years()
    {
        $years = AcademicYear::orderByDesc('start_date')->get();
        $readiness = $years->mapWithKeys(fn ($year) => [$year->id => $this->readiness($year)]);
        return view('admin.student-management.years', compact('years', 'readiness'));
    }

    private function readiness(AcademicYear $year): array
    {
        $classes = SchoolClass::where('academic_year_id', $year->id)->where('is_active', true);
        $unplaced = Student::where('is_active', true)->whereDoesntHave('classHistories', fn ($q) => $q->where('academic_year_id', $year->id)->whereNull('voided_at')->where('is_active', true))->count();
        $previous = AcademicYear::where('end_date', '<', $year->start_date)->orderByDesc('end_date')->first();
        $pending = $previous ? Student::where('is_active', true)->whereHas('classHistories', fn ($q) => $q->where('academic_year_id', $previous->id)->whereNull('voided_at'))
            ->whereNotIn('id', DB::table('student_year_decisions')->where('academic_year_id', $previous->id)->where('action', '!=', 'defer')->select('student_id'))->count() : 0;
        $deferred = $previous ? DB::table('student_year_decisions')->where('academic_year_id', $previous->id)->where('action', 'defer')->count() : 0;
        return ['classes' => (clone $classes)->count(), 'unplaced' => $unplaced, 'pending' => max(0, $pending - $deferred), 'deferred' => $deferred,
            'unscheduled' => (clone $classes)->whereDoesntHave('teachingAssignments.schedules', fn ($q) => $q->where('is_active', true))->count()];
    }

    public function createYear(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date']]);
        if (AcademicYear::where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists()) return back()->withErrors(['start_date' => 'Periode bertumpang tindih dengan tahun pelajaran yang sudah ada.'])->withInput();
        AcademicYear::create($data + ['is_active' => false]);
        return to_route('admin.academic-years.index')->with('status', 'Tahun pelajaran dibuat. Siapkan rombel sebelum aktivasi.');
    }

    public function activateYear(Request $request)
    {
        $data = $request->validate(['year_id' => ['required', 'integer', 'exists:academic_years,id'], 'confirmed' => ['accepted'], 'reason' => ['nullable', 'string', 'max:2000']]);
        return DB::transaction(function () use ($data, $request) {
            AcademicYear::orderBy('id')->lockForUpdate()->get();
            SchoolClass::orderBy('id')->lockForUpdate()->get();
            $year = AcademicYear::findOrFail($data['year_id']);
            $readiness = $this->readiness($year);
            if (! $readiness['classes']) return back()->withErrors(['year_id' => 'Buat rombel aktif pada tahun tujuan terlebih dahulu.']);
            if (($readiness['unplaced'] + $readiness['pending'] + $readiness['deferred'] + $readiness['unscheduled']) > 0 && trim($data['reason'] ?? '') === '') return back()->withErrors(['reason' => 'Masih ada persiapan belum selesai. Isi alasan aktivasi.']);
            $before = AcademicYear::where('is_active', true)->pluck('id')->all();
            AcademicYear::query()->update(['is_active' => false]);
            $year->update(['is_active' => true]);
            $key = (string) Str::uuid();
            DB::table('student_management_batches')->insert(['id' => $key, 'actor_id' => $request->user()->id, 'kind' => 'activate_year', 'request' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('student_management_events')->insert(['batch_id' => $key, 'academic_year_id' => $year->id, 'action' => 'activate_year', 'reason' => $data['reason'] ?? null, 'before' => json_encode($before), 'after' => json_encode($readiness), 'created_at' => now(), 'updated_at' => now()]);
            return to_route('admin.academic-years.index')->with('status', 'Tahun aktif diperbarui. Siswa belum ditempatkan tetap perlu ditindaklanjuti.');
        });
    }

    public function reconcile(Request $request, int $issue)
    {
        $issue = DB::table('student_data_issues')->find($issue);
        abort_unless($issue && ! $issue->resolved_at, 404);
        return view('admin.student-management.reconcile', [
            'issue' => $issue, 'student' => Student::findOrFail($issue->student_id),
            'year' => AcademicYear::findOrFail($issue->academic_year_id),
            'histories' => StudentClassHistory::with('schoolClass')->where('student_id', $issue->student_id)->where('academic_year_id', $issue->academic_year_id)->get(),
            'events' => DB::table('student_management_events')->where('student_id', $issue->student_id)->where('academic_year_id', $issue->academic_year_id)->whereIn('action', ['promote', 'repeat', 'graduate'])->get(),
            'draft' => $this->draft($request),
        ]);
    }

    public function issues()
    {
        $issues = DB::table('student_data_issues as i')->leftJoin('students as s', 's.id', '=', 'i.student_id')
            ->leftJoin('academic_years as y', 'y.id', '=', 'i.academic_year_id')->select('i.*', 's.full_name', 'y.name as year_name')
            ->whereNull('resolved_at')->orderBy('i.id')->paginate(30);
        return view('admin.student-management.issues', compact('issues'));
    }
}
