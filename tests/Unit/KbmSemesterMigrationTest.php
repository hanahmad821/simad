<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\GtkWorkJournal;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentClassHistory;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\StudentAttendanceHistoryService;
use App\Services\TeacherScheduleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Migration tests require an isolated in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_020757_create_academic_years_table.php',
        '2026_08_26_021543_create_semesters_table.php',
        '2026_08_26_025138_create_school_classes_table.php',
        '2026_08_26_025918_create_subjects_table.php',
        '2026_08_26_064302_create_lesson_periods_table.php',
        '2026_11_02_000004_add_daily_kbm_time_patterns.php',
        '2026_11_02_000005_normalize_lesson_period_sort_order.php',
        '2026_08_26_064539_create_rooms_table.php',
        '2026_08_26_071443_create_teaching_assignments_table.php',
        '2026_08_26_073224_create_schedules_table.php',
        '2026_08_26_074302_create_students_table.php',
        '2026_08_26_075134_create_student_class_histories_table.php',
        '2026_08_26_075839_create_student_attendances_table.php',
        '2026_10_27_000003_create_gtk_work_journals.php',
    ] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
});

it('carries forward one annual version while retaining old schedules attendance and journal snapshots', function (bool $hasActiveSemester) {
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $teacher = Gtk::create(['full_name' => 'Guru Satu']);
    $user = User::factory()->create(['role' => 'teacher', 'gtk_id' => $teacher->id]);
    $class = SchoolClass::create(['name' => 'X A', 'grade' => 10]);
    $student = Student::create(['student_code' => 'LEGACY', 'full_name' => 'Siswa Satu']);
    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika']);
    $period = LessonPeriod::create(['name' => 'JP 1', 'period_number' => 1, 'start_time' => '07:00', 'end_time' => '07:45']);
    $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
    $assignments = collect();
    $memberships = collect();
    $schedules = collect();
    foreach (['Ganjil' => ['2026-07-01', '2026-12-31'], 'Genap' => ['2027-01-01', '2027-06-30']] as $name => [$start, $end]) {
        $semester = Semester::create(['academic_year_id' => $year->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end,
            'is_active' => $hasActiveSemester && $name === 'Ganjil']);
        $assignment = TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id,
            'academic_year_id' => $year->id, 'semester_id' => $semester->id, 'teaching_hours' => 2]);
        $assignments->push($assignment);
        $memberships->push(StudentClassHistory::create(['student_id' => $student->id, 'school_class_id' => $class->id,
            'academic_year_id' => $year->id, 'semester_id' => $semester->id]));
        $schedule = Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => $period->id, 'room_id' => $room->id, 'day_of_week' => 1]);
        $schedules->push($schedule);
        StudentAttendance::create(['schedule_id' => $schedule->id, 'student_id' => $student->id, 'attendance_date' => $start, 'status' => 'present']);
        GtkWorkJournal::create(['gtk_id' => $teacher->id, 'journal_date' => $start, 'session_key' => 'kbm-'.$schedule->id,
            'kind' => 'teaching', 'label' => 'Matematika / X A / JP 1', 'schedule_ids' => [$schedule->id], 'material' => 'Materi '.$name]);
    }
    $attendanceBefore = DB::table('student_attendances')->orderBy('id')->get()->toJson();
    $journalsBefore = DB::table('gtk_work_journals')->orderBy('id')->get()->toJson();
    (require database_path('migrations/2026_11_02_000002_make_kbm_independent_of_semesters.php'))->up();
    $kept = $hasActiveSemester ? 0 : 1;
    expect(TeachingAssignment::count())->toBe(2)->and(StudentClassHistory::count())->toBe(2)
        ->and(TeachingAssignment::where('is_active', true)->pluck('id')->all())->toBe([$assignments[$kept]->id])
        ->and(StudentClassHistory::where('is_active', true)->pluck('id')->all())->toBe([$memberships[$kept]->id])
        ->and(Schedule::count())->toBe(2)
        ->and(DB::table('student_attendances')->orderBy('id')->get()->toJson())->toBe($attendanceBefore)
        ->and(DB::table('gtk_work_journals')->orderBy('id')->get()->toJson())->toBe($journalsBefore);
    expect(app(TeacherScheduleService::class)->sessions($user))->toHaveCount(1);
    expect(app(StudentAttendanceHistoryService::class)->sessionRecords($user, $schedules[1 - $kept]->id,
        $hasActiveSemester ? '2027-01-01' : '2026-07-01'))->toHaveCount(1);
    $newStudent = Student::create(['student_code' => 'NEW', 'full_name' => 'Siswa Baru']);
    $membership = StudentClassHistory::create(['student_id' => $newStudent->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id]);
    $newSubject = Subject::create(['code' => 'BIO', 'name' => 'Biologi']);
    $assignment = TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $newSubject->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id]);
    expect($membership->fresh()->semester_id)->toBeNull()->and($assignment->fresh()->semester_id)->toBeNull();
})->with([true, false]);
