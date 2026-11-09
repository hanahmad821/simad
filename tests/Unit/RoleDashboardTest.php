<?php

use App\Models\Gtk;
use App\Models\IncomingLetter;
use App\Models\LetterDisposition;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Dashboard tests require an isolated in-memory database.');
    }

    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_081240_create_gtk_attendances_table.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_09_11_024249_create_incoming_letters_table.php',
        '2026_09_11_025419_create_letter_dispositions_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }

    foreach (['students', 'school_classes'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
        });
    }
    Schema::create('outgoing_letters', function (Blueprint $table) {
        $table->id();
        $table->string('status');
        $table->string('perihal')->default('Surat pengujian');
        $table->string('nomor_surat')->default('TEST-001');
        $table->string('tujuan')->default('Dinas');
        $table->timestamps();
    });
    $this->withoutVite();
});

it('redirects each role and renders its dashboard', function (string $role, string $heading) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($role.'.dashboard'));
    $this->get(route($role.'.dashboard'))->assertOk()->assertSee($heading)
        ->assertSee('Akses Cepat')->assertSee('belum terhubung');
})->with([
    ['admin', 'Dashboard Administrator'],
    ['headmaster', 'Dashboard Kepala Madrasah'],
    ['teacher', 'Dashboard Guru'],
    ['staff', 'Dashboard Staf'],
]);

it('rejects access to another roles dashboard', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    foreach (array_diff(['admin', 'headmaster', 'teacher', 'staff'], [$role]) as $other) {
        $this->get(route($other.'.dashboard'))->assertForbidden();
    }
})->with(['admin', 'headmaster', 'teacher', 'staff']);

it('shows only personal active dispositions and their overdue count', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $other = User::factory()->create(['role' => 'teacher']);
    foreach ([[$user, 'Tugas pribadi', 'pending'], [$other, 'Tugas rahasia pengguna lain', 'pending'], [$user, 'Tugas sudah selesai', 'completed']] as [$recipient, $subject, $status]) {
        $letter = IncomingLetter::create([
            'nomor_surat' => $subject, 'pengirim' => 'Dinas', 'perihal' => $subject,
            'tanggal_surat' => today(), 'tanggal_masuk' => today(),
        ]);
        LetterDisposition::create([
            'incoming_letter_id' => $letter->id, 'from_user_id' => $other->id,
            'to_user_id' => $recipient->id, 'instruction' => 'Tindak lanjuti',
            'status' => $status, 'deadline' => today()->subDay(),
        ]);
    }
    $this->actingAs($user)->get(route('staff.dashboard'))->assertOk()
        ->assertSee('Tugas pribadi')->assertSee('Terlambat')
        ->assertDontSee('Tugas rahasia pengguna lain')->assertDontSee('Tugas sudah selesai')
        ->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === 1 && $stats[2]['value'] === 1 && $stats[3]['value'] === 1);
});

it('uses live counts and limits the approval queue to pending letters', function () {
    DB::table('students')->insert([['is_active' => true], ['is_active' => false]]);
    DB::table('outgoing_letters')->insert([
        ['status' => 'pending', 'perihal' => 'Perlu persetujuan'],
        ['status' => 'draft', 'perihal' => 'Draft privat'],
        ['status' => 'issued', 'perihal' => 'Sudah terbit'],
    ]);
    $this->actingAs(User::factory()->create(['role' => 'headmaster']))
        ->get(route('headmaster.dashboard'))->assertOk()
        ->assertSee('Perlu persetujuan')->assertDontSee('Draft privat')->assertDontSee('Sudah terbit')
        ->assertViewHas('stats', fn ($stats) => $stats[1]['value'] === 1 && $stats[5]['value'] === 1);
});

it('shows only todays active teaching schedule for the linked teacher', function () {
    $this->travelTo(now()->startOfWeek()->setTime(9, 0));
    foreach (['academic_years', 'semesters'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active');
        });
        DB::table($name)->insert(['id' => 1, 'is_active' => true]);
    }
    foreach (['subjects', 'rooms'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }
    Schema::create('lesson_periods', function (Blueprint $table) {
        $table->id();
        $table->time('start_time');
        $table->time('end_time');
    });
    Schema::create('teaching_assignments', function (Blueprint $table) {
        $table->id();
        foreach (['gtk_id', 'subject_id', 'school_class_id', 'academic_year_id', 'semester_id'] as $name) {
            $table->unsignedBigInteger($name)->nullable();
        }
        $table->boolean('is_active');
    });
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('teaching_assignment_id');
        $table->unsignedBigInteger('lesson_period_id');
        $table->unsignedBigInteger('room_id')->nullable();
        $table->integer('day_of_week');
        $table->boolean('is_active');
    });
    $gtk = Gtk::create(['full_name' => 'Guru Pengujian']);
    $user = User::factory()->create(['role' => 'teacher', 'gtk_id' => $gtk->id]);
    DB::table('subjects')->insert([['id' => 1, 'name' => 'Matematika pribadi'], ['id' => 2, 'name' => 'Pelajaran guru lain']]);
    DB::table('lesson_periods')->insert(['id' => 1, 'start_time' => '07:00', 'end_time' => '07:45']);
    foreach ([1, 2] as $id) {
        DB::table('teaching_assignments')->insert([
            'id' => $id, 'gtk_id' => $id === 1 ? $gtk->id : $gtk->id + 1,
            'subject_id' => $id, 'academic_year_id' => 1, 'semester_id' => 1, 'is_active' => true,
        ]);
        DB::table('schedules')->insert([
            'teaching_assignment_id' => $id, 'lesson_period_id' => 1, 'day_of_week' => 1, 'is_active' => true,
        ]);
    }
    $this->actingAs($user)->get(route('teacher.dashboard'))->assertOk()
        ->assertSee('Matematika pribadi')->assertDontSee('Pelajaran guru lain')->assertSee('07:00')
        ->assertViewHas('schedules', fn ($schedules) => $schedules->count() === 1);
    DB::table('semesters')->update(['is_active' => false]);
    $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Matematika pribadi');
    DB::table('academic_years')->update(['is_active' => false]);
    $this->get(route('teacher.dashboard'))->assertOk()->assertDontSee('Matematika pribadi');
});

it('lists the login email and searches GTK by account email', function () {
    $gtk = Gtk::create(['full_name' => 'GTK Email Contoh', 'email' => 'kontak@example.test']);
    User::factory()->create(['role' => 'teacher', 'gtk_id' => $gtk->id, 'email' => 'login@example.test']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $page = \Livewire\Livewire::test('admin.gtks.index')
        ->assertSee('login@example.test')->set('search', 'login@example.test')
        ->assertSee('GTK Email Contoh');
    $page->set('search', 'tidak-ada@example.test')->assertDontSee('GTK Email Contoh');
});

it('creates GTK with dropdown positions and assigns the matching account role', function (string $position, string $role) {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $page = \Livewire\Livewire::test('admin.gtks.index')->call('create')
        ->set('full_name', 'GTK Baru')->set('gender', 'male')
        ->set('employment_status', 'non_pns')->set('position', $position)
        ->call('save')->assertHasNoErrors();
    $gtk = Gtk::where('full_name', 'GTK Baru')->firstOrFail();
    $page->call('generateAccount', $gtk->id);
    expect($gtk->user->role)->toBe($role);
})->with([
    ['Kepala Madrasah', 'headmaster'],
    ['Guru Mata Pelajaran', 'teacher'],
    ['Staff', 'staff'],
]);

it('rejects arbitrary new positions while preserving an existing legacy position when editing', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $page = \Livewire\Livewire::test('admin.gtks.index')->call('create')
        ->set('full_name', 'GTK Baru')->set('gender', 'male')
        ->set('employment_status', 'non_pns')->set('position', 'Jabatan bebas')
        ->call('save')->assertHasErrors('position');
    expect(Gtk::count())->toBe(0);
    $gtk = Gtk::create(['full_name' => 'GTK Lama', 'gender' => 'male', 'position' => 'Guru Bahasa Arab']);
    $page->call('edit', $gtk->id)->assertSet('position', 'Guru Bahasa Arab')
        ->call('save')->assertHasNoErrors();
    expect($gtk->fresh()->position)->toBe('Guru Bahasa Arab');
});
