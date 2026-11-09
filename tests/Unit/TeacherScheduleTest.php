<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\TeacherScheduleService;
use Carbon\Carbon;
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

it('opens the teaching schedule route and hides other teachers schedules', function () {
    $this->get(route('teacher.schedules.index'))->assertOk()->assertSee('Jadwal Mengajar Saya')
        ->assertSee('Kelas mine')->assertDontSee('Kelas other')->assertSee('Jadwal Mengajar');
    expect(app(TeacherScheduleService::class)->sessions($this->mine['user']))->toHaveCount(2);
});

it('groups consecutive periods once and reports weekly totals independent of the day filter', function () {
    Schedule::create([
        'teaching_assignment_id' => $this->mine['assignment']->id,
        'lesson_period_id' => $this->mineSchedule1->lesson_period_id,
        'room_id' => $this->mineSchedule1->room_id, 'day_of_week' => 2,
    ]);
    Livewire::test('teacher.schedules.index')->assertSee('2 JP')
        ->assertViewHas('summary', fn ($summary) => $summary === ['today' => 2, 'sessions' => 3, 'periods' => 4, 'classes' => 1])
        ->set('day', '2')->assertViewHas('visibleDays', [2 => 'Selasa'])
        ->assertViewHas('summary', fn ($summary) => $summary['sessions'] === 3)
        ->call('showToday')->assertSet('day', '1')->assertViewHas('visibleDays', [1 => 'Senin'])
        ->call('showWeek')->assertSet('day', '');
});

it('shows the full time range of a two-period session in chronological order', function () {
    $sessions = app(TeacherScheduleService::class)->sessions($this->mine['user']);
    expect($sessions->first()->schedules->pluck('id')->all())->toBe([$this->mineSchedule1->id, $this->mineSchedule2->id]);
    Livewire::test('teacher.schedules.index')->assertSee('07:00 – 08:45')->assertSee('JP 1')->assertSee('JP 2');
});

it('excludes inactive schedules and assignments and archived years', function (string $inactive) {
    match ($inactive) {
        'schedule' => Schedule::where('teaching_assignment_id', $this->mine['assignment']->id)->update(['is_active' => false]),
        'assignment' => $this->mine['assignment']->update(['is_active' => false]),
        'year' => AcademicYear::query()->update(['is_active' => false]),
    };
    Livewire::test('teacher.schedules.index')->assertSee('Belum ada jadwal mengajar aktif')
        ->assertViewHas('summary', fn ($summary) => $summary['sessions'] === 0);
})->with(['schedule', 'assignment', 'year']);

it('keeps the same teaching schedule when a legacy semester is inactive or ends', function () {
    $semester = Semester::create(['academic_year_id' => $this->mine['assignment']->academic_year_id,
        'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => false]);
    $this->mine['assignment']->update(['semester_id' => $semester->id]);
    $this->travelTo(Carbon::parse('2027-01-04 08:00:00'));
    expect(app(TeacherScheduleService::class)->sessions($this->mine['user']))->toHaveCount(2);
});

it('excludes inactive lesson periods and breaks', function () {
    LessonPeriod::where('id', $this->mineSchedule2->lesson_period_id)->update(['is_break' => true]);
    LessonPeriod::where('id', $this->mineSchedule4->lesson_period_id)->update(['is_active' => false]);
    Livewire::test('teacher.schedules.index')->assertViewHas('summary', fn ($summary) => $summary['periods'] === 1);
});

it('handles unlinked teachers, invalid day filters and Sundays gracefully', function () {
    Livewire::test('teacher.schedules.index')->set('day', 'invalid')->assertSee('Pilihan hari tidak valid.')
        ->call('showWeek')->assertSet('day', '');
    $this->travelTo(today()->endOfWeek()->setTime(8, 0));
    Livewire::test('teacher.schedules.index')->call('showToday')->assertSet('day', '7')
        ->assertSee('Tidak ada jadwal mengajar pada hari Minggu.');
    $this->actingAs(User::factory()->create(['role' => 'teacher', 'gtk_id' => null]));
    $this->get(route('teacher.schedules.index'))->assertOk()->assertSee('belum terhubung');
});

it('restricts the teaching schedule to teachers', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->get(route('teacher.schedules.index'))->assertForbidden();
    Livewire::test('teacher.schedules.index')->assertForbidden();
})->with(['admin', 'staff']);

it('lets a headmaster teach while keeping schedules scoped to their GTK', function () {
    $user = $this->mine['user'];
    $user->update(['role' => 'headmaster']);
    $this->actingAs($user);
    $this->get(route('teacher.schedules.index'))->assertOk()->assertSee('Kelas mine')->assertDontSee('Kelas other');
    expect(app(TeacherScheduleService::class)->sessions($user))->toHaveCount(2);
    $user->update(['gtk_id' => null]);
    expect(app(TeacherScheduleService::class)->sessions($user))->toHaveCount(0);
});
