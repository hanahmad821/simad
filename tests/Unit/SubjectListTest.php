<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);
beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Tests require isolated database.');
    }
    foreach (['0001_01_01_000000_create_users_table.php', '2025_08_14_170933_add_two_factor_columns_to_users_table.php', '2026_08_26_064935_create_gtks_table.php', '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php', '2026_08_26_020757_create_academic_years_table.php', '2026_08_26_021543_create_semesters_table.php', '2026_08_26_025138_create_school_classes_table.php', '2026_08_26_074302_create_students_table.php', '2026_08_26_075134_create_student_class_histories_table.php', '2026_10_27_000002_add_homeroom_teacher_to_school_classes.php'] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->year = $year;
    $this->class = SchoolClass::create(['name' => 'X A', 'grade' => 10, 'capacity' => 1]);
    $this->student = Student::create(['student_code' => 'S1', 'full_name' => 'Siswa Satu']);
});

beforeEach(function () {
    foreach (['2026_08_26_025918_create_subjects_table.php', '2026_08_26_071443_create_teaching_assignments_table.php', '2026_08_26_081832_create_school_settings_table.php', '2026_11_02_000002_make_kbm_independent_of_semesters.php'] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika', 'category' => 'Umum']);
    $this->teacher = Gtk::create(['full_name' => 'Guru Matematika']);
    TeachingAssignment::create(['subject_id' => $this->subject->id, 'gtk_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id]);
    $this->data = ['code' => 'BIO', 'name' => 'Biologi', 'category' => 'Umum', 'is_active' => true];
});
it('lists subjects with their current teachers and class links', function () {
    $this->get(route('admin.subjects.index'))->assertOk()->assertSee('Matematika')->assertSee('Guru Matematika')->assertSee('X A')->assertSee(route('admin.classes.show', [$this->class, 'academic_year_id' => $this->year->id]), false);
});
it('searches by name or code and filters teacher assignments by school year', function () {
    $other = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);
    $this->get(route('admin.subjects.index', ['q' => 'MAT']))->assertOk()->assertSee('Matematika');
    $this->get(route('admin.subjects.index', ['q' => 'Biologi']))->assertOk()->assertSee('Tidak ada mata pelajaran yang sesuai')->assertDontSee('Guru Matematika');
    $this->get(route('admin.subjects.index', ['academic_year_id' => $other->id]))->assertOk()->assertSee('Matematika')->assertDontSee('Guru Matematika')->assertSee('Belum ada pengampu pada tahun pelajaran ini');
});
it('requires admin access and validates the school year', function () {
    $this->get(route('admin.subjects.index', ['academic_year_id' => 999999]))->assertSessionHasErrors('academic_year_id');
    $this->actingAs(User::factory()->create(['role' => 'teacher']))->get(route('admin.subjects.index'))->assertForbidden();
});

it('creates edits deactivates and deletes unused subjects through the admin pages', function () {
    $this->get(route('admin.subjects.index'))->assertOk()->assertSee('Tambah Mata Pelajaran')
        ->assertSee(route('admin.subjects.create'), false)->assertSee(route('admin.subjects.edit', $this->subject), false);
    $this->get(route('admin.subjects.create'))->assertOk()->assertSee('Kode mata pelajaran')->assertSee('Kategori (opsional)');
    $this->post(route('admin.subjects.store'), $this->data)->assertRedirect(route('admin.subjects.index'));
    $subject = Subject::where('code', 'BIO')->firstOrFail();
    $this->get(route('admin.subjects.index'))->assertOk()->assertSee(route('admin.subjects.destroy', $subject), false);
    $this->get(route('admin.subjects.edit', $subject))->assertOk()->assertSee('Biologi');
    $this->put(route('admin.subjects.update', $subject), array_replace($this->data, ['name' => 'Biologi Terapan', 'category' => null, 'is_active' => false]))
        ->assertRedirect(route('admin.subjects.index'));
    expect($subject->fresh()->name)->toBe('Biologi Terapan')->and($subject->fresh()->category)->toBeNull()
        ->and($subject->fresh()->is_active)->toBeFalse();
    $this->delete(route('admin.subjects.destroy', $subject))->assertRedirect(route('admin.subjects.index'));
    expect($subject->fresh())->toBeNull()->and(Subject::count())->toBe(1);
});

it('validates subject fields and unique codes on creation and editing', function (array $changes, string $field) {
    $this->post(route('admin.subjects.store'), array_replace($this->data, $changes))->assertSessionHasErrors($field);
    $other = Subject::create($this->data);
    $this->put(route('admin.subjects.update', $other), array_replace($this->data, $changes))->assertSessionHasErrors($field);
    expect($other->fresh()->name)->toBe('Biologi')->and(Subject::count())->toBe(2);
})->with([
    [['code' => 'MAT'], 'code'], [['code' => ''], 'code'], [['code' => str_repeat('X', 31)], 'code'],
    [['name' => ''], 'name'], [['name' => str_repeat('X', 101)], 'name'],
    [['category' => str_repeat('X', 51)], 'category'], [['is_active' => 'invalid'], 'is_active'],
]);

it('protects all historical assignments from subject deletion regardless of the selected school year', function () {
    $assignment = TeachingAssignment::firstOrFail();
    $assignment->update(['is_active' => false]);
    $this->year->update(['is_active' => false]);
    $year = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => true]);
    $this->get(route('admin.subjects.index', ['academic_year_id' => $year->id]))->assertOk()
        ->assertSee('Nonaktifkan melalui Edit')->assertDontSee('method="DELETE"', false);
    $this->delete(route('admin.subjects.destroy', $this->subject))->assertSessionHasErrors('subject');
    $this->put(route('admin.subjects.update', $this->subject), [
        'code' => 'MAT', 'name' => 'Matematika', 'category' => 'Umum', 'is_active' => false,
    ])->assertRedirect(route('admin.subjects.index'));
    expect($this->subject->fresh()->is_active)->toBeFalse()->and($assignment->fresh())->not->toBeNull()
        ->and($assignment->fresh()->subject_id)->toBe($this->subject->id)->and($assignment->fresh()->is_active)->toBeFalse();
    $this->put(route('admin.subjects.update', $this->subject), [
        'code' => 'MAT', 'name' => 'Matematika', 'category' => 'Umum', 'is_active' => true,
    ])->assertRedirect();
    expect($this->subject->fresh()->is_active)->toBeTrue();
});

it('restricts all subject management actions to administrators', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->get(route('admin.subjects.index'))->assertForbidden();
    $this->get(route('admin.subjects.create'))->assertForbidden();
    $this->get(route('admin.subjects.edit', $this->subject))->assertForbidden();
    $this->post(route('admin.subjects.store'), $this->data)->assertForbidden();
    $this->put(route('admin.subjects.update', $this->subject), $this->data)->assertForbidden();
    $this->delete(route('admin.subjects.destroy', $this->subject))->assertForbidden();
    expect(Subject::count())->toBe(1)->and($this->subject->fresh()->code)->toBe('MAT');
})->with(['teacher', 'staff', 'headmaster']);
