<?php

use App\Models\LetterType;
use App\Models\OutgoingLetter;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\LetterTemplateCatalog;
use App\Services\LetterTemplateService;
use App\Services\LetterTypeService;
use App\Services\OutgoingLetterPdfService;
use App\Services\OutgoingLetterWorkflowService;
use Database\Seeders\LetterTypeSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Letter type tests require an isolated database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php', '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php', '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_020757_create_academic_years_table.php', '2026_08_26_021543_create_semesters_table.php',
        '2026_08_26_025138_create_school_classes_table.php', '2026_08_26_074302_create_students_table.php',
        '2026_08_26_075134_create_student_class_histories_table.php', '2026_08_26_081832_create_school_settings_table.php',
        '2026_09_11_091830_create_letter_types_table.php', '2026_09_12_144411_add_fields_to_letter_types_table.php',
        '2026_09_28_005754_add_template_view_to_letter_types_table.php', '2026_09_11_091309_create_letter_sequences_table.php',
        '2026_09_11_091356_create_outgoing_letters_table.php', '2026_09_12_144048_add_data_to_outgoing_letters_table.php',
        '2026_09_14_084622_add_approval_fields_to_outgoing_letters_table.php', '2026_09_14_185326_add_verification_token_to_outgoing_letters_table.php',
        '2026_09_23_033605_add_final_file_to_outgoing_letters_table.php', '2026_10_27_000001_add_signature_method_to_outgoing_letters.php',
        '2026_11_02_000007_add_letter_template_editor.php',
        '2026_11_03_000001_add_rich_letter_body.php',
        '2026_11_03_000002_create_outgoing_letter_attachments.php',
    ] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    Schema::table('outgoing_letters', function (Blueprint $table) {
        $table->boolean('use_template')->default(true);
        $table->string('jenis_surat')->nullable();
        $table->string('final_pdf_file')->nullable();
        $table->string('final_docx_file')->nullable();
    });
    Schema::table('school_settings', function (Blueprint $table) {
        $table->string('headmaster_name')->nullable();
        $table->string('headmaster_nip')->nullable();
    });
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 11, 2));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->headmaster = User::factory()->create(['role' => 'headmaster']);
    $this->actingAs($this->admin);
    SchoolSetting::create(['school_name' => 'Madrasah Contoh', 'headmaster_name' => 'Kepala Contoh', 'headmaster_nip' => '12345']);
    $this->student = Student::create(['student_code' => 'LT-1', 'full_name' => 'Siswa Contoh', 'nisn' => '123', 'is_active' => true]);
    $this->service = app(LetterTypeService::class);
    $this->data = ['name' => 'Surat Keterangan Beasiswa', 'code' => 'A', 'is_active' => true, 'template_view' => 'pdf.letters.siswa-aktif'];
});


beforeEach(function () {
    Schema::table('school_settings', function (Blueprint $table) {
        $table->string('headmaster_rank')->nullable();
        $table->string('headmaster_position')->nullable();
    });
    SchoolSetting::first()->update(['headmaster_rank' => 'Pembina Tk 1/IVb', 'headmaster_position' => 'Kepala Madrasah', 'address' => 'Sirau RT 03/RW 02 Kemranjen, Banyumas.']);
    $this->gtkA = \App\Models\Gtk::create(['full_name' => 'Guru Pertama', 'nip' => '197505012005012001', 'position' => 'Guru', 'is_active' => true]);
    $this->gtkB = \App\Models\Gtk::create(['full_name' => 'Guru Kedua', 'position' => 'Guru Bahasa Arab', 'is_active' => true]);
    $this->type = $this->service->save($this->admin, ['name' => 'Surat Tugas', 'code' => 'E', 'is_active' => true, 'template_view' => 'pdf.letters.tugas']);
});

function assignmentTestData(int $first, ?int $second = null): array
{
    $rows = [['gtk_id' => (string) $first, 'pangkat' => '']];
    if ($second) $rows[] = ['gtk_id' => (string) $second, 'pangkat' => 'Penata/III c'];

    return ['penerima_tugas' => $rows, 'tugas' => 'Penyusunan materi Bahasa Arab tahun ajaran 2026/2027 (Membawa laptop)',
        'tanggal_tugas' => '2026-08-03', 'waktu' => 'Pukul 09.00 WIB s/d selesai', 'tempat' => 'RM Serayu (Argo Baceman Serayu Park)'];
}

it('creates a multi-recipient assignment with automatic target and frozen identities', function () {
    $data = assignmentTestData($this->gtkA->id, $this->gtkB->id);
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id)
        ->assertSee('Tambah Penerima Tugas')->set('formData', $data)->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    expect($letter->tujuan)->toBe('Guru Pertama, Guru Kedua')->and($letter->template_snapshot['uses_body'])->toBeFalse()
        ->and($letter->template_snapshot['assignment']['recipients'])->toHaveCount(2)
        ->and($letter->template_snapshot['assignment']['recipients'][0]['pangkat'])->toBe('-')
        ->and($letter->template_snapshot['assignment']['recipients'][1]['nip'])->toBe('-')
        ->and($letter->template_snapshot['assignment']['recipients'][1]['pangkat'])->toBe('Penata/III c')
        ->and($letter->template_snapshot['assignment']['signer']['pangkat'])->toBe('Pembina Tk 1/IVb');
    $this->get(route('admin.outgoing-letters.show', $letter))->assertOk()->assertSee('Guru Pertama')->assertSee('Guru Kedua')->assertSee('Senin, 03 Agustus 2026');
    $this->actingAs($this->headmaster);
    $this->get(route('headmaster.outgoing-letters.show', $letter))->assertOk()->assertSee('Guru Pertama')->assertSee('Guru Bahasa Arab');
});

it('requires valid and unique active GTK recipients and a complete schedule before allocating a number', function (string $invalid) {
    $data = assignmentTestData($this->gtkA->id, $this->gtkB->id);
    $expected = match ($invalid) {
        'empty' => 'formData.penerima_tugas', 'duplicate', 'inactive', 'missing' => 'formData.penerima_tugas.1.gtk_id',
        'forged' => 'formData.penerima_tugas.0', 'rank' => 'formData.penerima_tugas.0.pangkat',
        'date' => 'formData.tanggal_tugas', 'task' => 'formData.tugas', 'limit' => 'formData.penerima_tugas',
    };
    match ($invalid) {
        'empty' => $data['penerima_tugas'] = [],
        'duplicate' => $data['penerima_tugas'][1]['gtk_id'] = $this->gtkA->id,
        'inactive' => $this->gtkB->update(['is_active' => false]),
        'missing' => $data['penerima_tugas'][1]['gtk_id'] = 999999,
        'forged' => $data['penerima_tugas'][0]['nama'] = 'Nama palsu',
        'rank' => $data['penerima_tugas'][0]['pangkat'] = str_repeat('x', 101),
        'date' => $data['tanggal_tugas'] = 'Senin, # Agustus 2026',
        'task' => $data['tugas'] = '',
        'limit' => $data['penerima_tugas'] = array_fill(0, 21, $data['penerima_tugas'][0]),
    };
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id)
        ->set('formData', $data)->call('save')->assertHasErrors($expected);
    expect(OutgoingLetter::count())->toBe(0)->and(DB::table('letter_sequences')->count())->toBe(0);
})->with(['empty', 'duplicate', 'inactive', 'missing', 'forged', 'rank', 'date', 'task', 'limit']);

it('edits structured assignment recipients while retaining the old template if the master changes', function () {
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id)
        ->set('formData', assignmentTestData($this->gtkA->id, $this->gtkB->id))->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    $this->service->save($this->admin, ['name' => $this->type->name, 'code' => 'E', 'is_active' => true, 'template_view' => 'pdf.letters.undangan'], $this->type);
    Livewire::test('admin.outgoing-letters.show', ['outgoingLetter' => $letter->fresh()])->call('edit')
        ->call('removeAssignmentRecipient', 'penerima_tugas', 0)->set('formData.tugas', 'Tugas yang direvisi')
        ->call('updateLetter')->assertHasNoErrors();
    expect($letter->fresh()->template_snapshot['template_view'])->toBe('pdf.letters.tugas')
        ->and($letter->fresh()->template_snapshot['assignment']['recipients'])->toHaveCount(1)
        ->and($letter->fresh()->template_snapshot['assignment']['recipients'][0]['nama'])->toBe('Guru Kedua')
        ->and($letter->fresh()->tujuan)->toBe('Guru Kedua');
});

it('keeps assignment names and signer unchanged when master records change or are deleted', function () {
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id)
        ->set('formData', assignmentTestData($this->gtkA->id, $this->gtkB->id))->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    $this->gtkA->update(['full_name' => 'Nama Baru']);
    $this->gtkB->delete();
    SchoolSetting::first()->update(['headmaster_name' => 'Kepala Baru']);
    $html = view('pdf.letters.tugas', ['letter' => $letter, 'data' => $letter->data, 'school' => SchoolSetting::first(), 'qrPath' => null])->render();
    expect($html)->toContain('Guru Pertama', 'Guru Kedua', 'Kepala Contoh')->not->toContain('Nama Baru', 'Kepala Baru');
});

it('supports add and remove controls and continues to require a target for manual assignment letters', function () {
    $page = Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id);
    expect($page->get('formData.penerima_tugas'))->toHaveCount(1);
    $page->call('addAssignmentRecipient', 'penerima_tugas');
    expect($page->get('formData.penerima_tugas'))->toHaveCount(2);
    $page->call('removeAssignmentRecipient', 'penerima_tugas', 0);
    expect($page->get('formData.penerima_tugas'))->toHaveCount(1);
    $page->set('useTemplate', false)->call('save')->assertHasErrors('tujuan');
});

it('rolls back a draft edit if capturing its identities fails', function () {
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id)
        ->set('formData', assignmentTestData($this->gtkA->id))->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    $before = $letter->data;
    $page = Livewire::test('admin.outgoing-letters.show', ['outgoingLetter' => $letter])->call('edit');
    $this->mock(\App\Services\AssignmentLetterService::class, function ($mock) {
        $mock->makePartial();
        $mock->shouldReceive('recipientSummary')->andReturn('Guru Pertama');
        $mock->shouldReceive('resolve')->once()->andThrow(new RuntimeException('Identity capture failed'));
    });
    expect(fn () => $page->set('formData.tugas', 'Perubahan belum tersimpan')->call('updateLetter'))->toThrow(RuntimeException::class, 'Identity capture failed');
    expect($letter->fresh()->data)->toBe($before);
});

it('issues a real assignment PDF with either signature method and escapes user text', function (string $method) {
    $data = assignmentTestData($this->gtkA->id, $this->gtkB->id);
    $data['tugas'] .= ' <script>alert(1)</script>';
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $this->type->id)
        ->set('signatureMethod', $method)->set('formData', $data)->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    $html = view('pdf.letters.tugas', ['letter' => $letter, 'data' => $letter->data, 'school' => SchoolSetting::first(), 'qrPath' => null])->render();
    expect($html)->toContain('&lt;script&gt;', 'Senin, 03 Agustus 2026')->not->toContain('<script>');
    app(OutgoingLetterWorkflowService::class)->submit($letter);
    $this->actingAs($this->headmaster);
    app(OutgoingLetterWorkflowService::class)->approve($letter);
    $letter->refresh();
    $file = storage_path('app/'.$letter->final_pdf_file);
    try {
        expect($letter->status)->toBe($method === 'qr' ? 'issued' : 'awaiting_signature')
            ->and(file_get_contents($file))->toStartWith('%PDF-');
    } finally { @unlink($file); }
})->with(['wet', 'qr']);
