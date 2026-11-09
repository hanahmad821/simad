<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use App\Services\StudentCardService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Card tests require an isolated in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_020757_create_academic_years_table.php',
        '2026_08_26_021543_create_semesters_table.php',
        '2026_08_26_025138_create_school_classes_table.php',
        '2026_08_26_074302_create_students_table.php',
        '2026_08_26_075134_create_student_class_histories_table.php',
        '2026_08_26_081832_create_school_settings_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    SchoolSetting::create(['school_name' => 'Madrasah Pengujian']);
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $semester = Semester::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    foreach (['A', 'B'] as $key) {
        $class = SchoolClass::create(['name' => 'X '.$key, 'grade' => 10]);
        $student = Student::create(['student_code' => 'S-ABCDEF123456'.$key, 'full_name' => 'Siswa '.$key, 'nis' => 'NIS-'.$key, 'nisn' => 'NISN-'.$key,
            'nik' => 'NIK-RAHASIA-'.$key, 'guardian_phone' => 'TELP-RAHASIA-'.$key, 'address' => 'ALAMAT-RAHASIA-'.$key]);
        StudentClassHistory::create(['student_id' => $student->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'semester_id' => $semester->id]);
        $this->{'student'.$key} = $student;
        $this->{'class'.$key} = $class;
    }
});

it('prints identity and compatible QR codes without exposing private student fields', function () {
    $this->get(route('admin.students.cards'))->assertOk()->assertSee('Madrasah Pengujian')
        ->assertSee('54 × 85,6 mm')->assertSee('data:image/svg+xml;base64,', false)
        ->assertSee('MADIKA — Madrasah Digital Kita')->assertSee('QR code presensi')
        ->assertSee('Siswa A')->assertSee('NIS-A')->assertSee('NISN-A')->assertSee('2026/2027')
        ->assertDontSee('NIK-RAHASIA')->assertDontSee('TELP-RAHASIA')->assertDontSee('ALAMAT-RAHASIA');
});

it('supports one student or the complete class selection', function () {
    $this->get(route('admin.students.cards', ['student_id' => $this->studentA->id]))->assertOk()
        ->assertSee('Kartu Siswa A')->assertDontSee('Kartu Siswa B');
    $this->get(route('admin.students.cards', ['school_class_id' => $this->classB->id]))->assertOk()
        ->assertSee('Kartu Siswa B')->assertDontSee('Kartu Siswa A');
});

it('respects status and search filters without changing student codes', function () {
    $original = $this->studentA->student_code;
    $this->studentB->update(['is_active' => false]);
    $this->get(route('admin.students.cards'))->assertOk()->assertDontSee('Kartu Siswa B');
    $this->get(route('admin.students.cards', ['status' => 'inactive', 'search' => 'NIS-B']))->assertOk()
        ->assertSee('Kartu Siswa B')->assertDontSee('Kartu Siswa A');
    $this->get(route('admin.students.cards', ['student_id' => $this->studentA->id]))->assertOk();
    expect($this->studentA->fresh()->student_code)->toBe($original);
});

it('chunks the print sheet into nine cards per A4 page', function () {
    foreach (range(1, 8) as $number) {
        Student::create(['student_code' => 'S-TEST-'.$number, 'full_name' => 'Siswa tambahan '.$number]);
    }
    $response = $this->get(route('admin.students.cards'))->assertOk();
    expect(substr_count($response->getContent(), '<section class="sheet"'))->toBe(2)
        ->and(substr_count($response->getContent(), '<article class="card"'))->toBe(10);
});

it('restricts student cards to admins', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.students.cards'))->assertForbidden();
})->with(['teacher', 'staff', 'headmaster']);

it('handles missing card data and rejects invalid filters', function () {
    SchoolSetting::query()->delete();
    DB::table('students')->where('id', $this->studentA->id)->update(['student_code' => '']);
    $this->get(route('admin.students.cards', ['student_id' => $this->studentA->id]))->assertOk()
        ->assertSee('Nama Madrasah Belum Diisi')->assertSee('QR code belum tersedia.');
    $this->getJson(route('admin.students.cards', ['school_class_id' => 99999]))->assertUnprocessable();
});

it('generates an SVG for the exact student code and refuses unsupported values', function () {
    $service = app(StudentCardService::class);
    $uri = $service->qrCodeDataUri($this->studentA->student_code);
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg')->toContain('viewBox=')->not->toContain('preserveAspectRatio="none"')
        ->and($service->qrCodeDataUri(null))->toBeNull()
        ->and($service->qrCodeDataUri("bad\ncode"))->toBeNull();
});
