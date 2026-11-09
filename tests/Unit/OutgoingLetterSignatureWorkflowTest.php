<?php

use App\Models\OutgoingLetter;
use App\Models\User;
use App\Services\ManualOutgoingLetterPdfService;
use App\Services\OutgoingLetterPdfService;
use App\Services\OutgoingLetterWorkflowService;
use App\Services\PdfQrAnchorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Signature tests require an isolated in-memory database.');
    }
    Schema::create('outgoing_letters', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('draft');
        $table->boolean('use_template')->default(false);
        $table->string('signature_method')->default('qr');
        $table->unsignedBigInteger('letter_type_id')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->string('nomor_surat')->default('TEST-001');
        $table->string('jenis_surat')->default('Surat Manual');
        $table->string('tujuan')->default('Dinas Pendidikan');
        $table->string('perihal')->default('Surat pengantar');
        $table->date('tanggal_surat')->default('2026-10-27');
        $table->text('data')->nullable();
        $table->text('isi')->nullable();
        $table->string('isi_format')->default('text');
        $table->text('keterangan')->nullable();
        foreach (['file', 'final_file', 'final_docx_file', 'final_pdf_file', 'signed_file', 'verification_token', 'rejection_reason'] as $field) {
            $table->string($field)->nullable();
        }
        $table->unsignedBigInteger('approved_by')->nullable();
        $table->unsignedBigInteger('signed_uploaded_by')->nullable();
        $table->timestamp('approved_at')->nullable();
        $table->timestamp('signed_uploaded_at')->nullable();
        $table->timestamps();
    });
    Storage::fake('public');
    Schema::create('letter_types', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('code');
        $table->boolean('is_active')->default(true);
        $table->text('fields')->nullable();
        $table->timestamps();
    });
    $this->actingAs(User::factory()->make(['id' => 1, 'role' => 'admin']));
});

it('requires an uploaded source before submitting either manual signature method', function (string $method) {
    $letter = OutgoingLetter::create(['signature_method' => $method]);
    expect(fn () => app(OutgoingLetterWorkflowService::class)->submit($letter))->toThrow(ValidationException::class);
    expect($letter->fresh()->status)->toBe('draft');
})->with(['qr', 'wet']);

it('submits a wet signature source without looking for a QR marker', function () {
    Storage::disk('public')->put('manual/source.pdf', '%PDF-1.4');
    $letter = OutgoingLetter::create(['signature_method' => 'wet', 'file' => 'manual/source.pdf']);
    $this->mock(PdfQrAnchorService::class)->shouldNotReceive('locate');
    app(OutgoingLetterWorkflowService::class)->submit($letter);
    expect($letter->fresh()->status)->toBe('pending');
});

it('continues to require the QR marker for QR manual letters', function () {
    Storage::disk('public')->put('manual/source.pdf', '%PDF-1.4');
    $letter = OutgoingLetter::create(['signature_method' => 'qr', 'file' => 'manual/source.pdf']);
    $this->mock(PdfQrAnchorService::class)->shouldReceive('locate')->once()->andThrow(new RuntimeException('Penanda QR wajib.'));
    expect(fn () => app(OutgoingLetterWorkflowService::class)->submit($letter))->toThrow(ValidationException::class);
    expect($letter->fresh()->status)->toBe('draft');
});

it('approves manual wet letters without QR and completes them only after signed upload', function () {
    $source = "%PDF-1.4\noriginal unchanged\n%%EOF";
    Storage::disk('public')->put('manual/source.pdf', $source);
    $letter = OutgoingLetter::create(['signature_method' => 'wet', 'file' => 'manual/source.pdf', 'status' => 'pending']);
    $this->mock(PdfQrAnchorService::class)->shouldNotReceive('locate');
    $this->actingAs(User::factory()->make(['id' => 2, 'role' => 'headmaster']));
    app(OutgoingLetterWorkflowService::class)->approve($letter);
    $letter->refresh();
    $printPath = storage_path('app/'.$letter->final_pdf_file);
    try {
        expect($letter->status)->toBe('awaiting_signature')
            ->and($letter->verification_token)->toBeNull()
            ->and($letter->approved_by)->toBe(2)
            ->and(file_get_contents($printPath))->toBe($source);
        $this->actingAs(User::factory()->make(['id' => 1, 'role' => 'admin']));
        app(OutgoingLetterWorkflowService::class)->archiveSigned($letter, UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\nsigned scan\n%%EOF"));
        $letter->refresh();
        expect($letter->status)->toBe('issued')
            ->and($letter->signed_file)->toBe($letter->final_pdf_file)
            ->and($letter->signed_uploaded_at)->not->toBeNull()
            ->and($letter->signed_uploaded_by)->toBe(1)
            ->and(Storage::disk('public')->get('manual/source.pdf'))->toBe($source);
        $this->actingAs(User::factory()->make(['id' => 2, 'role' => 'headmaster']));
        $this->get(route('outgoing-letters.final-pdf', $letter))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    } finally {
        @unlink($printPath);
        if ($letter->signed_file) {
            @unlink(storage_path('app/'.$letter->signed_file));
        }
    }
});

it('uses the template renderer for both signature methods and preserves the approval status distinction', function (string $method, string $status) {
    $letter = OutgoingLetter::create(['signature_method' => $method, 'status' => 'pending', 'use_template' => true]);
    $this->mock(OutgoingLetterPdfService::class)->shouldReceive('generate')->once()->withArgs(fn ($current) => $current->signature_method === $method && ($method === 'wet' ? $current->verification_token === null : strlen($current->verification_token) === 64))->andReturn('unused-test-path');
    $this->actingAs(User::factory()->make(['id' => 2, 'role' => 'headmaster']));
    app(OutgoingLetterWorkflowService::class)->approve($letter);
    expect($letter->fresh()->status)->toBe($status);
})->with([['wet', 'awaiting_signature'], ['qr', 'issued']]);

it('rolls back approval when PDF generation fails', function () {
    $letter = OutgoingLetter::create(['status' => 'pending', 'use_template' => true]);
    $this->mock(OutgoingLetterPdfService::class)->shouldReceive('generate')->andThrow(new RuntimeException('Render failed'));
    $this->actingAs(User::factory()->make(['id' => 2, 'role' => 'headmaster']));
    expect(fn () => app(OutgoingLetterWorkflowService::class)->approve($letter))->toThrow(RuntimeException::class);
    expect($letter->fresh()->status)->toBe('pending')->and($letter->fresh()->verification_token)->toBeNull();
});

it('rejects signed uploads before approval and for QR letters', function (string $method, string $status) {
    $letter = OutgoingLetter::create(['signature_method' => $method, 'status' => $status]);
    expect(fn () => app(OutgoingLetterWorkflowService::class)->archiveSigned($letter, UploadedFile::fake()->createWithContent('signed.pdf', '%PDF-1.4')))->toThrow(ValidationException::class);
    expect($letter->fresh()->signed_file)->toBeNull();
})->with([['wet', 'draft'], ['wet', 'pending'], ['qr', 'issued']]);

it('does not accept a second signed upload from a stale tab', function () {
    $letter = OutgoingLetter::create(['signature_method' => 'wet', 'status' => 'awaiting_signature', 'approved_at' => now()]);
    $stale = $letter->fresh();
    try {
        app(OutgoingLetterWorkflowService::class)->archiveSigned($letter, UploadedFile::fake()->createWithContent('signed.pdf', '%PDF-1.4'));
        expect(fn () => app(OutgoingLetterWorkflowService::class)->archiveSigned($stale, UploadedFile::fake()->createWithContent('signed2.pdf', '%PDF-1.4')))->toThrow(ValidationException::class);
    } finally {
        @unlink(storage_path('app/'.$letter->fresh()->signed_file));
    }
});

it('shows print instructions without claiming QR verification for wet signatures', function () {
    $letter = new OutgoingLetter(['signature_method' => 'wet', 'status' => 'awaiting_signature', 'final_pdf_file' => 'print.pdf']);
    $letter->id = 1;
    $html = Blade::render('<x-outgoing-letter-document :letter="$letter" />', compact('letter'));
    expect($html)->toContain('Buka PDF untuk dicetak')->toContain('tanda tangan basah')->not->toContain('dilengkapi QR verifikasi');
});

it('clears the QR placeholder from Word templates while preserving the signature name', function () {
    Schema::create('school_settings', function (Blueprint $table) {
        $table->id();
    });
    $directory = storage_path('app/templates');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $name = 'signature-test-'.\Illuminate\Support\Str::uuid().'.docx';
    $word = new \PhpOffice\PhpWord\PhpWord;
    $section = $word->addSection();
    $section->addText('${qr_code}');
    $section->addText('Nama Kepala Sekolah');
    \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($directory.'/'.$name);
    $letter = OutgoingLetter::create(['signature_method' => 'wet', 'status' => 'awaiting_signature', 'use_template' => true]);
    $letter->setRelation('letterType', new \App\Models\LetterType(['template_file' => $name, 'fields' => []]));
    $output = null;
    try {
        $output = app(\App\Services\OutgoingLetterDocumentService::class)->generate($letter);
        $zip = new ZipArchive;
        $zip->open($output);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        expect($xml)->not->toContain('qr_code')->toContain('Nama Kepala Sekolah');
    } finally {
        @unlink($directory.'/'.$name);
        if ($output) {
            @unlink($output);
        }
    }
});

it('offers signature methods for both template and manual creation forms', function () {
    \Livewire\Livewire::test('admin.outgoing-letters.index')
        ->call('openCreate')->assertSee('Metode tanda tangan')->assertSet('signatureMethod', 'qr')
        ->set('signatureMethod', 'wet')->set('useTemplate', false)->assertSee('Tanda tangan basah')
        ->set('useTemplate', true)->assertSee('Tanda tangan basah');
});

it('edits the signature method only while the letter is a draft', function () {
    $letter = OutgoingLetter::create(['status' => 'draft', 'signature_method' => 'qr', 'use_template' => false])->fresh();
    $page = \Livewire\Livewire::test('admin.outgoing-letters.show', ['outgoingLetter' => $letter]);
    $page->call('edit')->set('signatureMethod', 'wet')->call('updateLetter')->assertHasNoErrors();
    expect($letter->fresh()->signature_method)->toBe('wet');
    // Simulate approval in a different browser tab while this detail page remains open.
    $letter->update(['status' => 'pending']);
    $page->set('signatureMethod', 'qr')->call('updateLetter');
    expect($letter->fresh()->signature_method)->toBe('wet');
});

it('renders a printable template PDF without requiring a verification token', function () {
    Schema::create('school_settings', function (Blueprint $table) {
        $table->id();
        $table->string('school_name');
    });
    DB::table('school_settings')->insert(['school_name' => 'Madrasah Test']);
    $letter = OutgoingLetter::create(['signature_method' => 'wet', 'status' => 'pending', 'use_template' => true])->fresh();
    $letter->setRelation('letterType', new \App\Models\LetterType(['template_view' => 'pdf.letters.undangan']));
    $output = null;
    try {
        $output = app(OutgoingLetterPdfService::class)->generate($letter);
        expect(file_get_contents($output))->toStartWith('%PDF-')->and($letter->fresh()->verification_token)->toBeNull();
    } finally {
        if ($output) {
            @unlink($output);
        }
    }
});
