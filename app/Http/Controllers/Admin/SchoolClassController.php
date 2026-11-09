<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolClassController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id']]);
        $year = AcademicYear::find($request->query('academic_year_id')) ?? AcademicYear::where('is_active', true)->first();
        $search = trim((string) $request->query('q', ''));
        $classes = SchoolClass::with(['homeroomTeacher', 'room', 'academicYear'])
            ->where('academic_year_id', $year?->id)
            ->withCount(['studentClassHistories as active_students_count' => fn ($q) => $q->whereNull('voided_at')->where('is_active', true)])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('grade')->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.classes.index', compact('classes', 'search', 'year') + ['years' => AcademicYear::orderByDesc('start_date')->get()]);
    }

    public function create(Request $request)
    {
        return $this->form(new SchoolClass(['is_active' => true, 'grade' => 10,
            'academic_year_id' => $request->query('academic_year_id', AcademicYear::where('is_active', true)->value('id'))]));
    }

    public function edit(SchoolClass $class)
    {
        return $this->form($class);
    }

    private function form(SchoolClass $class)
    {
        return view('admin.classes.form', [
            'class' => $class, 'years' => AcademicYear::orderByDesc('start_date')->get(),
            'teachers' => Gtk::where('is_active', true)->orWhere('id', $class->homeroom_teacher_id)->orderBy('full_name')->get(),
            'rooms' => Room::where('is_active', true)->orWhere('id', $class->room_id)->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request, ?SchoolClass $class = null): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:50', Rule::unique('school_classes', 'name')->where('academic_year_id', $request->input('academic_year_id'))->ignore($class?->id)],
            'grade' => ['required', 'integer', 'in:10,11,12'], 'major' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'], 'is_active' => ['required', 'boolean'],
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:gtks,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);
    }

    public function store(Request $request)
    {
        $class = SchoolClass::create($this->validated($request));
        return to_route('admin.classes.show', $class)->with('status', 'Rombel tahunan dibuat.');
    }

    public function update(Request $request, SchoolClass $class)
    {
        $data = $this->validated($request, $class);
        DB::transaction(function () use ($class, $data) {
            $locked = SchoolClass::whereKey($class->id)->lockForUpdate()->firstOrFail();
            if ($locked->academic_year_id != $data['academic_year_id']) throw ValidationException::withMessages(['academic_year_id' => 'Tahun rombel tidak dapat diganti. Buat rombel baru untuk tahun berikutnya.']);
            if ($locked->grade != $data['grade'] && ($locked->studentClassHistories()->exists() || $locked->teachingAssignments()->exists())) throw ValidationException::withMessages(['grade' => 'Tingkat kelas sudah digunakan. Riwayat tidak boleh diubah.']);
            $histories = $locked->studentClassHistories()->whereNull('voided_at')->whereNotNull('starts_on')->get();
            if (! $data['is_active'] && $histories->where('is_active', true)->contains(fn ($h) => $h->ends_on->gte(today()))) throw ValidationException::withMessages(['is_active' => 'Rombel masih memiliki anggota yang berlaku. Selesaikan penempatan terlebih dahulu.']);
            if ($data['capacity'] ?? null) {
                foreach ($histories->pluck('starts_on')->unique() as $date) {
                    if ($histories->filter(fn ($h) => $h->starts_on->lte($date) && $h->ends_on->gte($date))->count() > $data['capacity']) throw ValidationException::withMessages(['capacity' => 'Kapasitas lebih kecil daripada penempatan yang sudah tercatat.']);
                }
            }
            $locked->update($data + ['historical_metadata_unknown' => false]);
        });
        return to_route('admin.classes.show', $class)->with('status', 'Pengaturan rombel diperbarui.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $yearId = $class->academic_year_id;
        DB::transaction(function () use ($class) {
            $class = SchoolClass::whereKey($class->id)->lockForUpdate()->firstOrFail();
            if ($class->studentClassHistories()->exists() || $class->teachingAssignments()->exists()) {
                throw ValidationException::withMessages([
                    'class' => 'Kelas sudah memiliki riwayat penempatan siswa atau pengampu sehingga tidak dapat dihapus. Gunakan status Arsip melalui Pengaturan Kelas setelah penempatan siswa diselesaikan.',
                ]);
            }
            $class->delete();
        });

        return to_route('admin.classes.index', ['academic_year_id' => $yearId])->with('status', 'Kelas berhasil dihapus.');
    }

    public function show(Request $request, SchoolClass $class)
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'view' => ['nullable', 'in:current,history']]);
        $year = $class->academicYear;
        $date = $request->query('date', $year ? max($year->start_date->toDateString(), min(today()->toDateString(), $year->end_date->toDateString())) : today()->toDateString());
        $history = $request->query('view') === 'history';
        $members = $class->studentClassHistories()->with('student')
            ->when(! $history, fn ($q) => $q->whereNull('voided_at')->where('starts_on', '<=', $date)->where('ends_on', '>=', $date))
            ->orderByDesc('starts_on')->get()->sortBy('student.full_name');
        return view('admin.classes.show', ['class' => $class->load(['homeroomTeacher', 'room']), 'year' => $year, 'date' => $date, 'history' => $history, 'members' => $members]);
    }

    public function assignments(Request $request)
    {
        $request->validate(['academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'], 'class_id' => ['nullable', 'integer', 'exists:school_classes,id']]);
        $year = AcademicYear::find($request->query('academic_year_id')) ?? AcademicYear::where('is_active', true)->first();
        $classes = SchoolClass::where('academic_year_id', $year?->id)->where('is_active', true)->orderBy('name')->get();
        $assignments = TeachingAssignment::with(['gtk', 'subject', 'schoolClass'])->where('academic_year_id', $year?->id)
            ->when($request->filled('class_id'), fn ($q) => $q->where('school_class_id', $request->query('class_id')))->orderBy('school_class_id')->get();
        return view('admin.classes.assignments', compact('year', 'classes', 'assignments') + [
            'assignmentErrors' => $request->session()->get('assignment_errors', $request->session()->get('errors', new \Illuminate\Support\ViewErrorBag)->all()),
            'years' => AcademicYear::orderByDesc('start_date')->get(), 'teachers' => Gtk::where('is_active', true)->orderBy('full_name')->get(),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function assignment(Request $request)
    {
        try {
            $data = $request->validate(['school_class_id' => ['required', 'exists:school_classes,id'], 'gtk_id' => ['required', Rule::exists('gtks','id')->where('is_active', true)], 'subject_id' => ['required', Rule::exists('subjects','id')->where('is_active', true)]]);
            $class = DB::transaction(function () use ($data) {
                $class = SchoolClass::whereKey($data['school_class_id'])->lockForUpdate()->firstOrFail();
                abort_unless($class->is_active && $class->academic_year_id, 422);
                if (TeachingAssignment::where('school_class_id', $class->id)
                    ->where('academic_year_id', $class->academic_year_id)
                    ->where('subject_id', $data['subject_id'])->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        'subject_id' => 'Mata pelajaran tersebut sudah memiliki guru pengampu pada kelas dan tahun pelajaran yang dipilih.',
                    ]);
                }
                TeachingAssignment::updateOrCreate($data + ['academic_year_id' => $class->academic_year_id], ['is_active' => true]);

                return $class;
            });
            return to_route('admin.schedules.assignments', ['academic_year_id' => $class->academic_year_id, 'class_id' => $class->id])->with('status', 'Pengampu disimpan. Atur hari dan jam pada Jadwal Pelajaran.');
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) throw $exception;

            return back()->withErrors($exception->errors())->withInput()
                ->with('assignment_errors', collect($exception->errors())->flatten()->all());
        }
    }
}
