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

function viewTemplateLetter(LetterType $type, int $creator): OutgoingLetter
{
    static $sequence = 0;
    $sequence++;

    return OutgoingLetter::create(['letter_type_id' => $type->id, 'nomor_urut' => $sequence, 'nomor_tahun' => 2026,
        'nomor_surat' => 'A.TEST-'.$sequence.'/2026', 'jenis_surat' => $type->name, 'tujuan' => 'Penerima Surat',
        'tanggal_surat' => '2026-11-02', 'perihal' => 'Keterangan', 'use_template' => true, 'signature_method' => 'wet',
        'data' => ['keperluan' => 'Keperluan awal'], 'created_by' => $creator]);
}

it('replaces the editor menu with searchable letter types and predefined template choices', function () {
    $type = $this->service->save($this->admin, $this->data);
    $this->service->save($this->admin, array_replace($this->data, ['name' => 'Jenis Nonaktif', 'is_active' => false]));
    $this->get(route('admin.letter-types.index'))->assertOk()->assertSee('Tambah Jenis Surat')
        ->assertSee(route('admin.letter-types.create'), false)->assertSee('Surat Keterangan Beasiswa')->assertSee('Surat Keterangan Siswa Aktif')->assertDontSee('Master Template Surat');
    $this->get(route('admin.letter-types.index', ['q' => 'Beasiswa', 'status' => 'active']))->assertOk()->assertSee('Surat Keterangan Beasiswa')->assertDontSee('Jenis Nonaktif');
    $this->get(route('admin.letter-types.create'))->assertOk()->assertSee('Template surat')->assertSee('Surat Undangan')->assertSee('Surat Dispensasi')->assertSee('Tahun Pelajaran')->assertDontSee('Editor Isi Template');
    $this->get(route('admin.letter-types.edit', $type))->assertOk()->assertSee('Surat Keterangan Beasiswa');
    $this->get('/admin/letter-templates')->assertNotFound();
    $this->post('/admin/letter-templates/preview')->assertNotFound();
});

it('creates edits and deletes an unused letter type and derives its fields from the chosen view', function () {
    $this->post(route('admin.letter-types.store'), $this->data + ['fields' => [['name' => 'fake']], 'body_html' => '<p>Ignored</p>', 'template_file' => 'fake.docx'])->assertRedirect();
    $type = LetterType::firstOrFail();
    expect($type->fields)->toBe(config('letter_templates')['pdf.letters.siswa-aktif']['fields'])
        ->and($type->body_html)->toBeNull()->and($type->template_file)->toBeNull();
    $this->put(route('admin.letter-types.update', $type), array_replace($this->data, ['name' => 'Surat Undangan Rapat', 'code' => 'b.1', 'template_view' => 'pdf.letters.undangan', 'is_active' => false]))->assertRedirect();
    expect($type->fresh()->fields)->toBe([])->and($type->fresh()->template_view)->toBe('pdf.letters.undangan')
        ->and($type->fresh()->code)->toBe('B.1')->and($type->fresh()->is_active)->toBeFalse()->and($type->fresh()->template_version)->toBe(2);
    $this->delete(route('admin.letter-types.destroy', $type))->assertRedirect(route('admin.letter-types.index'));
    expect($type->fresh())->toBeNull();
});

it('rejects invalid metadata and arbitrary view paths', function (array $changes, string $field) {
    $this->post(route('admin.letter-types.store'), array_replace($this->data, $changes))->assertSessionHasErrors($field);
    expect(LetterType::count())->toBe(0);
})->with([
    [['name' => '  '], 'name'], [['code' => 'A/../B'], 'code'], [['is_active' => 'invalid'], 'is_active'],
    [['template_view' => 'pdf.letters.layouts.letter'], 'template_view'],
    [['template_view' => 'admin.school-profile'], 'template_view'],
    [['template_view' => '../../secrets'], 'template_view'],
]);

it('allows shared classification codes but rejects duplicate type names', function () {
    $type = $this->service->save($this->admin, $this->data);
    $this->post(route('admin.letter-types.store'), $this->data)->assertSessionHasErrors('name');
    $this->post(route('admin.letter-types.store'), array_replace($this->data, ['name' => 'Keterangan untuk Bank']))->assertRedirect();
    $this->put(route('admin.letter-types.update', $type), $this->data)->assertRedirect();
    expect(LetterType::count())->toBe(2);
});

it('excludes missing and unregistered views from available template choices', function () {
    config(['letter_templates' => config('letter_templates') + ['pdf.letters.missing' => ['name' => 'Missing', 'fields' => []]]]);
    expect(array_keys(app(LetterTemplateCatalog::class)->all()))->not->toContain('pdf.letters.missing');
    config(['letter_templates' => []]);
    $this->post(route('admin.letter-types.store'), $this->data)->assertSessionHasErrors('template_view');
});

it('offers manual-only types only in manual creation and rejects forced use as a template', function () {
    $manual = $this->service->save($this->admin, array_replace($this->data, ['name' => 'Surat Manual Khusus', 'template_view' => null]));
    expect($manual->fields)->toBe([])->and($manual->template_view)->toBeNull();
    $component = Livewire::test('admin.outgoing-letters.index')->call('openCreate');
    expect($component->viewData('templateLetterTypes'))->toHaveCount(0);
    $component->set('letterTypeIdForm', (string) $manual->id)->call('save')->assertHasErrors('letterTypeIdForm');
    $component->set('useTemplate', false)->set('tujuan', 'Penerima')->set('perihal', 'Manual')->call('save')->assertHasNoErrors();
    expect(OutgoingLetter::firstOrFail()->use_template)->toBeFalse();
});

it('uses fixed student inputs and keeps the previous view and form when the master selection changes', function () {
    $type = $this->service->save($this->admin, $this->data);
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $type->id)
        ->assertSee('Tahun Pelajaran')->assertDontSee('label="Isi Surat"', false)
        ->set('tujuan', 'Penerima')->set('formData.student_id', $this->student->id)->set('formData.tahun_pelajaran', '2026/2027')
        ->set('formData.keperluan', 'Beasiswa')->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    $this->service->save($this->admin, array_replace($this->data, ['template_view' => 'pdf.letters.undangan']), $type);
    expect($letter->fresh()->template_snapshot['template_view'])->toBe('pdf.letters.siswa-aktif')
        ->and($letter->fresh()->template_snapshot['uses_body'])->toBeFalse();
    Livewire::test('admin.outgoing-letters.show', ['outgoingLetter' => $letter->fresh()])->call('edit')
        ->set('formData.keperluan', 'Revisi beasiswa')->call('updateLetter')->assertHasNoErrors();
    expect($letter->fresh()->data['keperluan'])->toBe('Revisi beasiswa')
        ->and($letter->fresh()->template_snapshot['template_view'])->toBe('pdf.letters.siswa-aktif');
});

it('requires the student data or invitation content before creating a template letter', function () {
    $type = $this->service->save($this->admin, $this->data);
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $type->id)
        ->set('tujuan', 'Penerima')->set('formData.student_id', 99999)->call('save')
        ->assertHasErrors(['formData.student_id', 'formData.tahun_pelajaran', 'formData.keperluan']);
    $invitation = $this->service->save($this->admin, array_replace($this->data, ['name' => 'Undangan', 'template_view' => 'pdf.letters.undangan']));
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $invitation->id)
        ->set('tujuan', 'Penerima')->call('save')->assertHasErrors('isi');
    expect(OutgoingLetter::count())->toBe(0)->and(DB::table('letter_sequences')->count())->toBe(0);
});

it('freezes unsnapshotted old letters before updating a type and prevents deletion once used', function () {
    $type = $this->service->save($this->admin, $this->data);
    $letter = viewTemplateLetter($type, $this->admin->id);
    $this->put(route('admin.letter-types.update', $type), array_replace($this->data, ['template_view' => null, 'is_active' => false]))->assertRedirect();
    expect($letter->fresh()->template_snapshot['template_view'])->toBe('pdf.letters.siswa-aktif')
        ->and($type->fresh()->template_view)->toBeNull();
    $this->delete(route('admin.letter-types.destroy', $type))->assertSessionHasErrors('letter_type');
    expect($type->fresh())->not->toBeNull();
});

it('preserves old editor snapshots when a type is switched to a predefined view', function () {
    $type = LetterType::create(['name' => 'Jenis Lama', 'code' => 'A', 'is_active' => true,
        'body_html' => '<p>Keperluan: <span data-letter-field="keperluan">Keperluan</span></p>',
        'fields' => [['name' => 'keperluan', 'label' => 'Keperluan', 'type' => 'text', 'required' => true]]]);
    $letter = viewTemplateLetter($type, $this->admin->id);
    $this->put(route('admin.letter-types.update', $type), $this->data)->assertRedirect();
    expect($type->fresh()->body_html)->toBeNull()->and($letter->fresh()->template_snapshot['engine'])->toBe('editor')
        ->and($letter->fresh()->template_snapshot['values']['keperluan'])->toBe('Keperluan awal');
    $path = app(OutgoingLetterPdfService::class)->preview($letter->fresh());
    try {
        expect(file_get_contents($path))->toStartWith('%PDF-');
    } finally {
        @unlink($path);
    }
});

it('generates real PDFs from every registered view for both signature methods', function (string $view, string $method) {
    $type = $this->service->save($this->admin, array_replace($this->data, ['template_view' => $view]));
    $letter = viewTemplateLetter($type, $this->admin->id);
    $letter->data = ['student_id' => $this->student->id, 'tahun_pelajaran' => '2026/2027', 'keperluan' => 'Beasiswa Contoh',
        'nama' => 'Peserta Contoh', 'hari_tanggal' => 'Senin, 2 November 2026', 'waktu' => '08.00 - 10.00', 'tempat' => 'Aula', 'acara' => 'Lomba Contoh'];
    $letter->isi = 'Isi undangan contoh <script>tidak dieksekusi</script>';
    $letter->signature_method = $method;
    app(LetterTemplateService::class)->capture($letter, $type);
    $letter->save();
    $html = view($view, ['letter' => $letter, 'data' => $letter->data, 'school' => SchoolSetting::first(), 'qrPath' => null])->render();
    $expected = match ($view) {
        'pdf.letters.siswa-aktif' => 'Beasiswa Contoh',
        'pdf.letters.dispensasi' => 'Lomba Contoh',
        default => 'Isi undangan contoh &lt;script&gt;',
    };
    expect($html)->toContain($expected)->toContain('Kepala Contoh')->not->toContain('<script>');
    $path = app(OutgoingLetterPdfService::class)->preview($letter);
    $finalPath = null;
    try {
        expect(file_get_contents($path))->toStartWith('%PDF-');
        app(OutgoingLetterWorkflowService::class)->submit($letter);
        $this->actingAs($this->headmaster);
        $this->get(route('outgoing-letters.preview', $letter))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        app(OutgoingLetterWorkflowService::class)->approve($letter);
        $letter->refresh();
        $finalPath = storage_path('app/'.$letter->final_pdf_file);
        expect($letter->status)->toBe($method === 'wet' ? 'awaiting_signature' : 'issued')
            ->and(file_get_contents($finalPath))->toStartWith('%PDF-');
        $this->get(route('outgoing-letters.final-pdf', $letter))->assertOk();
    } finally {
        @unlink($path);
        if ($finalPath) {
            @unlink($finalPath);
        }
    }
})->with([
    ['pdf.letters.siswa-aktif', 'wet'], ['pdf.letters.siswa-aktif', 'qr'],
    ['pdf.letters.undangan', 'wet'], ['pdf.letters.undangan', 'qr'],
    ['pdf.letters.dispensasi', 'wet'], ['pdf.letters.dispensasi', 'qr'],
    ['pdf.letters.pemberitahuan', 'wet'], ['pdf.letters.pemberitahuan', 'qr'],
]);

it('seeds the standard types with the matching view schemas', function () {
    $this->seed(LetterTypeSeeder::class);
    $this->seed(LetterTypeSeeder::class);
    expect(LetterType::count())->toBe(6);
    foreach (['Surat Keterangan Siswa', 'Surat Undangan', 'Surat Dispensasi', 'Surat Pemberitahuan', 'Surat Tugas'] as $name) {
        $type = LetterType::where('name', $name)->firstOrFail();
        expect(app(LetterTemplateCatalog::class)->availableFor($type))->toBeTrue()
            ->and($type->fields)->toBe(app(LetterTemplateCatalog::class)->fieldsFor($type));
    }
    $type = LetterType::where('name', 'Surat Undangan')->firstOrFail();
    $this->service->save($this->admin, ['name' => $type->name, 'code' => 'B', 'is_active' => false, 'template_view' => null], $type);
    $this->seed(LetterTypeSeeder::class);
    expect($type->fresh()->template_view)->toBeNull()->and($type->fresh()->is_active)->toBeFalse()->and($type->fresh()->code)->toBe('B');
});

it('limits all letter type management to administrators', function (string $role) {
    $type = $this->service->save($this->admin, $this->data);
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->get(route('admin.letter-types.index'))->assertForbidden();
    $this->get(route('admin.letter-types.create'))->assertForbidden();
    $this->get(route('admin.letter-types.edit', $type))->assertForbidden();
    $this->post(route('admin.letter-types.store'), $this->data)->assertForbidden();
    $this->put(route('admin.letter-types.update', $type), $this->data)->assertForbidden();
    $this->delete(route('admin.letter-types.destroy', $type))->assertForbidden();
    expect(LetterType::count())->toBe(1);
})->with(['teacher', 'staff', 'headmaster']);

it('creates and edits rich notification letters without losing formatting or snapshot fields', function () {
    $type = $this->service->save($this->admin, array_replace($this->data, ['name' => 'Pemberitahuan', 'code' => 'E', 'template_view' => 'pdf.letters.pemberitahuan']));
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')
        ->set('letterTypeIdForm', (string) $type->id)->assertSee('Gunakan kerangka awal')
        ->set('tujuan', "Bapak/Ibu Wali Murid\nMadrasah Contoh")
        ->set('isiFormat', 'html')->set('isi', '<p><strong>Informasi</strong></p><ol><li><p>Poin</p></li></ol><script>unsafe()</script>')
        ->set('formData.lampiran', '1 lembar')->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    expect($letter->isi_format)->toBe('html')->and($letter->isi)->toContain('<strong>Informasi</strong>', '<ol>')
        ->not->toContain('<script>')->and($letter->template_snapshot['uses_body'])->toBeTrue()
        ->and($letter->template_snapshot['fields'][0]['name'])->toBe('lampiran');
    Livewire::test('admin.outgoing-letters.show', ['outgoingLetter' => $letter])->call('edit')
        ->assertSet('isiFormat', 'html')->set('isi', '<p style="text-align:right"><u>Revisi</u></p>')
        ->call('updateLetter')->assertHasNoErrors();
    expect($letter->fresh()->isi)->toContain('text-align:right', '<u>Revisi</u>');
    $this->actingAs($this->headmaster);
    $this->get(route('headmaster.outgoing-letters.show', $letter))->assertOk()->assertSee('<u>Revisi</u>', false)->assertDontSee('&lt;u&gt;', false);
});

it('rejects empty HTML or an invalid body format before issuing a number', function () {
    $type = $this->service->save($this->admin, array_replace($this->data, ['template_view' => 'pdf.letters.pemberitahuan']));
    $page = Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $type->id)
        ->set('tujuan', 'Wali Murid')->set('isiFormat', 'html')->set('isi', '<p>&nbsp;<br></p>');
    $page->call('save')->assertHasErrors('isi');
    $page->set('isi', '<p>Informasi</p>')->set('isiFormat', 'invalid')->call('save')->assertHasErrors('isiFormat');
    expect(OutgoingLetter::count())->toBe(0)->and(DB::table('letter_sequences')->count())->toBe(0);
});
