<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);
        $search = trim($filters['q'] ?? '');
        $academicYears = AcademicYear::orderByDesc('start_date')->get();
        $academicYear = isset($filters['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $filters['academic_year_id'])
            : $academicYears->firstWhere('is_active', true);

        $subjects = Subject::query()
            ->withCount('teachingAssignments')
            ->with(['teachingAssignments' => fn ($query) => $query
                ->where('academic_year_id', $academicYear?->id)
                ->where('is_active', true)
                ->with(['gtk', 'schoolClass'])])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.subjects.index', compact('subjects', 'search', 'academicYears', 'academicYear'));
    }

    public function create(): View
    {
        return view('admin.subjects.form', ['subject' => new Subject(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Subject::create($this->validated($request));

        return to_route('admin.subjects.index')->with('status', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function edit(Subject $subject): View
    {
        return view('admin.subjects.form', compact('subject'));
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validated($request, $subject));

        return to_route('admin.subjects.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }

    private function validated(Request $request, ?Subject $subject = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($subject?->id)],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        DB::transaction(function () use ($subject) {
            $subject = Subject::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($subject->teachingAssignments()->exists()) {
                throw ValidationException::withMessages(['subject' => 'Mata pelajaran sudah digunakan dalam penugasan mengajar. Ubah status menjadi nonaktif melalui Edit agar riwayat tetap tersimpan.']);
            }
            $subject->delete();
        });

        return to_route('admin.subjects.index')->with('status', 'Mata pelajaran berhasil dihapus.');
    }
}
