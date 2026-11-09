<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Attendance tests require an isolated in-memory database.');
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
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_11_02_000002_make_kbm_independent_of_semesters.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->withoutVite();
    $this->travelTo(now()->startOfWeek()->setTime(8, 0));
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
    $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika']);
    $periods = collect();
    foreach (range(1, 4) as $number) {
        $periods->put($number, LessonPeriod::create(['period_number' => $number, 'name' => 'JP '.$number, 'start_time' => sprintf('%02d:00', $number + 6), 'end_time' => sprintf('%02d:45', $number + 6)]));
    }
    foreach (['mine', 'other'] as $key) {
        $gtk = Gtk::create(['full_name' => 'Guru '.$key]);
        $class = SchoolClass::create(['name' => 'Kelas '.$key, 'grade' => 10]);
        $user = User::factory()->create(['role' => 'teacher', 'gtk_id' => $gtk->id]);
        $assignment = TeachingAssignment::create([
            'gtk_id' => $gtk->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);
        $student = Student::create(['student_code' => $key, 'full_name' => 'Siswa '.$key]);
        StudentClassHistory::create(['student_id' => $student->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id]);
        $this->{$key} = compact('gtk', 'class', 'user', 'assignment', 'student');
    }
    // Interleaving schedules from other classes must not split one teacher's session.
    foreach ([['mine', 1], ['other', 1], ['mine', 2], ['mine', 4]] as [$key, $number]) {
        $schedule = Schedule::create(['teaching_assignment_id' => $this->{$key}['assignment']->id, 'lesson_period_id' => $periods[$number]->id, 'room_id' => $room->id, 'day_of_week' => 1]);
        $this->{$key.'Schedule'.$number} = $schedule;
    }
    $this->actingAs($this->mine['user']);
});

function recordHistoryAttendance(Schedule $schedule, Student $student, string $status = 'present', ?string $date = null): StudentAttendance
{
    return StudentAttendance::create([
        'schedule_id' => $schedule->id, 'student_id' => $student->id,
        'attendance_date' => $date ?? today()->toDateString(), 'status' => $status,
        'recorded_by' => auth()->id(), 'notes' => 'Catatan '.$student->full_name,
    ]);
}

it('provides role-specific history routes and denies cross-role access and guests', function () {
    foreach (['teacher', 'admin', 'headmaster', 'staff'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route($role.'.student-attendances.history'))->assertOk()->assertSee('Riwayat Presensi Siswa');
    }
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('teacher.student-attendances.history'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.student-attendances.history'))->assertRedirect(route('login'));
});

it('limits teacher history, filter options, summary and detail to their own assignments', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student'], 'present');
    recordHistoryAttendance($this->otherSchedule1, $this->other['student'], 'absent');
    $service = app(StudentAttendanceHistoryService::class);
    expect($service->classes($this->mine['user'])->pluck('id')->all())->toBe([$this->mine['class']->id]);
    Livewire::test('student-attendances.history')->assertSee('Kelas mine')->assertDontSee('Kelas other')
        ->assertViewHas('summary', fn ($summary) => ($summary['present'] ?? 0) === 1 && ! isset($summary['absent']))
        ->call('openSession', $this->mineSchedule1->id, today()->toDateString())
        ->assertSee('Catatan Siswa mine')->assertDontSee('Catatan Siswa other');
    Livewire::test('student-attendances.history')
        ->call('openSession', $this->otherSchedule1->id, today()->toDateString())->assertNotFound();
    Livewire::test('student-attendances.history')->set('schoolClassId', (string) $this->other['class']->id)
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 0);
});

it('lets admins staff and headmasters see all recorded sessions', function (string $role) {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student']);
    recordHistoryAttendance($this->otherSchedule1, $this->other['student'], 'sick');
    $this->actingAs(User::factory()->create(['role' => $role]));
    Livewire::test('student-attendances.history')->assertSee('Kelas mine')->assertSee('Kelas other')
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 2)
        ->call('openSession', $this->otherSchedule1->id, today()->toDateString())->assertSee('Siswa other');
})->with(['admin', 'staff', 'headmaster']);

it('changes history visibility when a dual-role user switches between teacher and admin', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student']);
    recordHistoryAttendance($this->otherSchedule1, $this->other['student']);
    $user = $this->mine['user'];
    $user->update(['secondary_role' => 'admin']);
    $service = app(StudentAttendanceHistoryService::class);
    expect($service->recordsForUser($user)->count())->toBe(1);
    $this->post(route('role.switch'), ['role' => 'admin'])->assertRedirect();
    expect($service->recordsForUser($user)->count())->toBe(2);
    $this->post(route('role.switch'), ['role' => 'teacher'])->assertRedirect();
    expect($service->recordsForUser($user)->count())->toBe(1);
    Livewire::test('student-attendances.history')->call('openSession', $this->otherSchedule1->id, today()->toDateString())->assertNotFound();
});

it('counts a two-period lesson once and keeps separate sessions separate', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student']);
    recordHistoryAttendance($this->mineSchedule4, $this->mine['student'], 'late');
    Livewire::test('student-attendances.history')
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 2 && $sessions->sum('total') === 2)
        ->assertViewHas('summary', fn ($summary) => $summary['present'] === 1 && $summary['late'] === 1);
    expect(StudentAttendance::where('schedule_id', $this->mineSchedule2->id)->count())->toBe(0);
});

it('filters dates, class, subject, status and student without changing stored records', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student'], 'sick');
    recordHistoryAttendance($this->mineSchedule4, $this->mine['student'], 'absent', today()->subDay()->toDateString());
    $student = Student::create(['student_code' => 'ADDITIONAL', 'full_name' => 'Siswa tambahan', 'nis' => '445566']);
    recordHistoryAttendance($this->mineSchedule1, $student, 'present');
    $subject = $this->mine['assignment']->subject_id;
    Livewire::test('student-attendances.history')->set('fromDate', today()->toDateString())
        ->set('schoolClassId', (string) $this->mine['class']->id)->set('subjectId', (string) $subject)
        ->set('status', 'sick')->set('search', 'Siswa mine')
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 1 && (int) $sessions->first()->total === 1)
        ->assertViewHas('summary', fn ($summary) => $summary === ['sick' => 1])
        ->set('status', '')->set('search', '445566')
        ->assertViewHas('summary', fn ($summary) => $summary === ['present' => 1])
        ->set('subjectId', '99999')->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 0);
    expect(StudentAttendance::count())->toBe(3);
});

it('includes archived semesters, schedules, students and assignments', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student']);
    $this->mineSchedule1->update(['is_active' => false]);
    $this->mine['assignment']->update(['is_active' => false]);
    $this->mine['student']->update(['is_active' => false]);
    AcademicYear::query()->update(['is_active' => false]);
    Semester::query()->update(['is_active' => false]);
    Livewire::test('student-attendances.history')
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 1)
        ->call('openSession', $this->mineSchedule1->id, today()->toDateString())->assertSee('Siswa mine');
});

it('shows all saved students in session detail even when the list has a status filter', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student'], 'sick');
    $student = Student::create(['student_code' => 'DETAIL', 'full_name' => 'Hadir dalam sesi']);
    recordHistoryAttendance($this->mineSchedule1, $student);
    Livewire::test('student-attendances.history')->set('status', 'sick')
        ->call('openSession', $this->mineSchedule1->id, today()->toDateString())
        ->assertViewHas('detailRecords', fn ($records) => $records->count() === 2)
        ->assertSee('Hadir dalam sesi')->call('closeSession')->assertSet('showSessionModal', false);
});

it('does not mark unrecorded students as absent or report attendance as complete', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student']);
    Livewire::test('student-attendances.history')
        ->assertSee('Sesi yang baru dipindai sebagian juga tampil')
        ->assertViewHas('summary', fn ($summary) => ! isset($summary['absent']));
});

it('handles invalid filters without an exception and resets to this month', function () {
    $component = Livewire::test('student-attendances.history')->set('fromDate', 'not-a-date')
        ->assertSee('Tanggal awal tidak valid.')->assertViewHas('sessions', null)
        ->set('fromDate', today()->toDateString())->set('toDate', today()->subDay()->toDateString())
        ->assertSee('Tanggal akhir tidak boleh sebelum tanggal awal.')
        ->call('resetFilters')->assertSet('fromDate', today()->startOfMonth()->toDateString())
        ->assertSet('toDate', today()->toDateString())->assertSet('status', '');
});

it('paginates sessions and resets pagination when filters change', function () {
    foreach (range(0, 12) as $days) {
        recordHistoryAttendance($this->mineSchedule1, $this->mine['student'], 'present', today()->subDays($days)->toDateString());
    }
    Livewire::test('student-attendances.history')->set('fromDate', today()->subDays(20)->toDateString())
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 13 && $sessions->count() === 12)
        ->call('setPage', 2)->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1)
        ->set('status', 'present')->assertSet('paginators.page', 1);
});

it('shows no records for teachers without a linked GTK and rejects malformed detail dates', function () {
    recordHistoryAttendance($this->mineSchedule1, $this->mine['student']);
    $this->actingAs(User::factory()->create(['role' => 'teacher', 'gtk_id' => null]));
    Livewire::test('student-attendances.history')->assertSee('belum terhubung')
        ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 0)
        ->call('openSession', $this->mineSchedule1->id, today()->toDateString())->assertNotFound();
    $this->actingAs($this->mine['user']);
    Livewire::test('student-attendances.history')->call('openSession', $this->mineSchedule1->id, 'invalid')->assertNotFound();
});
