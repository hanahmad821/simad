<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentClassHistory;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\StudentAttendanceSessionService;
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
        '2026_10_27_000002_add_homeroom_teacher_to_school_classes.php',
        '2026_11_02_000003_add_room_to_school_classes.php',
        '2026_11_04_000001_add_student_progression.php',
        '2026_11_04_000002_restructure_student_management.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->withoutVite();
    $this->travelTo(\Carbon\Carbon::parse('2026-11-02 08:00:00'));
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
    $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika']);
    $periods = collect();
    foreach (range(1, 4) as $number) {
        $periods->put($number, LessonPeriod::create(['period_number' => $number, 'name' => 'JP '.$number, 'start_time' => sprintf('%02d:00', $number + 6), 'end_time' => sprintf('%02d:45', $number + 6)]));
    }
    foreach (['mine', 'other'] as $key) {
        $gtk = Gtk::create(['full_name' => 'Guru '.$key]);
        $class = SchoolClass::create(['name' => 'Kelas '.$key, 'grade' => 10, 'academic_year_id' => $year->id]);
        $user = User::factory()->create(['role' => 'teacher', 'gtk_id' => $gtk->id]);
        $assignment = TeachingAssignment::create([
            'gtk_id' => $gtk->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);
        $student = Student::create(['student_code' => $key, 'full_name' => 'Siswa '.$key]);
        StudentClassHistory::create(['student_id' => $student->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
        $this->{$key} = compact('gtk', 'class', 'user', 'assignment', 'student');
    }
    // Interleaving schedules from other classes must not split one teacher's session.
    foreach ([['mine', 1], ['other', 1], ['mine', 2], ['mine', 4]] as [$key, $number]) {
        $schedule = Schedule::create(['teaching_assignment_id' => $this->{$key}['assignment']->id, 'lesson_period_id' => $periods[$number]->id, 'room_id' => $room->id, 'day_of_week' => 1]);
        $this->{$key.'Schedule'.$number} = $schedule;
    }
    $this->actingAs($this->mine['user']);
});

it('lets teachers open student attendance but forbids other roles and the admin URL', function () {
    $this->get(route('teacher.student-attendances.index'))->assertOk()->assertSee('Presensi Siswa');
    $this->get(route('admin.student-attendances.index'))->assertForbidden();
    foreach (['staff'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('teacher.student-attendances.index'))->assertForbidden();
    }
});

it('groups consecutive periods once and keeps separated periods distinct', function () {
    $service = app(StudentAttendanceSessionService::class);
    $sessions = $service->forUser($this->mine['user'], today()->toDateString());
    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->schedules->pluck('id')->all())->toBe([$this->mineSchedule1->id, $this->mineSchedule2->id])
        ->and($sessions[1]->id)->toBe($this->mineSchedule4->id);
    $admin = User::factory()->create(['role' => 'admin']);
    expect($service->forUser($admin, today()->toDateString()))->toHaveCount(3);
});

it('scopes the same accounts attendance sessions to its selected admin or teacher role', function () {
    $user = $this->mine['user'];
    $user->update(['secondary_role' => 'admin']);
    $service = app(StudentAttendanceSessionService::class);
    expect($service->forUser($user, today()->toDateString()))->toHaveCount(2);
    $this->post(route('role.switch'), ['role' => 'admin'])->assertRedirect();
    expect($service->forUser($user, today()->toDateString()))->toHaveCount(3);
    $this->post(route('role.switch'), ['role' => 'teacher'])->assertRedirect();
    expect($service->forUser($user, today()->toDateString()))->toHaveCount(2);
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->otherSchedule1->id)->assertForbidden();
});

it('saves two consecutive periods once per student and updates without duplicates', function () {
    $student = $this->mine['student'];
    $component = Livewire::test('admin.student-attendances.index')
        ->set('scheduleId', $this->mineSchedule1->id)
        ->assertSee($student->full_name)->assertDontSee($this->other['student']->full_name)
        ->set('attendance.'.$student->id, 'sick')->call('saveAttendance')->assertHasNoErrors();
    expect(StudentAttendance::count())->toBe(1)
        ->and(StudentAttendance::first()->schedule_id)->toBe($this->mineSchedule1->id)
        ->and(StudentAttendance::first()->recorded_by)->toBe($this->mine['user']->id);
    $component->set('attendance.'.$student->id, 'present')->call('saveAttendance')->assertHasNoErrors();
    expect(StudentAttendance::count())->toBe(1)->and(StudentAttendance::first()->status)->toBe('present');
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->assertSet('attendance.'.$student->id, 'present');
});

it('rejects a forged schedule from another teacher', function () {
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->otherSchedule1->id)->assertForbidden();
    expect(StudentAttendance::count())->toBe(0);
});

it('does not expose the second period as another attendance session', function () {
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule2->id)->assertForbidden();
    expect(StudentAttendance::count())->toBe(0);
});

it('rejects injected students and performs no partial writes', function () {
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendance.'.$this->mine['student']->id, 'present')
        ->set('attendance.'.$this->other['student']->id, 'present')
        ->call('saveAttendance')->assertHasErrors('attendance');
    expect(StudentAttendance::count())->toBe(0);
});

it('only scans students in the selected class', function () {
    $student = $this->mine['student'];
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendance.'.$student->id, 'absent')
        ->call('scanStudent', $this->other['student']->student_code)->assertSet('scanMessage', 'QR code tidak terdaftar sebagai siswa aktif di kelas ini.')
        ->assertSet('attendance.'.$student->id, 'absent')
        ->call('scanStudent', $student->student_code)->assertSet('attendance.'.$student->id, 'present');
});

it('starts unrecorded students as pending and requires a manual decision before final save', function () {
    $student = $this->mine['student'];
    $component = Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->assertSet('attendance.'.$student->id, '')
        ->assertSee('Belum dipresensi')
        ->call('saveAttendance')->assertHasErrors('attendance.'.$student->id)
        ->assertDispatched('attendance-save-feedback', type: 'error', message: 'Masih ada siswa yang belum dipresensi. Lengkapi statusnya secara manual sebelum menyimpan.');
    expect(StudentAttendance::count())->toBe(0);
    $component->set('attendance.'.$student->id, 'excused')->call('saveAttendance')->assertHasNoErrors()
        ->assertDispatched('attendance-save-feedback', type: 'success', message: 'Presensi siswa berhasil disimpan.');
    expect(StudentAttendance::first()->status)->toBe('excused')->and(StudentAttendance::first()->scanned_at)->toBeNull();
});

it('notifies the teacher when a manual note is too long without writing attendance', function () {
    $student = $this->mine['student'];
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendance.'.$student->id, 'present')->set('notes.'.$student->id, str_repeat('a', 1001))
        ->call('saveAttendance')->assertHasErrors('notes.'.$student->id)
        ->assertDispatched('attendance-save-feedback', type: 'error', message: 'Catatan siswa maksimal 1.000 karakter.');
    expect(StudentAttendance::count())->toBe(0);
});

it('persists a scan immediately and restores it when the page is reopened', function () {
    $student = $this->mine['student'];
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->call('startScanner')
        ->call('scanStudent', $student->student_code)->assertSet('scanMessageType', 'success')
        ->assertReturned(['success' => true, 'type' => 'success', 'message' => $student->full_name.' — Hadir, berhasil disimpan.'])
        ->assertSee('Scan terakhir berhasil tersimpan')->assertSee($student->full_name)
        ->assertSet('attendance.'.$student->id, 'present');
    expect(StudentAttendance::count())->toBe(1)->and(StudentAttendance::first()->scanned_at)->not->toBeNull();
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->assertSet('attendance.'.$student->id, 'present');
});

it('keeps manual attendance available for students without cards', function () {
    $student = $this->mine['student'];
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendance.'.$student->id, 'present')->set('notes.'.$student->id, 'Tidak membawa kartu')
        ->call('saveAttendance')->assertHasNoErrors();
    expect(StudentAttendance::first()->scanned_at)->toBeNull()
        ->and(StudentAttendance::first()->notes)->toBe('Tidak membawa kartu');
});

it('ignores repeated scans and preserves manual corrections and the first scan timestamp', function () {
    $student = $this->mine['student'];
    $component = Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->call('scanStudent', $student->student_code);
    $scanTime = StudentAttendance::first()->scanned_at->toIso8601String();
    $this->travel(5)->minutes();
    $component->set('attendance.'.$student->id, 'late')->call('saveAttendance')->assertHasNoErrors()
        ->call('scanStudent', $student->student_code)->assertSet('scanMessageType', 'info')
        ->assertSet('attendance.'.$student->id, 'late');
    expect(StudentAttendance::count())->toBe(1)->and(StudentAttendance::first()->status)->toBe('late')
        ->and(StudentAttendance::first()->scanned_at->toIso8601String())->toBe($scanTime);
});

it('discards scans started for a previous session', function () {
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule4->id)
        ->call('scanStudent', $this->mine['student']->student_code, $this->mineSchedule1->id, today()->toDateString())
        ->assertSet('scanMessageType', 'error');
    expect(StudentAttendance::count())->toBe(0);
});

it('clears the session on date change and excludes inactive assignments', function () {
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendanceDate', today()->addDay()->toDateString())->assertSet('scheduleId', null)->assertSet('attendance', []);
    $this->mine['assignment']->update(['is_active' => false]);
    expect(app(StudentAttendanceSessionService::class)->forUser($this->mine['user'], today()->toDateString()))->toBeEmpty();
});

it('gives unlinked teachers an empty schedule and a helpful message', function () {
    $user = User::factory()->create(['role' => 'teacher', 'gtk_id' => null]);
    $this->actingAs($user)->get(route('teacher.student-attendances.index'))->assertOk()->assertSee('belum terhubung');
    expect(app(StudentAttendanceSessionService::class)->forUser($user, today()->toDateString()))->toBeEmpty();
});

it('lets a headmaster record and scan only their own teaching sessions', function () {
    $user = $this->mine['user'];
    $user->update(['role' => 'headmaster']);
    $this->actingAs($user);
    $this->get(route('teacher.student-attendances.index'))->assertOk();
    $this->get(route('admin.student-attendances.index'))->assertForbidden();
    expect(app(StudentAttendanceSessionService::class)->forUser($user, today()->toDateString()))->toHaveCount(2);
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->otherSchedule1->id)->assertForbidden();
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->call('scanStudent', $this->mine['student']->student_code)->assertSet('scanMessageType', 'success')
        ->set('attendance.'.$this->mine['student']->id, 'sick')->call('saveAttendance')->assertHasNoErrors();
    expect(StudentAttendance::first()->recorded_by)->toBe($user->id);
    expect(StudentAttendance::first()->status)->toBe('sick');
    $user->update(['gtk_id' => null]);
    expect(app(StudentAttendanceSessionService::class)->forUser($user, today()->toDateString()))->toHaveCount(0);
});

it('lets staff record and scan attendance across teaching assignments without access to admin master data', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff);
    $this->get(route('staff.student-attendances.index'))->assertOk()->assertSee('Presensi Siswa');
    expect(app(StudentAttendanceSessionService::class)->forUser($staff, today()->toDateString()))->toHaveCount(3);
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendance.'.$this->mine['student']->id, 'sick')->call('saveAttendance')->assertHasNoErrors();
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->otherSchedule1->id)
        ->call('scanStudent', $this->other['student']->student_code)->assertHasNoErrors();
    expect(StudentAttendance::count())->toBe(2)
        ->and(StudentAttendance::where('recorded_by', $staff->id)->count())->toBe(2);
    $this->get(route('admin.students.index'))->assertForbidden();
    $this->get(route('admin.student-attendances.index'))->assertForbidden();
    $this->get(route('teacher.student-attendances.index'))->assertForbidden();
});

it('preserves attendance before a move and rejects backdated moves across recorded attendance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    $student = $this->mine['student'];
    $component = Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)
        ->set('attendance.'.$student->id, 'present')->call('saveAttendance')->assertHasNoErrors();
    $service = app(\App\Services\StudentLifecycleService::class);
    $data = ['kind' => 'move', 'source_class_id' => $this->mine['class']->id, 'target_class_id' => $this->other['class']->id,
        'student_ids' => [$student->id], 'date' => '2026-11-02', 'reason' => 'Pindah rombel'];
    expect(fn () => $service->preview($admin, $data))->toThrow(\Illuminate\Validation\ValidationException::class);
    $this->travelTo(\Carbon\Carbon::parse('2026-11-09 08:00:00'));
    $data['date'] = '2026-11-09';
    $plan = $service->preview($admin, $data);
    $service->commit($admin, $data, (string) \Illuminate\Support\Str::uuid(), $plan['fingerprint']);
    Livewire::test('admin.student-attendances.index')->set('attendanceDate', '2026-11-02')->set('scheduleId', $this->mineSchedule1->id)
        ->assertSet('attendance.'.$student->id, 'present');
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->otherSchedule1->id)
        ->assertSee($student->full_name)->call('scanStudent', $student->student_code)->assertSet('scanMessageType', 'success');
    $event = DB::table('student_management_events')->latest('id')->first();
    expect(fn () => $service->preview($admin, ['kind' => 'correct', 'event_id' => $event->id, 'date' => '2026-11-09', 'reason' => 'Salah tujuan']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(StudentAttendance::where('student_id', $student->id)->count())->toBe(2);
});

it('retains historical roster after exit and excludes later sessions', function () {
    $student = $this->mine['student'];
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(\App\Services\StudentLifecycleService::class);
    $data = ['kind' => 'status', 'student_ids' => [$student->id], 'date' => '2026-11-02', 'action' => 'withdraw', 'reason' => 'Keluar'];
    $plan = $service->preview($admin, $data);
    $service->commit($admin, $data, (string) \Illuminate\Support\Str::uuid(), $plan['fingerprint']);
    Livewire::test('admin.student-attendances.index')->set('scheduleId', $this->mineSchedule1->id)->assertSee($student->full_name);
    Livewire::test('admin.student-attendances.index')->set('attendanceDate', '2026-11-09')->set('scheduleId', $this->mineSchedule1->id)->assertDontSee($student->full_name);
});
