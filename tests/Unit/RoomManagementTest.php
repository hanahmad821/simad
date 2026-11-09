<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\ClassroomSeeder;
use Database\Seeders\RoomSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Room tests require an isolated in-memory database.');
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
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_10_27_000002_add_homeroom_teacher_to_school_classes.php',
        '2026_11_02_000003_add_room_to_school_classes.php',
    ] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->withoutVite();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->room = Room::create(['code' => 'R1', 'name' => 'Ruang Kelas Satu', 'building' => 'Gedung Utama', 'capacity' => 32]);
    $this->class = SchoolClass::create(['name' => 'X A', 'grade' => 10]);
    $this->data = ['code' => 'LAB', 'name' => 'Laboratorium IPA', 'building' => 'Gedung Sains', 'capacity' => 36, 'is_active' => true];
});

it('lists searches and filters rooms with a discoverable admin menu', function () {
    Room::create(['code' => 'ARS', 'name' => 'Ruang Arsip', 'is_active' => false]);
    $this->get(route('admin.rooms.index'))->assertOk()->assertSee('Manajemen Ruangan')->assertSee('Ruang Kelas Satu')
        ->assertSee('Ruang Arsip')->assertSee(route('admin.rooms.index'), false);
    foreach (['R1', 'Gedung Utama'] as $query) {
        $this->get(route('admin.rooms.index', ['q' => $query]))->assertOk()->assertSee('Ruang Kelas Satu')->assertDontSee('Ruang Arsip');
    }
    $this->get(route('admin.rooms.index', ['status' => 'active']))->assertOk()->assertSee('Ruang Kelas Satu')->assertDontSee('Ruang Arsip');
    $this->get(route('admin.rooms.index', ['status' => 'inactive']))->assertOk()->assertSee('Ruang Arsip')->assertDontSee('Ruang Kelas Satu');
    $this->get(route('admin.rooms.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
});

it('creates edits deactivates and deletes unused rooms', function () {
    $this->get(route('admin.rooms.create'))->assertOk()->assertSee('Kode ruangan');
    $this->post(route('admin.rooms.store'), $this->data)->assertRedirect(route('admin.rooms.index'));
    $room = Room::where('code', 'LAB')->firstOrFail();
    $this->get(route('admin.rooms.edit', $room))->assertOk()->assertSee('Laboratorium IPA');
    $this->put(route('admin.rooms.update', $room), array_replace($this->data, ['name' => 'Laboratorium Baru', 'is_active' => false]))->assertRedirect();
    expect($room->fresh()->name)->toBe('Laboratorium Baru')->and($room->fresh()->is_active)->toBeFalse();
    $this->delete(route('admin.rooms.destroy', $room))->assertRedirect(route('admin.rooms.index'));
    expect($room->fresh())->toBeNull();
});

it('validates room codes names capacities and status', function (array $changes, string $field) {
    $this->post(route('admin.rooms.store'), array_replace($this->data, $changes))->assertSessionHasErrors($field);
    expect(Room::count())->toBe(1);
})->with([
    [['code' => 'R1'], 'code'], [['name' => ''], 'name'], [['capacity' => 0], 'capacity'],
    [['capacity' => 65536], 'capacity'], [['is_active' => 'invalid'], 'is_active'],
]);

it('assigns an active default room to a class and allows clearing it', function () {
    $classData = ['name' => 'X A', 'grade' => 10, 'is_active' => true, 'room_id' => $this->room->id];
    $this->put(route('admin.classes.update', $this->class), $classData)->assertRedirect();
    expect($this->class->fresh()->room->id)->toBe($this->room->id);
    $this->get(route('admin.classes.edit', $this->class))->assertOk()->assertSee('Ruang kelas bawaan')->assertSee('Ruang Kelas Satu');
    $this->get(route('admin.rooms.index'))->assertOk()->assertSee('X A');
    $this->put(route('admin.classes.update', $this->class), array_replace($classData, ['room_id' => null]))->assertRedirect();
    expect($this->class->fresh()->room_id)->toBeNull();
});

it('rejects nonexistent or inactive rooms as new classroom defaults', function () {
    $this->room->update(['is_active' => false]);
    foreach ([$this->room->id, 999999] as $id) {
        $this->put(route('admin.classes.update', $this->class), ['name' => 'X A', 'grade' => 10, 'is_active' => true, 'room_id' => $id])->assertSessionHasErrors('room_id');
    }
    expect($this->class->fresh()->room_id)->toBeNull();
});

it('protects rooms assigned to classes while preserving the existing assignment on deactivation', function () {
    $this->class->update(['room_id' => $this->room->id]);
    $this->delete(route('admin.rooms.destroy', $this->room))->assertSessionHasErrors('room');
    $this->put(route('admin.rooms.update', $this->room), ['code' => 'R1', 'name' => 'Ruang Kelas Satu', 'is_active' => false])->assertRedirect();
    expect($this->class->fresh()->room_id)->toBe($this->room->id)->and($this->room->fresh()->is_active)->toBeFalse();
    $this->get(route('admin.classes.edit', $this->class))->assertOk()->assertSee('(Nonaktif)');
});

it('protects rooms used by historical schedules from deletion', function () {
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    $semester = Semester::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']);
    $teacher = Gtk::create(['full_name' => 'Guru Satu']);
    $subject = Subject::create(['code' => 'MAT', 'name' => 'Matematika']);
    $assignment = TeachingAssignment::create(['gtk_id' => $teacher->id, 'subject_id' => $subject->id, 'school_class_id' => $this->class->id,
        'academic_year_id' => $year->id, 'semester_id' => $semester->id]);
    $period = LessonPeriod::create(['name' => 'JP 1', 'period_number' => 1, 'start_time' => '07:00', 'end_time' => '07:45']);
    Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => $period->id, 'room_id' => $this->room->id,
        'day_of_week' => 1, 'is_active' => false]);
    $this->seed(ClassroomSeeder::class);
    expect($this->class->fresh()->room_id)->not->toBe($this->room->id)
        ->and(Schedule::first()->room_id)->toBe($this->room->id);
    $this->delete(route('admin.rooms.destroy', $this->room))->assertSessionHasErrors('room');
    expect(Schedule::count())->toBe(1)->and($this->room->fresh())->not->toBeNull();
});

it('restricts all room management actions to admins', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    foreach (['index', 'create', 'edit'] as $action) {
        $this->get(route('admin.rooms.'.$action, $action === 'edit' ? $this->room : []))->assertForbidden();
    }
    $this->post(route('admin.rooms.store'), $this->data)->assertForbidden();
    $this->put(route('admin.rooms.update', $this->room), $this->data)->assertForbidden();
    $this->delete(route('admin.rooms.destroy', $this->room))->assertForbidden();
    expect(Room::count())->toBe(1);
})->with(['teacher', 'staff', 'headmaster']);

it('adds and rolls back only the classroom room reference without changing existing records', function () {
    $migration = require database_path('migrations/2026_11_02_000003_add_room_to_school_classes.php');
    $migration->down();
    expect(Schema::hasColumn('school_classes', 'room_id'))->toBeFalse();
    $migration->up();
    expect($this->class->fresh()->room_id)->toBeNull()->and(SchoolClass::count())->toBe(1)->and(Room::count())->toBe(1);
});

it('seeds one matching default room per existing class and is unchanged on repeated runs', function () {
    SchoolClass::create(['name' => 'XI-B', 'grade' => 11, 'capacity' => 36]);
    SchoolClass::create(['name' => 'XII C', 'grade' => 12, 'capacity' => 20, 'is_active' => false]);
    $this->seed(ClassroomSeeder::class);
    expect(Room::count())->toBe(4)->and(SchoolClass::whereNotNull('room_id')->count())->toBe(3)
        ->and(SchoolClass::distinct()->count('room_id'))->toBe(3);
    foreach (SchoolClass::with('room')->get() as $class) {
        expect($class->room->name)->toBe($class->name)->and($class->room->code)->toBe('KLS-'.$class->id)
            ->and($class->room->capacity)->toBe($class->capacity)->and($class->room->is_active)->toBe($class->is_active);
    }
    $rooms = DB::table('rooms')->orderBy('id')->get()->toJson();
    $classes = DB::table('school_classes')->orderBy('id')->get()->toJson();
    $this->seed(ClassroomSeeder::class);
    expect(DB::table('rooms')->orderBy('id')->get()->toJson())->toBe($rooms)
        ->and(DB::table('school_classes')->orderBy('id')->get()->toJson())->toBe($classes);
});

it('reuses a matching existing room without replacing its capacity building or status', function () {
    $this->room->update(['name' => $this->class->name, 'capacity' => 40, 'is_active' => false]);
    $this->seed(ClassroomSeeder::class);
    expect(Room::count())->toBe(1)->and($this->class->fresh()->room_id)->toBe($this->room->id)
        ->and($this->room->fresh()->capacity)->toBe(40)->and($this->room->fresh()->is_active)->toBeFalse()
        ->and($this->room->fresh()->building)->toBe('Gedung Utama');
});

it('renames the generated classroom after a class rename without creating another room', function () {
    $this->seed(ClassroomSeeder::class);
    $roomId = $this->class->fresh()->room_id;
    $this->class->update(['name' => 'X-A Baru']);
    $this->seed(ClassroomSeeder::class);
    expect(Room::count())->toBe(2)->and($this->class->fresh()->room_id)->toBe($roomId)
        ->and(Room::find($roomId)->name)->toBe('X-A Baru');
});

it('includes dynamic classrooms in the main room seeder without duplicating facilities or overwriting their settings', function () {
    Room::create(['code' => 'LAB-IPA', 'name' => 'Lab Pilihan Admin', 'capacity' => 25, 'is_active' => false]);
    $this->seed(RoomSeeder::class);
    $this->seed(RoomSeeder::class);
    expect(Room::count())->toBe(4)->and($this->class->fresh()->room->name)->toBe($this->class->name)
        ->and(Room::where('code', 'LAB-KOM')->count())->toBe(1)
        ->and(Room::where('code', 'LAB-IPA')->first()->name)->toBe('Lab Pilihan Admin')
        ->and(Room::where('code', 'LAB-IPA')->first()->is_active)->toBeFalse()
        ->and(Room::where('code', 'R01')->exists())->toBeFalse();
});
