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
use App\Services\ScheduleSessionService;
use App\Services\StudentAttendanceSessionService;
use Database\Seeders\LessonPeriodSeeder;
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
        '2026_11_02_000002_make_kbm_independent_of_semesters.php',
        '2026_11_02_000003_add_room_to_school_classes.php',
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

beforeEach(function () {
    Schedule::query()->delete();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $this->scheduleData = [
        'academicYearId' => $this->mine['assignment']->academic_year_id,
        'teachingAssignmentId' => $this->mine['assignment']->id,
        'lessonPeriodId' => LessonPeriod::where('period_number', 1)->value('id'),
        'duration' => 2, 'roomId' => Room::first()->id, 'dayOfWeek' => 1, 'isActive' => true,
    ];
});

it('creates two periods from one form and presents one attendance session', function () {
    $component = Livewire::test('admin.schedules.index')->call('createSchedule');
    foreach ($this->scheduleData as $key => $value) {
        $component->set($key, $value);
    }
    $component->assertSee('Durasi sesi (JP)')->call('saveSchedule')->assertHasNoErrors()->assertSet('showForm', false);
    expect(Schedule::count())->toBe(2);
    $sessions = app(StudentAttendanceSessionService::class)->forUser($this->mine['user'], today()->toDateString());
    expect($sessions)->toHaveCount(1)->and($sessions->first()->schedules)->toHaveCount(2);
    $component->assertSeeHtml('rowspan="2"');
});

it('loads the entire session when editing either period and preserves unchanged row IDs', function () {
    $service = app(ScheduleSessionService::class);
    $service->save($this->admin, $this->scheduleData);
    $ids = Schedule::orderBy('lesson_period_id')->pluck('id')->all();
    $component = Livewire::test('admin.schedules.index')->call('editSchedule', $ids[1])
        ->assertSet('duration', 2)->assertSet('editingScheduleId', $ids[0]);
    $component->set('duration', 3)->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::count())->toBe(3)->and(Schedule::orderBy('lesson_period_id')->limit(2)->pluck('id')->all())->toBe($ids);
    $component->call('editSchedule', $ids[0])->set('duration', 1)->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::count())->toBe(1)->and(Schedule::first()->id)->toBe($ids[0]);
});

it('rejects a conflict in the second period without saving the first period', function (string $kind, string $errorField) {
    $assignment = $this->other['assignment'];
    $semester = Semester::create(['academic_year_id' => $assignment->academic_year_id, 'name' => 'Genap',
        'start_date' => '2027-01-01', 'end_date' => '2027-06-30', 'is_active' => false]);
    $assignment->update(['semester_id' => $semester->id]);
    if ($kind === 'teacher') {
        $assignment->update(['gtk_id' => $this->mine['gtk']->id]);
    }
    if ($kind === 'class') {
        $assignment->update(['school_class_id' => $this->mine['class']->id]);
    }
    $room = $kind === 'room' ? Room::first() : Room::create(['code' => 'OTHER', 'name' => 'Ruang lain']);
    Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => LessonPeriod::where('period_number', 2)->value('id'), 'room_id' => $room->id, 'day_of_week' => 1]);
    $component = Livewire::test('admin.schedules.index');
    foreach ($this->scheduleData as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('saveSchedule')->assertHasErrors($errorField);
    expect(Schedule::count())->toBe(1)->and(Schedule::where('teaching_assignment_id', $this->mine['assignment']->id)->count())->toBe(0);
})->with([['teacher', 'lessonPeriodId'], ['class', 'lessonPeriodId'], ['room', 'roomId']]);

it('keeps schedule conflicts isolated to the school year', function () {
    $nextYear = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
    $this->other['assignment']->update(['academic_year_id' => $nextYear->id, 'gtk_id' => $this->mine['gtk']->id,
        'school_class_id' => $this->mine['class']->id]);
    Schedule::create(['teaching_assignment_id' => $this->other['assignment']->id, 'lesson_period_id' => $this->scheduleData['lessonPeriodId'],
        'room_id' => $this->scheduleData['roomId'], 'day_of_week' => 1]);
    app(ScheduleSessionService::class)->save($this->admin, $this->scheduleData);
    expect(Schedule::count())->toBe(3);
});

it('saves the whole session without a manual teaching workload limit', function () {
    DB::table('teaching_assignments')->where('id', $this->mine['assignment']->id)->update(['teaching_hours' => 1]);
    $component = Livewire::test('admin.schedules.index');
    foreach ($this->scheduleData as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::count())->toBe(2)->and($this->mine['assignment']->fresh()->scheduled_hours)->toBe(2);
});

it('updates the derived weekly JP after creating editing deactivating and deleting schedules', function () {
    $service = app(ScheduleSessionService::class);
    $id = $this->mine['assignment']->id;
    expect($this->mine['assignment']->scheduled_hours)->toBe(0);
    $service->save($this->admin, $this->scheduleData);
    $anchor = Schedule::first()->id;
    expect(TeachingAssignment::withCount('activeSchedules as scheduled_hours')->find($id)->scheduled_hours)->toBe(2);
    $service->save($this->admin, array_replace($this->scheduleData, ['duration' => 3]), $anchor);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(3);
    $service->save($this->admin, array_replace($this->scheduleData, ['duration' => 3, 'isActive' => false]), $anchor);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(0);
    $service->save($this->admin, $this->scheduleData, $anchor);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(2);
    $service->delete($this->admin, $anchor);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(0);
});

it('excludes inactive lesson periods breaks and non-teaching activities from scheduled JP', function () {
    $service = app(ScheduleSessionService::class);
    $service->save($this->admin, $this->scheduleData);
    LessonPeriod::where('period_number', 1)->update(['is_break' => true]);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(1);
    LessonPeriod::where('period_number', 2)->update(['is_active' => false]);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(0);
    LessonPeriod::where('period_number', 2)->update(['is_active' => true, 'period_number' => 0]);
    expect($this->mine['assignment']->fresh()->scheduled_hours)->toBe(0);
});

it('skips legacy break slots while still rejecting the end of the day', function () {
    LessonPeriod::where('period_number', 2)->update(['is_break' => true]);
    $component = Livewire::test('admin.schedules.index');
    foreach ($this->scheduleData as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::with('lessonPeriod')->get()->pluck('lessonPeriod.period_number')->all())->toBe([1, 3]);
    $component->call('createSchedule');
    foreach ($this->scheduleData as $key => $value) {
        $component->set($key, $value);
    }
    $component->set('lessonPeriodId', LessonPeriod::where('period_number', 4)->value('id'))->call('saveSchedule')->assertHasErrors('duration');
    expect(Schedule::count())->toBe(2);
});

it('limits Friday to the fifth teaching period rather than counting breaks', function () {
    LessonPeriod::query()->delete();
    $this->seed(LessonPeriodSeeder::class);
    $service = app(ScheduleSessionService::class);
    $periods = $service->periodsForDay(5);
    expect($periods->filter(fn ($period) => ! $period->is_break && $period->period_number > 0))->toHaveCount(5)
        ->and($periods->last()->name)->toBe('Jam ke-5');
    $data = array_merge($this->scheduleData, ['dayOfWeek' => 5, 'lessonPeriodId' => $periods->last()->id, 'duration' => 2]);
    $component = Livewire::test('admin.schedules.index');
    foreach ($data as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('saveSchedule')->assertHasErrors('duration');
});

it('preserves attendance anchors and prevents destructive changes to recorded sessions', function () {
    $service = app(ScheduleSessionService::class);
    $service->save($this->admin, $this->scheduleData);
    $anchor = Schedule::orderBy('lesson_period_id')->first();
    StudentAttendance::create(['schedule_id' => $anchor->id, 'student_id' => $this->mine['student']->id, 'attendance_date' => today(), 'status' => 'present']);
    $component = Livewire::test('admin.schedules.index')->call('editSchedule', $anchor->id);
    $component->call('saveSchedule')->assertHasNoErrors();
    expect(StudentAttendance::first()->schedule_id)->toBe($anchor->id);
    $component->call('editSchedule', $anchor->id)->set('duration', 1)->call('saveSchedule')->assertHasErrors('schedule');
    $component->call('deleteSchedule', $anchor->id)->assertHasErrors('schedule');
    expect(StudentAttendance::count())->toBe(1)->and(Schedule::count())->toBe(2);
    $component->call('editSchedule', $anchor->id)->set('isActive', false)->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::where('is_active', true)->count())->toBe(0)->and(StudentAttendance::count())->toBe(1);
});

it('deletes all periods of an unrecorded session together', function () {
    $service = app(ScheduleSessionService::class);
    $service->save($this->admin, $this->scheduleData);
    Livewire::test('admin.schedules.index')->call('deleteSchedule', Schedule::first()->id)->assertHasNoErrors();
    expect(Schedule::count())->toBe(0);
});

it('restricts schedule management to administrators', function () {
    $this->actingAs($this->mine['user']);
    Livewire::test('admin.schedules.index')->assertForbidden();
    $this->get(route('admin.schedules.index'))->assertForbidden();
});

it('does not change an attendance anchor by prepending an adjacent new period', function () {
    $service = app(ScheduleSessionService::class);
    $service->save($this->admin, array_merge($this->scheduleData, ['lessonPeriodId' => LessonPeriod::where('period_number', 2)->value('id')]));
    $anchor = Schedule::orderBy('lesson_period_id')->first();
    StudentAttendance::create(['schedule_id' => $anchor->id, 'student_id' => $this->mine['student']->id, 'attendance_date' => today(), 'status' => 'present']);
    $component = Livewire::test('admin.schedules.index');
    foreach (array_merge($this->scheduleData, ['duration' => 1]) as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('saveSchedule')->assertHasErrors('schedule');
    expect(Schedule::count())->toBe(2)->and(StudentAttendance::first()->schedule_id)->toBe($anchor->id);
});

it('lets the duration field be temporarily cleared without breaking the form', function () {
    Livewire::test('admin.schedules.index')->call('createSchedule', 1, $this->scheduleData['lessonPeriodId'], $this->mine['class']->id)
        ->set('duration', null)->assertSee('Isi durasi sesi dalam JP.')
        ->set('duration', 2)->assertSee('Presensi siswa cukup sekali untuk sesi ini.');
});

it('defaults to the classroom from both the timetable grid and the embedded class schedule', function () {
    $this->mine['class']->update(['room_id' => Room::first()->id]);
    Livewire::test('admin.schedules.index')
        ->call('createSchedule', 1, $this->scheduleData['lessonPeriodId'], $this->mine['class']->id)
        ->assertSet('roomId', Room::first()->id);
    Livewire::test('admin.schedules.index', ['schoolClassId' => $this->mine['class']->id])
        ->call('createSchedule')->assertSet('roomId', Room::first()->id);
});

it('changes the default room with the selected assignment and preserves a manually selected room when editing', function () {
    $classroom = Room::first();
    $otherRoom = Room::create(['code' => 'R2', 'name' => 'Ruang Kelas Lain']);
    $lab = Room::create(['code' => 'LAB', 'name' => 'Laboratorium']);
    $this->mine['class']->update(['room_id' => $classroom->id]);
    $this->other['class']->update(['room_id' => $otherRoom->id]);
    $component = Livewire::test('admin.schedules.index')->call('createSchedule')
        ->set('teachingAssignmentId', $this->mine['assignment']->id)->assertSet('roomId', $classroom->id)
        ->set('teachingAssignmentId', $this->other['assignment']->id)->assertSet('roomId', $otherRoom->id)
        ->set('teachingAssignmentId', $this->mine['assignment']->id)->set('roomId', $lab->id)
        ->set('dayOfWeek', 1)->set('lessonPeriodId', $this->scheduleData['lessonPeriodId'])->set('duration', 2)
        ->call('saveSchedule')->assertHasNoErrors();
    $anchor = Schedule::first()->id;
    expect(Schedule::where('room_id', $lab->id)->count())->toBe(2);
    $component->call('editSchedule', $anchor)->assertSet('roomId', $lab->id)
        ->set('duration', 3)->assertSet('roomId', $lab->id)->call('saveSchedule')->assertHasNoErrors();
    expect(Schedule::where('room_id', $lab->id)->count())->toBe(3);
});

it('leaves the room empty for an unset or inactive classroom', function () {
    $room = Room::first();
    Livewire::test('admin.schedules.index')->call('createSchedule')->set('teachingAssignmentId', $this->mine['assignment']->id)->assertSet('roomId', null);
    $this->mine['class']->update(['room_id' => $room->id]);
    $room->update(['is_active' => false]);
    Livewire::test('admin.schedules.index')->call('createSchedule', 1, null, $this->mine['class']->id)->assertSet('roomId', null)
        ->set('teachingAssignmentId', $this->mine['assignment']->id)->assertSet('roomId', null);
});

it('uses the classroom on the server when a schedule has no explicit room', function () {
    $this->mine['class']->update(['room_id' => Room::first()->id]);
    $data = $this->scheduleData;
    unset($data['roomId']);
    app(ScheduleSessionService::class)->save($this->admin, $data);
    expect(Schedule::where('room_id', Room::first()->id)->count())->toBe(2);
});

it('rejects missing or inactive default rooms and still detects conflicts in the default room', function () {
    $component = Livewire::test('admin.schedules.index')->call('createSchedule');
    foreach (array_replace($this->scheduleData, ['roomId' => null]) as $key => $value) {
        $component->set($key, $value);
    }
    $component->call('saveSchedule')->assertHasErrors('roomId');
    $room = Room::first();
    $this->mine['class']->update(['room_id' => $room->id]);
    $room->update(['is_active' => false]);
    $component->call('saveSchedule')->assertHasErrors('roomId');
    $room->update(['is_active' => true]);
    Schedule::create(['teaching_assignment_id' => $this->other['assignment']->id, 'lesson_period_id' => $this->scheduleData['lessonPeriodId'],
        'room_id' => $room->id, 'day_of_week' => 1]);
    $component->call('saveSchedule')->assertHasErrors('roomId');
    expect(Schedule::count())->toBe(1);
});
