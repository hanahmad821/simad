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
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::fake('public');
});

function attachmentTestLetter(int $actor, array $overrides = []): OutgoingLetter
{
    static $number = 0;
    $number++;
    $type = LetterType::firstOrCreate(['name' => 'Surat Pemberitahuan'], [
        'code' => 'E', 'is_active' => true, 'template_view' => 'pdf.letters.pemberitahuan',
        'fields' => config('letter_templates')['pdf.letters.pemberitahuan']['fields'],
    ]);
    $letter = new OutgoingLetter(array_replace([
        'letter_type_id' => $type->id, 'nomor_urut' => $number, 'nomor_tahun' => 2026,
        'nomor_surat' => 'LAMPIRAN-'.$number.'/2026', 'tanggal_surat' => '2026-11-03',
        'tujuan' => 'Orang Tua/Wali Murid', 'perihal' => 'Pemberitahuan', 'use_template' => true,
        'signature_method' => 'wet', 'status' => 'draft', 'isi' => 'Informasi kegiatan.',
        'created_by' => $actor, 'data' => [],
    ], $overrides));
    app(LetterTemplateService::class)->capture($letter, $type);
    $letter->save();

    return $letter;
}

it('creates a notification and private attachments together and displays automatic counts', function () {
    $type = $this->service->save($this->admin, array_replace($this->data, ['name' => 'Surat Pemberitahuan', 'code' => 'E', 'template_view' => 'pdf.letters.pemberitahuan']));
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $type->id)
        ->set('tujuan', 'Wali Murid')->set('isi', 'Informasi kegiatan')
        ->set('attachmentUploads', [\Illuminate\Http\UploadedFile::fake()->create('jadwal.pdf', 20, 'application/pdf'), \Illuminate\Http\UploadedFile::fake()->image('denah.png')])
        ->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    expect($letter->attachments()->count())->toBe(2)->and($letter->attachmentDescription())->toBe('2 berkas');
    foreach ($letter->attachments as $file) {
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($file->path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($file->path);
        expect($file->uploaded_by)->toBe($this->admin->id);
    }
    $this->get(route('admin.outgoing-letters.show', $letter))->assertOk()->assertSee('jadwal.pdf')->assertSee('denah.png');
    $html = view('pdf.letters.pemberitahuan', ['letter' => $letter, 'school' => SchoolSetting::first(), 'data' => [], 'qrPath' => null])->render();
    expect($html)->toContain('2 berkas');
    $this->actingAs($this->headmaster);
    $this->get(route('headmaster.outgoing-letters.show', $letter))->assertOk()->assertSee('jadwal.pdf')->assertDontSee('Simpan Lampiran');
});

it('rejects unsuitable files before allocating a letter number', function (string $invalid) {
    $type = $this->service->save($this->admin, array_replace($this->data, ['template_view' => 'pdf.letters.pemberitahuan']));
    $files = match ($invalid) {
        'type' => [\Illuminate\Http\UploadedFile::fake()->create('program.exe', 1, 'application/x-msdownload')],
        'size' => [\Illuminate\Http\UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')],
        'count' => array_map(fn ($n) => \Illuminate\Http\UploadedFile::fake()->create('file-'.$n.'.pdf', 1, 'application/pdf'), range(1, 6)),
    };
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $type->id)
        ->set('tujuan', 'Wali Murid')->set('isi', 'Informasi')->set('attachmentUploads', $files)->call('save')
        ->assertHasErrors($invalid === 'count' ? 'attachmentUploads' : 'attachmentUploads.0');
    expect(OutgoingLetter::count())->toBe(0)->and(DB::table('letter_sequences')->count())->toBe(0);
})->with(['type', 'size', 'count']);

it('checks the real content type of a file instead of trusting a PDF filename', function () {
    $letter = attachmentTestLetter($this->admin->id);
    $path = tempnam(sys_get_temp_dir(), 'invalid-attachment-');
    file_put_contents($path, '<?php echo "not a PDF";');
    try {
        $file = new \Illuminate\Http\UploadedFile($path, 'jadwal.pdf', null, null, true);
        expect(fn () => app(\App\Services\OutgoingLetterAttachmentService::class)->add($this->admin, $letter, [$file]))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
        expect($letter->attachments()->count())->toBe(0);
    } finally { @unlink($path); }
});

it('accepts genuine Office documents', function (string $format) {
    $path = tempnam(sys_get_temp_dir(), 'office-attachment-');
    if ($format === 'docx') {
        $document = new \PhpOffice\PhpWord\PhpWord;
        $document->addSection()->addText('Lampiran contoh');
        \PhpOffice\PhpWord\IOFactory::createWriter($document, 'Word2007')->save($path);
    } else {
        $document = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $document->getActiveSheet()->setCellValue('A1', 'Lampiran contoh');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($document))->save($path);
    }
    try {
        $letter = attachmentTestLetter($this->admin->id);
        app(\App\Services\OutgoingLetterAttachmentService::class)->add($this->admin, $letter, [new \Illuminate\Http\UploadedFile($path, 'lampiran.'.$format, null, null, true)]);
        expect($letter->attachments()->firstOrFail()->original_name)->toBe('lampiran.'.$format);
    } finally { @unlink($path); }
})->with(['docx', 'xlsx']);

it('enforces the combined file limit and removes draft files without overwriting a custom description', function () {
    $letter = attachmentTestLetter($this->admin->id, ['data' => ['lampiran' => 'Jadwal kegiatan (1 lembar)']]);
    $service = app(\App\Services\OutgoingLetterAttachmentService::class);
    $makeFiles = fn ($count) => array_map(fn ($n) => \Illuminate\Http\UploadedFile::fake()->create('jadwal-'.$n.'.pdf', 1, 'application/pdf'), range(1, $count));
    $service->add($this->admin, $letter, $makeFiles(2));
    expect(fn () => $service->add($this->admin, $letter, $makeFiles(4)))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($letter->attachments()->count())->toBe(2)->and($letter->attachmentDescription())->toBe('Jadwal kegiatan (1 lembar)');
    $attachment = $letter->attachments()->firstOrFail();
    $service->remove($this->admin, $letter, $attachment->id);
    \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($attachment->path);
    expect($letter->attachments()->count())->toBe(1);
    $letter->update(['data' => []]);
    expect($letter->attachmentDescription())->toBe('1 berkas');
});

it('freezes attachments as soon as a letter leaves draft even with a stale page', function (string $status) {
    $letter = attachmentTestLetter($this->admin->id);
    $service = app(\App\Services\OutgoingLetterAttachmentService::class);
    $service->add($this->admin, $letter, [\Illuminate\Http\UploadedFile::fake()->create('jadwal.pdf', 1, 'application/pdf')]);
    $attachment = $letter->attachments()->firstOrFail();
    OutgoingLetter::whereKey($letter->id)->update(['status' => $status]);
    expect(fn () => $service->add($this->admin, $letter, [\Illuminate\Http\UploadedFile::fake()->image('baru.png')]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $service->remove($this->admin, $letter, $attachment->id))->toThrow(\Illuminate\Validation\ValidationException::class);
    \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment->path);
    expect($letter->attachments()->count())->toBe(1);
})->with(['pending', 'issued', 'awaiting_signature', 'cancelled']);

it('serves attachment downloads to letter operators and headmaster', function (string $role, int $expected) {
    $letter = attachmentTestLetter($this->admin->id);
    app(\App\Services\OutgoingLetterAttachmentService::class)->add($this->admin, $letter, [\Illuminate\Http\UploadedFile::fake()->create('jadwal.pdf', 1, 'application/pdf')]);
    $attachment = $letter->attachments()->firstOrFail();
    $this->actingAs(User::factory()->create(['role' => $role]));
    $response = $this->get(route('outgoing-letters.attachments.download', [$letter, $attachment]))->assertStatus($expected);
    if ($expected === 200) $response->assertDownload('jadwal.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
})->with([['admin', 200], ['headmaster', 200], ['teacher', 403], ['staff', 200]]);

it('does not allow a non-admin to add or remove attachments', function (string $role) {
    $letter = attachmentTestLetter($this->admin->id);
    $service = app(\App\Services\OutgoingLetterAttachmentService::class);
    $service->add($this->admin, $letter, [\Illuminate\Http\UploadedFile::fake()->image('denah.png')]);
    $actor = User::factory()->create(['role' => $role]);
    expect(fn () => $service->add($actor, $letter, [\Illuminate\Http\UploadedFile::fake()->image('baru.png')]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn () => $service->remove($actor, $letter, $letter->attachments()->firstOrFail()->id))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
})->with(['headmaster', 'teacher']);

it('checks the parent letter and handles missing files without exposing paths', function () {
    $letter = attachmentTestLetter($this->admin->id);
    $other = attachmentTestLetter($this->admin->id);
    app(\App\Services\OutgoingLetterAttachmentService::class)->add($this->admin, $letter, [\Illuminate\Http\UploadedFile::fake()->image('denah.png')]);
    $attachment = $letter->attachments()->firstOrFail();
    $this->get(route('outgoing-letters.attachments.download', [$other, $attachment]))->assertNotFound();
    \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
    $this->get(route('outgoing-letters.attachments.download', [$letter, $attachment]))->assertNotFound();
});

it('rolls back the letter and cleans saved files if a later upload fails', function () {
    $service = app(\App\Services\OutgoingLetterAttachmentService::class);
    $files = [\Illuminate\Http\UploadedFile::fake()->image('one.png'), \Illuminate\Http\UploadedFile::fake()->image('two.png')];
    $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
    $calls = 0;
    $disk->shouldReceive('putFile')->twice()->andReturnUsing(function () use (&$calls) {
        if (++$calls === 2) throw new RuntimeException('Simulated disk failure');
        return 'outgoing-letters/attachments/1/saved.png';
    });
    $disk->shouldReceive('delete')->once()->with(['outgoing-letters/attachments/1/saved.png'])->andReturn(true);
    \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    expect(fn () => $service->create($this->admin, $files, fn () => attachmentTestLetter($this->admin->id)))
        ->toThrow(RuntimeException::class, 'Simulated disk failure');
    expect(OutgoingLetter::count())->toBe(0)->and(\App\Models\OutgoingLetterAttachment::count())->toBe(0);
});

it('allows optional uploads to be cancelled or reset when switching letter types', function () {
    $type = $this->service->save($this->admin, array_replace($this->data, ['template_view' => 'pdf.letters.pemberitahuan']));
    $page = Livewire::test('admin.outgoing-letters.index')->call('openCreate')->set('letterTypeIdForm', (string) $type->id)
        ->set('attachmentUploads', [\Illuminate\Http\UploadedFile::fake()->image('denah.png')]);
    $page->call('removeAttachmentUpload', 0)->assertSet('attachmentUploads', []);
    $page->set('tujuan', 'Wali Murid')->set('isi', 'Informasi')->set('attachmentUploads', [\Illuminate\Http\UploadedFile::fake()->create('program.exe', 1, 'application/x-msdownload')])
        ->call('save')->assertHasErrors('attachmentUploads.0')->call('removeAttachmentUpload', 0)->assertHasNoErrors('attachmentUploads.0');
    $page->set('attachmentUploads', [\Illuminate\Http\UploadedFile::fake()->image('denah.png')])->set('useTemplate', false)->assertSet('attachmentUploads', []);
});

it('allows staff to create and submit outgoing letters with attachments', function () {
    $type = $this->service->save($this->admin, array_replace($this->data, [
        'name' => 'Surat Pemberitahuan', 'code' => 'E', 'template_view' => 'pdf.letters.pemberitahuan',
    ]));
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff);
    $this->get(route('staff.outgoing-letters.index'))->assertOk()->assertDontSee('Kelola jenis surat');
    Livewire::test('admin.outgoing-letters.index')->call('openCreate')
        ->set('letterTypeIdForm', (string) $type->id)->set('tujuan', 'Wali Murid')
        ->set('isi', 'Informasi kegiatan')->set('signatureMethod', 'wet')
        ->set('attachmentUploads', [\Illuminate\Http\UploadedFile::fake()->create('jadwal.pdf', 1, 'application/pdf')])
        ->call('save')->assertHasNoErrors();
    $letter = OutgoingLetter::firstOrFail();
    expect($letter->created_by)->toBe($staff->id)->and($letter->status)->toBe('draft');
    $this->get(route('staff.outgoing-letters.show', $letter))->assertOk()
        ->assertSee(route('staff.outgoing-letters.preview', $letter), false);
    Livewire::test('admin.outgoing-letters.show', ['outgoingLetter' => $letter])
        ->call('submitForApproval')->assertHasNoErrors();
    expect($letter->fresh()->status)->toBe('pending');
    expect(fn () => app(OutgoingLetterWorkflowService::class)->approve($letter))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->get(route('admin.gtks.index'))->assertForbidden();
    $this->get(route('admin.letter-types.index'))->assertForbidden();
    $this->get(route('headmaster.outgoing-letters.show', $letter))->assertForbidden();
});

it('blocks teachers from staff letter pages and shared operator components', function () {
    $letter = attachmentTestLetter($this->admin->id);
    $this->actingAs(User::factory()->create(['role' => 'teacher']));
    $this->get(route('staff.outgoing-letters.index'))->assertForbidden();
    $this->get(route('staff.outgoing-letters.show', $letter))->assertForbidden();
    Livewire::test('admin.outgoing-letters.index')->assertForbidden();
});
