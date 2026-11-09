<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\GtkWorkJournal;
use App\Models\KbmDayPattern;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\KbmTimeService;
use App\Services\ScheduleSessionService;
use App\Services\StudentAttendanceSessionService;
use App\Services\TeacherScheduleService;
use Carbon\Carbon;
use Database\Seeders\KbmTimeSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Time pattern tests require an isolated in-memory database.');
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
        '2026_08_26_064539_create_rooms_table.php',
        '2026_08_26_071443_create_teaching_assignments_table.php',
        '2026_08_26_073224_create_schedules_table.php',
        '2026_08_26_074302_create_students_table.php',
        '2026_08_26_075134_create_student_class_histories_table.php',
        '2026_08_26_075839_create_student_attendances_table.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_10_27_000003_create_gtk_work_journals.php',
        '2026_11_02_000002_make_kbm_independent_of_semesters.php',
        '2026_11_02_000003_add_room_to_school_classes.php',
        '2026_11_02_000004_add_daily_kbm_time_patterns.php',
        '2026_11_02_000005_normalize_lesson_period_sort_order.php',
    ] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $this->times = app(KbmTimeService::class);
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $gtk = Gtk::create(['full_name' => 'Guru Waktu']);
    $this->teacher = User::factory()->create(['role' => 'teacher', 'gtk_id' => $gtk->id]);
    $this->room = Room::create(['code' => 'R1', 'name' => 'X A']);
    $class = SchoolClass::create(['name' => 'X A', 'grade' => 10, 'room_id' => $this->room->id]);
    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika']);
    $this->assignment = TeachingAssignment::create(['gtk_id' => $gtk->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'academic_year_id' => $year->id]);
});

it('builds exact 35 minute lessons with the requested morning and break times', function (int $day, string $first, string $last, int $count) {
    $slots = collect($this->times->slots($this->times->defaults($day)));
    $lessons = $slots->where('is_break', false)->where('period_number', '>', 0)->values();
    expect($lessons)->toHaveCount($count)->and($lessons->first()['start_time'])->toBe($first)
        ->and($lessons->last()['end_time'])->toBe($last)->and($lessons->pluck('period_number')->all())->toBe(range(1, $count));
    foreach ($lessons as $lesson) {
        expect(Carbon::parse($lesson['start_time'])->diffInMinutes(Carbon::parse($lesson['end_time'])))->toEqual(35);
    }
    foreach ($this->times->defaults($day)['breaks'] as $index => $break) {
        $slot = $slots->firstWhere('name', 'Istirahat '.($index + 1));
        expect($slot['start_time'])->toBe($break['start'])->and($slot['end_time'])->toBe($break['end'])->and($slot['period_number'])->toBe(0);
    }
})->with([[1, '07:30', '13:55', 9], [2, '07:30', '13:55', 9], [3, '07:30', '13:55', 9],
    [4, '07:50', '14:00', 9], [5, '08:00', '11:15', 5], [6, '07:30', '13:55', 9]]);

it('seeds each day independently and preserves configured times on repeated seeding', function () {
    $this->seed(KbmTimeSeeder::class);
    expect(KbmDayPattern::count())->toBe(6)->and(LessonPeriod::count())->toBe(67);
    $this->times->save($this->admin, [5], array_replace($this->times->defaults(5), ['teaching_periods' => 6]));
    $this->seed(KbmTimeSeeder::class);
    expect(KbmDayPattern::where('day_of_week', 5)->first()->teaching_periods)->toBe(6)
        ->and(LessonPeriod::where('day_of_week', 5)->where('period_number', '>', 0)->count())->toBe(6);
});

it('saves one lesson across a break with two teaching slots and one attendance session', function () {
    $this->seed(KbmTimeSeeder::class);
    $service = app(ScheduleSessionService::class);
    $jp3 = LessonPeriod::where('day_of_week', 5)->where('period_number', 3)->first();
    $jp4 = LessonPeriod::where('day_of_week', 5)->where('period_number', 4)->first();
    expect($service->periodSpan($jp3->id, 2, 5)->pluck('id')->all())->toBe([$jp3->id, $jp4->id]);
    expect(fn () => $service->periodSpan($jp3->id, 1, 4))->toThrow(ValidationException::class);
    $span = $service->periodSpan($jp4->id, 2, 5);
    expect(substr($span->first()->start_time, 0, 5))->toBe('10:05')->and(substr($span->last()->end_time, 0, 5))->toBe('11:15');
    Livewire::test('admin.schedules.index')->call('createSchedule', 5, $jp3->id)
        ->set('teachingAssignmentId', $this->assignment->id)->set('duration', 2)
        ->assertSee('Jeda istirahat 1: 09:45')->call('saveSchedule')->assertHasNoErrors()
        ->assertSee('Lanjutan setelah istirahat')->assertDontSee('rowspan="2"', false);
    $schedules = Schedule::with(['lessonPeriod', 'room', 'teachingAssignment'])->get();
    $sessions = $service->group($schedules);
    expect($schedules)->toHaveCount(2)->and($schedules->pluck('lesson_period_id')->all())->toBe([$jp3->id, $jp4->id])
        ->and($sessions)->toHaveCount(1)->and($sessions->first()->segments)->toHaveCount(2)
        ->and($sessions->first()->segments->pluck('schedules')->map->count()->all())->toBe([1, 1]);
    expect(app(TeacherScheduleService::class)->sessions($this->teacher))->toHaveCount(1);
    expect(app(StudentAttendanceSessionService::class)->forUser($this->teacher, '2026-11-06'))->toHaveCount(1);
    expect($this->assignment->fresh()->scheduled_hours)->toBe(2);
    $this->actingAs($this->teacher)->get(route('teacher.schedules.index'))->assertOk()
        ->assertSee('Pelajaran berlanjut setelah istirahat')->assertSee('09:10')->assertSee('10:40');
    $this->actingAs($this->admin);
    Livewire::test('admin.schedules.index')->call('editSchedule', $schedules->last()->id)
        ->assertSet('editingScheduleId', $schedules->first()->id)->assertSet('duration', 2)
        ->assertSet('lessonPeriodId', $jp3->id)->call('saveSchedule')->assertHasNoErrors()
        ->call('deleteSchedule', $schedules->last()->id);
    expect(Schedule::count())->toBe(0);
});

it('allows a session across two breaks without counting either break as JP', function () {
    $this->seed(KbmTimeSeeder::class);
    $start = LessonPeriod::where('day_of_week', 1)->where('period_number', 4)->first();
    $span = app(ScheduleSessionService::class)->periodSpan($start->id, 5, 1);
    expect($span->pluck('period_number')->all())->toBe([4, 5, 6, 7, 8])
        ->and($span->contains('is_break', true))->toBeFalse()
        ->and(substr($span->last()->end_time, 0, 5))->toBe('13:20');
});

it('checks conflicts after the break and rolls back the entire requested session', function () {
    $this->seed(KbmTimeSeeder::class);
    $jp3 = LessonPeriod::where('day_of_week', 5)->where('period_number', 3)->first();
    $jp4 = LessonPeriod::where('day_of_week', 5)->where('period_number', 4)->first();
    $subject = Subject::create(['code' => 'IPA', 'name' => 'IPA']);
    $assignment = TeachingAssignment::create([
        'gtk_id' => $this->assignment->gtk_id, 'school_class_id' => $this->assignment->school_class_id,
        'subject_id' => $subject->id, 'academic_year_id' => $this->assignment->academic_year_id,
    ]);
    $existing = Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => $jp4->id,
        'room_id' => $this->room->id, 'day_of_week' => 5]);
    Livewire::test('admin.schedules.index')->call('createSchedule', 5, $jp3->id)
        ->set('teachingAssignmentId', $this->assignment->id)->set('duration', 2)
        ->call('saveSchedule')->assertHasErrors('lessonPeriodId');
    expect(Schedule::pluck('id')->all())->toBe([$existing->id]);
});

it('preserves an existing attendance anchor when adding a lesson before a break', function () {
    $this->seed(KbmTimeSeeder::class);
    $jp3 = LessonPeriod::where('day_of_week', 5)->where('period_number', 3)->first();
    $jp4 = LessonPeriod::where('day_of_week', 5)->where('period_number', 4)->first();
    $existing = Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $jp4->id,
        'room_id' => $this->room->id, 'day_of_week' => 5]);
    $student = Student::create(['student_code' => 'ANCHOR', 'full_name' => 'Siswa Jeda']);
    StudentAttendance::create(['schedule_id' => $existing->id, 'student_id' => $student->id,
        'attendance_date' => '2026-11-06', 'status' => 'present']);
    Livewire::test('admin.schedules.index')->call('createSchedule', 5, $jp3->id)
        ->set('teachingAssignmentId', $this->assignment->id)->call('saveSchedule')->assertHasErrors('schedule');
    expect(Schedule::pluck('id')->all())->toBe([$existing->id])
        ->and(StudentAttendance::first()->schedule_id)->toBe($existing->id);
});

it('rejects unavailable teaching periods and unconfigured gaps even across a break', function () {
    $this->seed(KbmTimeSeeder::class);
    $service = app(ScheduleSessionService::class);
    $jp3 = LessonPeriod::where('day_of_week', 5)->where('period_number', 3)->first();
    $jp4 = LessonPeriod::where('day_of_week', 5)->where('period_number', 4)->first();
    $jp4->update(['is_active' => false]);
    expect(fn () => $service->periodSpan($jp3->id, 2, 5))->toThrow(ValidationException::class);
    $jp4->update(['is_active' => true]);
    LessonPeriod::where('day_of_week', 5)->where('is_break', true)->update(['is_active' => false]);
    expect(fn () => $service->periodSpan($jp3->id, 2, 5))->toThrow(ValidationException::class);
    foreach ([$jp3, $jp4] as $period) {
        Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $period->id, 'room_id' => $this->room->id, 'day_of_week' => 5]);
    }
    expect($service->group(Schedule::with(['lessonPeriod', 'room', 'teachingAssignment'])->get()))->toHaveCount(2);
});

it('switches the selected JP to the correct day and shows the daily times to teachers', function () {
    $this->seed(KbmTimeSeeder::class);
    $monday = LessonPeriod::where('day_of_week', 1)->where('period_number', 1)->first();
    $thursday = LessonPeriod::where('day_of_week', 4)->where('period_number', 1)->first();
    $component = Livewire::test('admin.schedules.index')->call('createSchedule', 1, $monday->id)
        ->set('teachingAssignmentId', $this->assignment->id)->set('dayOfWeek', 4)
        ->assertSet('lessonPeriodId', $thursday->id)->assertSee('07:50')->assertSee('08:25')
        ->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::first()->day_of_week)->toBe(4)->and(Schedule::first()->lesson_period_id)->toBe($thursday->id);
    $this->actingAs($this->teacher)->get(route('teacher.schedules.index'))->assertOk()->assertSee('07:50 – 08:25');
});

it('rejects daily slots that no longer last exactly 35 minutes', function () {
    $this->seed(KbmTimeSeeder::class);
    $period = LessonPeriod::where('day_of_week', 1)->where('period_number', 1)->first();
    $period->update(['end_time' => '08:00']);
    expect(fn () => app(ScheduleSessionService::class)->periodSpan($period->id, 1, 1))->toThrow(ValidationException::class);
});

it('lets admins render and apply one valid pattern to several days without changing period identities', function () {
    $this->seed(KbmTimeSeeder::class);
    $id = LessonPeriod::where('day_of_week', 1)->where('period_number', 1)->value('id');
    $this->get(route('admin.kbm-times.index', ['day' => 4]))->assertOk()->assertSee('Pengaturan Waktu KBM')->assertSee('07:50')->assertSee('14:00');
    $this->put(route('admin.kbm-times.update'), ['days' => [1, 2, 3]] + $this->times->defaults(4))->assertRedirect();
    foreach ([1, 2, 3] as $day) {
        expect(substr(LessonPeriod::where('day_of_week', $day)->where('period_number', 1)->first()->start_time, 0, 5))->toBe('07:50');
    }
    expect(LessonPeriod::where('day_of_week', 1)->where('period_number', 1)->value('id'))->toBe($id);
    $this->get(route('admin.kbm-times.index', ['day' => 7]))->assertSessionHasErrors('day');
});

it('rejects malformed or overlapping times and breaks cutting through a JP', function (array $changes, string $field) {
    $this->seed(KbmTimeSeeder::class);
    $before = DB::table('lesson_periods')->orderBy('id')->get()->toJson();
    $this->put(route('admin.kbm-times.update'), ['days' => [1]] + array_replace($this->times->defaults(1), $changes))->assertSessionHasErrors($field);
    expect(DB::table('lesson_periods')->orderBy('id')->get()->toJson())->toBe($before);
})->with([
    [['morning_end' => '06:00'], 'morning_end'], [['teaching_periods' => 0], 'teaching_periods'],
    [['teaching_periods' => 13], 'teaching_periods'],
    [['breaks' => [['start' => '09:55', 'end' => '10:25']]], 'breaks'],
    [['breaks' => [['start' => '09:50', 'end' => '10:25'], ['start' => '10:20', 'end' => '10:30']]], 'breaks'],
    [['breaks' => [['start' => '09:50', 'end' => '09:40']]], 'breaks'],
    [['teaching_periods' => 2], 'breaks'],
]);

it('rolls back a multi-day edit if a removed JP is still used by a schedule', function () {
    $this->seed(KbmTimeSeeder::class);
    $period = LessonPeriod::where('day_of_week', 5)->where('period_number', 5)->first();
    Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $period->id, 'room_id' => $this->room->id, 'day_of_week' => 5]);
    $this->put(route('admin.kbm-times.update'), ['days' => [1, 5]] + array_replace($this->times->defaults(5), ['teaching_periods' => 4]))->assertSessionHasErrors('teaching_periods');
    expect(KbmDayPattern::where('day_of_week', 1)->first()->teaching_periods)->toBe(9)
        ->and(KbmDayPattern::where('day_of_week', 5)->first()->teaching_periods)->toBe(5)
        ->and(Schedule::count())->toBe(1);
});

it('restricts time settings and changes to administrators', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('admin.kbm-times.index'))->assertForbidden();
    $this->put(route('admin.kbm-times.update'), ['days' => [1]] + $this->times->defaults(1))->assertForbidden();
    expect(fn () => $this->times->save($user, [1], $this->times->defaults(1)))->toThrow(HttpException::class);
    expect(KbmDayPattern::count())->toBe(0);
})->with(['teacher', 'staff', 'headmaster']);

it('converts existing test schedules by actual JP number and clears only the authorized teaching test data', function () {
    $legacy = collect();
    foreach ($this->times->slots($this->times->defaults(1)) as $index => $slot) {
        $legacy->push(LessonPeriod::create(array_replace($slot, ['period_number' => $index])));
    }
    $mon = Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $legacy->firstWhere('name', 'Jam ke-5')->id, 'room_id' => $this->room->id, 'day_of_week' => 1]);
    $thu = Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $legacy->firstWhere('name', 'Jam ke-1')->id, 'room_id' => $this->room->id, 'day_of_week' => 4]);
    $unsupported = Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $legacy->firstWhere('name', 'Jam ke-9')->id, 'room_id' => $this->room->id, 'day_of_week' => 5]);
    $student = Student::create(['student_code' => 'TIME', 'full_name' => 'Siswa Waktu']);
    StudentAttendance::create(['schedule_id' => $mon->id, 'student_id' => $student->id, 'attendance_date' => '2026-11-02', 'status' => 'present']);
    GtkWorkJournal::create(['gtk_id' => $this->teacher->gtk_id, 'journal_date' => '2026-11-02', 'session_key' => 'kbm-'.$mon->id, 'kind' => 'teaching', 'label' => 'Jurnal uji', 'schedule_ids' => [$mon->id]]);
    GtkWorkJournal::create(['gtk_id' => $this->teacher->gtk_id, 'journal_date' => '2026-11-02', 'session_key' => 'lckh', 'kind' => 'daily', 'label' => 'LCKH', 'activities' => 'Tetap disimpan']);
    $this->artisan('kbm:apply-time-patterns', ['--reset-test-data' => true])->assertSuccessful();
    expect($mon->fresh()->lessonPeriod->period_number)->toBe(5)->and(substr($mon->fresh()->lessonPeriod->start_time, 0, 5))->toBe('10:25')
        ->and(substr($thu->fresh()->lessonPeriod->start_time, 0, 5))->toBe('07:50')->and($unsupported->fresh())->toBeNull()
        ->and(StudentAttendance::count())->toBe(0)->and(GtkWorkJournal::where('kind', 'teaching')->count())->toBe(0)
        ->and(GtkWorkJournal::where('kind', 'daily')->count())->toBe(1)
        ->and(LessonPeriod::whereNull('day_of_week')->where('is_active', true)->count())->toBe(0);
    $ids = Schedule::orderBy('id')->pluck('lesson_period_id')->all();
    $this->artisan('kbm:apply-time-patterns')->assertSuccessful();
    expect(Schedule::orderBy('id')->pluck('lesson_period_id')->all())->toBe($ids)->and(KbmDayPattern::count())->toBe(6);
});

it('refuses unsupported legacy slots without the test reset option and rolls back all changes', function () {
    $period = LessonPeriod::create(['period_number' => 1, 'name' => 'Jam ke-1', 'start_time' => '07:30', 'end_time' => '08:05']);
    $schedule = Schedule::create(['teaching_assignment_id' => $this->assignment->id, 'lesson_period_id' => $period->id,
        'room_id' => $this->room->id, 'day_of_week' => 7]);
    expect(fn () => Artisan::call('kbm:apply-time-patterns'))->toThrow(RuntimeException::class);
    expect($schedule->fresh()->lesson_period_id)->toBe($period->id)->and($period->fresh()->is_active)->toBeTrue()
        ->and(KbmDayPattern::count())->toBe(0)->and(LessonPeriod::count())->toBe(1);
});

it('supports a pre-existing required sort_order column without losing legacy ordering', function () {
    $migration = require database_path('migrations/2026_11_02_000005_normalize_lesson_period_sort_order.php');
    $migration->down();
    $legacy = LessonPeriod::create(['period_number' => 1, 'name' => 'Legacy JP', 'start_time' => '07:00', 'end_time' => '07:35', 'sort_order' => 3]);
    Schema::table('lesson_periods', function (Blueprint $table) {
        $table->tinyInteger('sort_order')->nullable(false)->change();
    });
    $migration->up();
    $this->seed(KbmTimeSeeder::class);
    expect(LessonPeriod::count())->toBe(68)->and($legacy->fresh()->sort_order)->toBe(3)
        ->and(Schema::hasColumn('lesson_periods', 'slot_order'))->toBeFalse();
    foreach (KbmTimeService::DAYS as $day => $label) {
        $orders = LessonPeriod::where('day_of_week', $day)->orderBy('sort_order')->pluck('sort_order')->all();
        expect($orders)->toBe(range(0, count($orders) - 1));
    }
});
