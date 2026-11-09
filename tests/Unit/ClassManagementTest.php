<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Tests require isolated database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_070421_create_gtk_education_table.php',
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
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_10_27_000002_add_homeroom_teacher_to_school_classes.php',
        '2026_11_02_000002_make_kbm_independent_of_semesters.php',
        '2026_11_02_000003_add_room_to_school_classes.php',
        '2026_08_26_075839_create_student_attendances_table.php',
        '2026_11_04_000001_add_student_progression.php',
        '2026_11_04_000002_restructure_student_management.php',
    ] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $this->year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->class = SchoolClass::create(['name' => 'X A', 'grade' => 10, 'capacity' => 1, 'academic_year_id' => $this->year->id]);
    $this->student = Student::create(['student_code' => 'S1', 'full_name' => 'Siswa Satu']);
});

it('creates a class with its homeroom teacher and validates grade', function () {
    $teacher = Gtk::create(['full_name' => 'Wali Satu']);
    $this->post(route('admin.classes.store'), ['name' => 'XI A', 'academic_year_id' => $this->year->id, 'grade' => 11, 'capacity' => 32, 'is_active' => 1, 'homeroom_teacher_id' => $teacher->id])->assertRedirect();
    expect(SchoolClass::where('name', 'XI A')->first()->homeroomTeacher->id)->toBe($teacher->id);
    $this->post(route('admin.classes.store'), ['name' => 'Bad', 'grade' => 99, 'is_active' => 1])->assertSessionHasErrors('grade');
});

it('disables direct membership mutations that bypass review', function () {
    $this->post('/admin/classes/'.$this->class->id.'/students', ['student_id' => $this->student->id])->assertNotFound();
    expect(StudentClassHistory::count())->toBe(0);
});

it('restricts management to admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'teacher']))->post(route('admin.classes.store'), ['name' => 'Unauthorized', 'grade' => 10, 'is_active' => 1])->assertForbidden();
});

it('deletes unused classes and returns to their academic year without deleting shared data', function (bool $active) {
    $teacher = Gtk::create(['full_name' => 'Wali Kelas']);
    $room = Room::create(['code' => 'R-HAPUS', 'name' => 'Ruang Kelas']);
    $this->class->update(['is_active' => $active, 'homeroom_teacher_id' => $teacher->id, 'room_id' => $room->id]);
    $otherYear = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
    $otherClass = SchoolClass::create(['name' => 'X A', 'grade' => 10, 'academic_year_id' => $otherYear->id]);
    $url = route('admin.classes.destroy', $this->class);
    foreach (['index', 'show', 'edit'] as $action) {
        $this->get(route('admin.classes.'.$action, $action === 'index' ? [] : $this->class))
            ->assertOk()->assertSee($url, false)->assertSee('Hapus');
    }
    $this->delete($url)->assertRedirect(route('admin.classes.index', ['academic_year_id' => $this->year->id]))
        ->assertSessionHas('status', 'Kelas berhasil dihapus.');
    expect($this->class->fresh())->toBeNull()
        ->and($otherClass->fresh())->not->toBeNull()
        ->and($teacher->fresh())->not->toBeNull()
        ->and($room->fresh())->not->toBeNull()
        ->and($this->student->fresh())->not->toBeNull()
        ->and($this->year->fresh())->not->toBeNull();
    $this->get(route('admin.classes.index', ['academic_year_id' => $this->year->id]))
        ->assertOk()->assertSee('Kelas berhasil dihapus.')->assertDontSee($url, false);
    $this->delete($url)->assertNotFound();
})->with([true, false]);

it('preserves classes with current historical or voided student placements', function (string $state) {
    $history = StudentClassHistory::create([
        'student_id' => $this->student->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id,
        'starts_on' => '2026-07-01', 'ends_on' => $state === 'historical' ? '2026-07-31' : '2027-06-30',
        'is_active' => $state === 'current', 'voided_at' => $state === 'voided' ? now() : null,
    ]);
    $index = route('admin.classes.index', ['academic_year_id' => $this->year->id]);
    $this->from($index)->followingRedirects()->delete(route('admin.classes.destroy', $this->class))
        ->assertOk()->assertSee('Kelas sudah memiliki riwayat penempatan siswa atau pengampu sehingga tidak dapat dihapus.');
    expect($this->class->fresh())->not->toBeNull()
        ->and($history->fresh())->not->toBeNull()
        ->and($this->student->fresh())->not->toBeNull();
})->with(['current', 'historical', 'voided']);

it('preserves classes with active or inactive teaching assignments and schedules', function (bool $active) {
    $teacher = Gtk::create(['full_name' => 'Guru Kelas']);
    $subject = Subject::create(['code' => 'MAT-HAPUS', 'name' => 'Matematika']);
    $assignment = TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id, 'is_active' => $active]);
    $room = Room::create(['code' => 'R-JADWAL', 'name' => 'Ruang Jadwal']);
    $period = LessonPeriod::create(['period_number' => 1, 'name' => 'JP 1', 'start_time' => '07:00', 'end_time' => '07:45']);
    $schedule = Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => $period->id,
        'room_id' => $room->id, 'day_of_week' => 1, 'is_active' => $active]);
    $this->delete(route('admin.classes.destroy', $this->class))->assertSessionHasErrors('class');
    expect($this->class->fresh())->not->toBeNull()
        ->and($assignment->fresh())->not->toBeNull()
        ->and($schedule->fresh())->not->toBeNull();
})->with([true, false]);

it('denies class deletion to non-admins and guests', function (string $role) {
    $url = route('admin.classes.destroy', $this->class);
    $this->actingAs(User::factory()->create(['role' => $role]))->delete($url)->assertForbidden();
    auth()->logout();
    $this->delete($url)->assertRedirect(route('login'));
    expect($this->class->fresh())->not->toBeNull();
})->with(['teacher', 'staff', 'headmaster']);

it('renders annual class membership and links to the separate schedule', function () {
    $this->get(route('admin.classes.index'))->assertOk()->assertSee('X A');
    $this->get(route('admin.classes.create'))->assertOk()->assertSee('Wali kelas');
    $this->get(route('admin.classes.show', [$this->class, 'academic_year_id' => $this->year->id]))
        ->assertOk()->assertSee('Jadwal Pelajaran')->assertDontSee('Semester')->assertDontSee('Beban JP per minggu');
    Livewire::test('admin.schedules.index', ['schoolClassId' => $this->class->id, 'initialAcademicYearId' => $this->year->id])
        ->assertSet('academicYearId', $this->year->id)->call('createSchedule')->assertSet('selectedSchoolClassId', $this->class->id);
});

it('rejects duplicate active subjects from the schedule assignments page', function (bool $differentTeacher) {
    $teacher = Gtk::create(['full_name' => 'Guru Matematika']);
    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika']);
    $data = ['gtk_id' => $teacher->id, 'subject_id' => $subject->id, 'school_class_id' => $this->class->id];
    $formUrl = route('admin.schedules.assignments', ['academic_year_id' => $this->year->id, 'create' => 1]);
    TeachingAssignment::create($data + ['academic_year_id' => $this->year->id, 'is_active' => true]);
    if ($differentTeacher) $data['gtk_id'] = Gtk::create(['full_name' => 'Guru Lain'])->id;
    $this->from($formUrl)->post(route('admin.schedules.assignments.store'), $data)
        ->assertRedirect($formUrl)->assertSessionHasErrors(['subject_id' => 'Mata pelajaran tersebut sudah memiliki guru pengampu pada kelas dan tahun pelajaran yang dipilih.'])->assertSessionHasInput('gtk_id', $data['gtk_id']);
    $response = $this->get($formUrl)->assertOk();
    expect($response->viewData('assignmentErrors'))->toHaveCount(1);
    $response->assertSee('Simpan Pengampu')->assertSee('sudah memiliki guru pengampu');
    expect(TeachingAssignment::count())->toBe(1)->and(TeachingAssignment::first()->semester_id)->toBeNull()
        ->and(TeachingAssignment::first()->scheduled_hours)->toBe(0)
        ->and((int) TeachingAssignment::first()->getRawOriginal('teaching_hours'))->toBe(0);
})->with([false, true]);

it('shows existing assignments by default and opens the form only on request', function () {
    $teacher = Gtk::create(['full_name' => 'Guru Terdaftar']);
    $subject = Subject::create(['code' => 'BIO', 'name' => 'Biologi']);
    TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id]);
    $this->get(route('admin.schedules.assignments'))->assertOk()->assertSee('Guru Terdaftar')
        ->assertSee('Biologi')->assertSee('Tambah Pengampu')->assertDontSee('Simpan Pengampu');
    $this->get(route('admin.schedules.assignments', ['create' => 1]))->assertOk()->assertSee('Simpan Pengampu')->assertSee('Batal');
    $this->get(route('admin.classes.show', $this->class))->assertOk()->assertDontSee('Kelola Pengampu');
});

it('saves and edits teaching assignments from the GTK profile without a semester', function () {
    $teacher = Gtk::create(['full_name' => 'Guru Matematika']);
    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika']);
    $component = Livewire::test('admin.gtks.show', ['gtk' => $teacher])->call('createAssignment')
        ->assertDontSee('Semester')->assertDontSee('Beban Mengajar')->assertDontSee('wire:model="teachingHours"', false)
        ->set('subjectId', $subject->id)->set('schoolClassId', $this->class->id)->set('academicYearId', $this->year->id)
        ->call('saveAssignment')->assertHasNoErrors()->assertSet('showAssignmentForm', false);
    $assignment = TeachingAssignment::firstOrFail();
    expect($assignment->semester_id)->toBeNull();
    $component->call('editAssignment', $assignment->id)->set('assignmentIsActive', false)->call('saveAssignment')->assertHasNoErrors();
    expect($assignment->fresh()->is_active)->toBeFalse();
    $component->call('editAssignment', $assignment->id)->set('assignmentIsActive', true)->call('saveAssignment')->assertHasNoErrors();
    $component->call('createAssignment')->set('subjectId', $subject->id)->set('schoolClassId', $this->class->id)
        ->set('academicYearId', $this->year->id)->call('saveAssignment')->assertHasErrors('subjectId');
});

it('displays scheduled JP from active lessons instead of legacy manual workload values', function () {
    $teacher = Gtk::create(['full_name' => 'Guru Matematika']);
    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika']);
    $assignment = TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id]);
    DB::table('teaching_assignments')->where('id', $assignment->id)->update(['teaching_hours' => 40]);
    $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
    foreach ([1, 2] as $number) {
        $period = LessonPeriod::create(['period_number' => $number, 'name' => 'JP '.$number, 'start_time' => '07:00', 'end_time' => '07:45']);
        Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => $period->id, 'room_id' => $room->id, 'day_of_week' => 1]);
    }
    $this->get(route('admin.gtks.show', $teacher))->assertOk()->assertSee('JP Terjadwal / Minggu')->assertSee('2 JP')->assertDontSee('40 JP');
    Livewire::test('admin.schedules.index')->call('createSchedule')->assertSee('2 JP terjadwal')->assertDontSee('40 JP');
    Livewire::test('admin.gtks.show', ['gtk' => $teacher])->call('editAssignment', $assignment->id)
        ->assertSee('2 JP')->assertDontSee('Beban Mengajar')->call('saveAssignment')->assertHasNoErrors()->assertSee('2 JP');
});

it('blocks inactive subjects in new assignments while keeping existing assignments editable', function () {
    $teacher = Gtk::create(['full_name' => 'Guru Pelajaran Nonaktif']);
    $subject = Subject::create(['code' => 'OLD', 'name' => 'Pelajaran Nonaktif', 'is_active' => false]);
    $assignment = TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $subject->id,
        'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id]);
    $this->post(route('admin.schedules.assignments.store'), [
        'subject_id' => $subject->id, 'gtk_id' => $teacher->id, 'school_class_id' => $this->class->id,
    ])->assertSessionHasErrors('subject_id');
    $component = Livewire::test('admin.gtks.show', ['gtk' => $teacher])->call('createAssignment');
    expect($component->viewData('subjects')->contains('id', $subject->id))->toBeFalse();
    $component->set('subjectId', $subject->id)->set('schoolClassId', $this->class->id)->set('academicYearId', $this->year->id)
        ->call('saveAssignment')->assertHasErrors('subjectId');
    $component->call('editAssignment', $assignment->id);
    expect($component->viewData('subjects')->contains('id', $subject->id))->toBeTrue();
    $component->set('assignmentIsActive', false)->call('saveAssignment')->assertHasNoErrors();
    expect($assignment->fresh()->is_active)->toBeFalse()->and(TeachingAssignment::count())->toBe(1)
        ->and($subject->fresh()->is_active)->toBeFalse();
});

it('requires an explicit year when creating a class', function () {
    $this->post(route('admin.classes.store'), ['name' => 'XI B', 'grade' => 11, 'is_active' => 1])->assertSessionHasErrors('academic_year_id');
});

it('creates biodata without silently changing membership', function () {
    Livewire::test('admin.students.index')->call('createStudent')->set('fullName', 'Siswa Manual')
        ->set('studentClassId', $this->class->id)->call('saveStudent')->assertHasNoErrors();
    expect(StudentClassHistory::where('academic_year_id', $this->year->id)->whereNull('semester_id')->count())->toBe(0);
});
