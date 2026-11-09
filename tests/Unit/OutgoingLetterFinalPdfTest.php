<?php

use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('links issued letters to the final PDF instead of the original upload', function () {
    $letter = new OutgoingLetter;
    $letter->forceFill(['id' => 15, 'status' => 'issued', 'file' => 'manual/original.pdf', 'final_pdf_file' => 'generated/letters/final.pdf']);
    $html = Blade::render('<x-outgoing-letter-document :letter="$letter" />', ['letter' => $letter]);

    expect($html)->toContain(route('outgoing-letters.final-pdf', $letter))
        ->toContain('Buka PDF Final')
        ->not->toContain('manual/original.pdf')
        ->not->toContain('[[QR_CODE]]');
});

it('labels draft and pending documents as source PDFs', function (string $status) {
    $letter = new OutgoingLetter;
    $letter->forceFill(['id' => 15, 'status' => $status, 'file' => 'manual/original.pdf']);
    $html = Blade::render('<x-outgoing-letter-document :letter="$letter" />', ['letter' => $letter]);

    expect($html)->toContain(Storage::disk('public')->url($letter->file))
        ->toContain('Buka PDF Sumber')
        ->not->toContain('Buka PDF Final');
})->with(['draft', 'pending']);

it('does not offer the original upload as a final document when the final PDF is missing', function () {
    $letter = new OutgoingLetter;
    $letter->forceFill(['id' => 15, 'status' => 'issued', 'file' => 'manual/original.pdf']);
    $html = Blade::render('<x-outgoing-letter-document :letter="$letter" />', ['letter' => $letter]);

    expect($html)->toContain('PDF final belum tersedia')
        ->not->toContain('manual/original.pdf');
});

it('serves the final PDF to admins and headmasters', function (string $role) {
    $relative = 'generated/letters/final-pdf-route-test.pdf';
    $path = storage_path('app/'.$relative);
    file_put_contents($path, "%PDF-1.4\n%%EOF");
    $letter = new OutgoingLetter;
    $letter->forceFill(['id' => 15, 'status' => 'issued', 'final_pdf_file' => $relative]);
    Route::bind('outgoingLetter', fn () => $letter);
    $this->actingAs(User::factory()->make(['id' => 1, 'role' => $role]));
    try {
        $this->get(route('outgoing-letters.final-pdf', $letter))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    } finally {
        unlink($path);
    }
})->with(['admin', 'headmaster']);

it('blocks users outside admin and headmaster roles', function () {
    $letter = new OutgoingLetter;
    $letter->forceFill(['id' => 15, 'status' => 'issued', 'final_pdf_file' => 'generated/letters/final.pdf']);
    Route::bind('outgoingLetter', fn () => $letter);
    $this->actingAs(User::factory()->make(['id' => 1, 'role' => 'teacher']));

    $this->get(route('outgoing-letters.final-pdf', $letter))->assertForbidden();
});

it('does not serve a final PDF before approval or when its file is missing', function (string $status) {
    $letter = new OutgoingLetter;
    $letter->forceFill(['id' => 15, 'status' => $status, 'final_pdf_file' => 'generated/letters/missing-final.pdf']);
    Route::bind('outgoingLetter', fn () => $letter);
    $this->actingAs(User::factory()->make(['id' => 1, 'role' => 'admin']));

    $this->get(route('outgoing-letters.final-pdf', $letter))->assertNotFound();
})->with(['pending', 'issued']);
