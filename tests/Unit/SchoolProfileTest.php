<?php

use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Profile tests require an in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_09_14_044921_add_headmaster_fields_to_school_settings_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

it('renders the settings page and creates then updates the shared profile', function () {
    $this->withoutVite();
    $this->get(route('admin.school-profile.edit'))->assertOk()->assertSee('Simpan Profil Madrasah')
        ->assertSee('x-data="schoolLocationPicker"', false)->assertSee('x-ref="locationMap"', false)
        ->assertSee('Lihat area presensi')->assertSee('Batalkan pencarian');
    $this->put(route('admin.school-profile.update'), [
        'school_name' => 'MA Pengujian', 'npsn' => '00123456',
        'headmaster_name' => 'Kepala Pengujian', 'attendance_radius' => 100,
        'latitude' => -7.6, 'longitude' => 109.3,
    ])->assertRedirect(route('admin.school-profile.edit'))->assertSessionHasNoErrors();
    $this->put(route('admin.school-profile.update'), [
        'school_name' => 'MA Diperbarui', 'attendance_radius' => 200,
    ])->assertSessionHasNoErrors();
    expect(SchoolSetting::count())->toBe(1);
    expect(SchoolSetting::first()->school_name)->toBe('MA Diperbarui');
    expect(SchoolSetting::first()->npsn)->toBe('00123456');
    $this->get(route('admin.school-profile.edit'))->assertOk()->assertSee('MA Diperbarui');
});

it('rejects invalid data without modifying the profile', function () {
    SchoolSetting::create(['school_name' => 'Asli']);
    $this->put(route('admin.school-profile.update'), [
        'school_name' => '', 'email' => 'invalid', 'latitude' => 100,
        'attendance_radius' => -1,
    ])->assertSessionHasErrors(['school_name', 'email', 'latitude', 'longitude', 'attendance_radius']);
    expect(SchoolSetting::first()->school_name)->toBe('Asli');
});

it('restricts profile reads and writes to admins', function () {
    foreach (['teacher', 'staff', 'headmaster'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('admin.school-profile.edit'))->assertForbidden();
        $this->put(route('admin.school-profile.update'), ['school_name' => 'Unauthorized', 'attendance_radius' => 100])->assertForbidden();
    }
    expect(SchoolSetting::count())->toBe(0);
});
