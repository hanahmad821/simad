<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\StudentCardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class StudentCardController extends Controller
{
    public function __invoke(Request $request, StudentCardService $cards): Response
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all', 'graduated', 'transferred', 'withdrawn'])],
        ]);
        $status = $filters['status'] ?? 'active';
        $classId = $filters['school_class_id'] ?? null;

        $students = Student::query()->with([
            'classHistories' => fn ($query) => $query
                ->when(! in_array($status, ['graduated', 'transferred', 'withdrawn'], true), fn ($history) => $history
                    ->where('is_active', true)->whereHas('academicYear', fn ($year) => $year->where('is_active', true)))
                ->with(['schoolClass', 'academicYear'])->latest('id'),
        ])->when(! empty($filters['student_id']), fn ($query) => $query->whereKey($filters['student_id']))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->when(in_array($status, ['graduated', 'transferred', 'withdrawn'], true), fn ($query) => $query->where('exit_status', $status))
            ->when($classId, fn ($query) => $query->whereHas('classHistories', fn ($history) => $history
                ->where('school_class_id', $classId)
                ->when(! in_array($status, ['graduated', 'transferred', 'withdrawn'], true), fn ($history) => $history
                    ->where('is_active', true)->whereHas('academicYear', fn ($year) => $year->where('is_active', true)))))
            ->when(! empty($filters['search']), fn ($query) => $query->where(function ($student) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $student->where('full_name', 'like', $term)->orWhere('nis', 'like', $term)
                    ->orWhere('nisn', 'like', $term)->orWhere('nik', 'like', $term);
            }))->orderBy('full_name')->get();

        $qrCodes = $students->mapWithKeys(fn ($student) => [$student->id => $cards->qrCodeDataUri($student->student_code)]);

        return response()->view('admin.students.cards', [
            'students' => $students,
            'qrCodes' => $qrCodes,
            'madikaLogo' => $cards->madikaLogoDataUri(),
            'school' => SchoolSetting::first(),
            'classes' => SchoolClass::where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
        ])->header('Cache-Control', 'private, no-store');
    }
}
