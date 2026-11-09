<?php

use App\Models\OutgoingLetter;
use App\Services\ManualOutgoingLetterPdfService;
use App\Services\PdfQrAnchorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

uses(TestCase::class);

function anchorXml(array $lines, float $width = 595.28, float $height = 841.89): string
{
    $body = '';
    foreach ($lines as $words) {
        $body .= '<line>';
        foreach ($words as [$text, $x, $y, $right, $bottom]) {
            $body .= '<word xMin="'.$x.'" yMin="'.$y.'" xMax="'.$right.'" yMax="'.$bottom.'">'.htmlspecialchars($text, ENT_XML1).'</word>';
        }
        $body .= '</line>';
    }

    return '<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml"><body><doc><page width="'.$width.'" height="'.$height.'">'.$body.'</page></doc></body></html>';
}

it('joins marker fragments and converts PDF points to millimetres', function () {
    $anchor = app(PdfQrAnchorService::class)->parse(anchorXml([
        [['[[QR_', 144, 216, 172, 228], ['CODE]]', 172, 216, 206, 228]],
    ]));

    expect($anchor['page'])->toBe(1)
        ->and($anchor['x'])->toBeBetween(49.79, 49.81)
        ->and($anchor['y'])->toBeBetween(75.19, 75.21)
        ->and($anchor['size'])->toBe(30.0);
});

it('rejects missing or duplicate markers and markers mixed with other text', function (array $lines) {
    app(PdfQrAnchorService::class)->parse(anchorXml($lines));
})->with([
    'scan or missing marker' => [[]],
    'duplicates' => [[
        [['[[QR_CODE]]', 144, 216, 206, 228]],
        [['[[QR_CODE]]', 144, 400, 206, 412]],
    ]],
    'inline marker' => [[
        [['Tanda tangan', 72, 216, 140, 228], ['[[QR_CODE]]', 144, 216, 206, 228]],
    ]],
])->throws(RuntimeException::class, 'tepat satu penanda');

it('rejects markers outside the page, oversized markers, and text collisions', function (array $lines) {
    app(PdfQrAnchorService::class)->parse(anchorXml($lines));
})->with([
    'right edge' => [[[['[[QR_CODE]]', 550, 216, 590, 228]]]],
    'bottom edge' => [[[['[[QR_CODE]]', 144, 800, 206, 812]]]],
    'oversized' => [[[['[[QR_CODE]]', 144, 216, 250, 228]]]],
    'overlap' => [[
        [['[[QR_CODE]]', 144, 216, 206, 228]],
        [['Nama Kepala', 144, 250, 206, 262]],
    ]],
])->throws(RuntimeException::class);

it('rejects malformed extraction output', function () {
    app(PdfQrAnchorService::class)->parse('not XML');
})->throws(RuntimeException::class, 'Koordinat teks PDF');

function manualPdf(string $path, bool $landscape = false): void
{
    $pdf = new FPDF;
    $pdf->AddPage($landscape ? 'L' : 'P');
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Surat manual halaman pertama');
    $pdf->Text(40, 70, '[[QR_CODE]]');
    $pdf->AddPage();
    $pdf->Text(20, 20, 'Lampiran halaman kedua');
    $pdf->Output('F', $path);
}

it('locates a real PDF marker on the first page in portrait and landscape', function (bool $landscape) {
    $path = tempnam(sys_get_temp_dir(), 'qr-anchor-');
    try {
        manualPdf($path, $landscape);
        $anchor = app(PdfQrAnchorService::class)->locate($path);
        expect($anchor['page'])->toBe(1)
            ->and($anchor['x'])->toBeBetween(38.9, 39.1)
            ->and($anchor['y'])->toBeBetween(64.0, 70.0);
    } finally {
        unlink($path);
    }
})->with([false, true]);

it('creates the final PDF without changing the source and cleans up temporary QR files', function () {
    Storage::fake('public');
    $relative = 'outgoing-letters/manual/test.pdf';
    Storage::disk('public')->makeDirectory('outgoing-letters/manual');
    $source = Storage::disk('public')->path($relative);
    manualPdf($source);
    $original = file_get_contents($source);
    $letter = Mockery::mock(OutgoingLetter::class)->makePartial();
    $letter->forceFill(['id' => 987654, 'use_template' => false, 'file' => $relative, 'verification_token' => 'test-verification-token']);
    $letter->shouldReceive('update')->once()->withArgs(function ($attributes) use ($letter) {
        $letter->final_pdf_file = $attributes['final_pdf_file'];

        return str_starts_with($attributes['final_pdf_file'], 'generated/letters/');
    })->andReturn(true);

    $output = null;
    try {
        $output = app(ManualOutgoingLetterPdfService::class)->generate($letter);
        $pdf = new Fpdi;
        expect($pdf->setSourceFile($output))->toBe(2)
            ->and(file_get_contents($source))->toBe($original)
            ->and(glob(storage_path('app/generated/qr/qr-987654-*.png')))->toBe([])
            ->and(storage_path('app/'.$letter->final_pdf_file))->toBe($output);
        $pdf->cleanUp();
    } finally {
        if ($output && is_file($output)) {
            unlink($output);
        }
    }
});

it('fails clearly when the PDF extractor is unavailable', function () {
    config(['documents.pdftotext_binary' => 'missing-poppler-executable']);
    $path = tempnam(sys_get_temp_dir(), 'qr-anchor-');
    try {
        manualPdf($path);
        app(PdfQrAnchorService::class)->locate($path);
    } finally {
        unlink($path);
    }
})->throws(RuntimeException::class, 'PDFTOTEXT_BINARY');

function workflowLetter(array $attributes): OutgoingLetter
{
    $letter = Mockery::mock(OutgoingLetter::class)->makePartial();
    $letter->forceFill(array_merge(['id' => 987655, 'use_template' => false, 'status' => 'draft'], $attributes));
    $letter->shouldReceive('refresh')->andReturnSelf();

    return $letter;
}

function persistedQrWorkflowLetter(array $attributes, string $role = 'admin'): OutgoingLetter
{
    if (\Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Workflow tests require an isolated database.');
    }
    \Illuminate\Support\Facades\Schema::create('outgoing_letters', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->boolean('use_template')->default(false);
        $table->string('status')->default('draft');
        $table->string('signature_method')->default('qr');
        foreach (['file', 'verification_token', 'final_pdf_file', 'rejection_reason'] as $field) {
            $table->string($field)->nullable();
        }
        $table->unsignedBigInteger('approved_by')->nullable();
        $table->timestamp('approved_at')->nullable();
        $table->timestamps();
    });
    test()->actingAs(\App\Models\User::factory()->make(['id' => 1, 'role' => $role]));

    return OutgoingLetter::create(array_merge(['status' => 'draft', 'use_template' => false], $attributes));
}

it('prevents submission when the manual PDF is missing or its anchor is invalid', function (bool $missing) {
    Storage::fake('public');
    $letter = persistedQrWorkflowLetter(['file' => $missing ? null : 'old.docx']);
    $component = app('livewire')->new('admin.outgoing-letters.show');
    $component->outgoingLetter = $letter;
    $component->submitForApproval();

    expect($letter->status)->toBe('draft')
        ->and($component->getErrorBag()->has('documentFile'))->toBeTrue();
})->with([true, false]);

it('submits a valid manual PDF without generating a final document before approval', function () {
    Storage::fake('public');
    manualPdf(Storage::disk('public')->path('submission.pdf'));
    $letter = persistedQrWorkflowLetter(['file' => 'submission.pdf']);
    $component = app('livewire')->new('admin.outgoing-letters.show');
    $component->outgoingLetter = $letter;
    $component->submitForApproval();

    expect($letter->status)->toBe('pending')->and($letter->final_pdf_file)->toBeNull();
});

it('keeps an invalid PDF pending when the headmaster tries to approve', function () {
    Storage::fake('public');
    $letter = persistedQrWorkflowLetter(['status' => 'pending', 'file' => 'missing.pdf', 'verification_token' => 'test-token'], 'headmaster');
    $component = app('livewire')->new('headmaster.outgoing-letters.show');
    $component->outgoingLetter = $letter;
    $component->approve();

    expect($letter->status)->toBe('pending')->and($letter->approved_at)->toBeNull();
});

it('generates and issues a manual PDF only when the headmaster approves', function () {
    Storage::fake('public');
    manualPdf(Storage::disk('public')->path('approval.pdf'));
    $letter = persistedQrWorkflowLetter(['status' => 'pending', 'file' => 'approval.pdf', 'verification_token' => null], 'headmaster');
    $component = app('livewire')->new('headmaster.outgoing-letters.show');
    $component->outgoingLetter = $letter;
    try {
        $component->approve();
        expect($letter->status)->toBe('issued')
            ->and($letter->approved_at)->not->toBeNull()
            ->and(strlen($letter->verification_token))->toBe(64)
            ->and(is_file(storage_path('app/'.$letter->final_pdf_file)))->toBeTrue();
    } finally {
        if ($letter->final_pdf_file && is_file(storage_path('app/'.$letter->final_pdf_file))) {
            unlink(storage_path('app/'.$letter->final_pdf_file));
        }
    }
});

it('accepts a PDF upload with one marker and keeps it as a draft', function () {
    Storage::fake('public');
    $path = tempnam(sys_get_temp_dir(), 'upload-pdf-');
    try {
        manualPdf($path);
        $upload = UploadedFile::fake()->createWithContent('surat.pdf', file_get_contents($path));
        $letter = workflowLetter(['file' => null]);
        $letter->shouldReceive('update')->once()->andReturnUsing(function ($attributes) use ($letter) {
            $letter->forceFill($attributes);

            return true;
        });
        $component = app('livewire')->new('admin.outgoing-letters.show');
        $component->outgoingLetter = $letter;
        $component->documentFile = $upload;
        $component->uploadDocument();

        expect($letter->status)->toBe('draft')
            ->and(Storage::disk('public')->exists($letter->file))->toBeTrue()
            ->and($letter->final_pdf_file)->toBeNull();
    } finally {
        unlink($path);
    }
});

it('rejects a PDF without a marker and preserves the previous uploaded document', function () {
    Storage::fake('public');
    Storage::disk('public')->put('previous.pdf', 'previous contents');
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Surat tanpa penanda');
    $letter = workflowLetter(['file' => 'previous.pdf']);
    $letter->shouldNotReceive('update');
    $component = app('livewire')->new('admin.outgoing-letters.show');
    $component->outgoingLetter = $letter;
    $component->documentFile = UploadedFile::fake()->createWithContent('surat.pdf', $pdf->Output('S'));
    try {
        $component->uploadDocument();
        test()->fail('PDF without a marker should be rejected.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('documentFile')
            ->and(Storage::disk('public')->get('previous.pdf'))->toBe('previous contents')
            ->and($letter->file)->toBe('previous.pdf');
    }
});

it('rejects Word documents in the PDF upload form', function () {
    $letter = workflowLetter(['file' => null]);
    $letter->shouldNotReceive('update');
    $component = app('livewire')->new('admin.outgoing-letters.show');
    $component->outgoingLetter = $letter;
    $component->documentFile = UploadedFile::fake()->create('surat.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $component->uploadDocument();
})->throws(ValidationException::class);
