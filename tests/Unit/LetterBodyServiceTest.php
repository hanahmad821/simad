<?php

use App\Models\OutgoingLetter;
use App\Services\LetterBodyService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('keeps approved formatting and list numbering while stripping executable HTML', function () {
    $body = '<p style="text-align:center;color:red" onclick="alert(1)"><strong>Informasi</strong></p>'
        .'<ol start="3"><li><p>Utama</p><ol><li><p><u>Rincian</u></p></li></ol></li></ol>'
        .'<script>alert(1)</script><img src="https://example.invalid/pixel"><iframe src="/secret"></iframe>';
    $clean = app(LetterBodyService::class)->clean($body, 'html');
    expect($clean)->toContain('text-align:center', '<strong>Informasi</strong>', '<ol start="3">', '<u>Rincian</u>')
        ->not->toContain('onclick', 'color:red', '<script', '<img', '<iframe', 'alert(1)', 'https://');
});

it('rejects visually empty rich text', function (string $body) {
    expect(fn () => app(LetterBodyService::class)->clean($body, 'html'))->toThrow(ValidationException::class);
})->with(['<p><br></p>', '<p>&nbsp; </p>', '<script>alert(1)</script>', '<ul><li><p></p></li></ul>', "<p>\u{200B}</p>"]);

it('keeps old plain text as literal text with line breaks', function () {
    $letter = new OutgoingLetter(['isi' => "Teks <strong>biasa</strong> & aman\nBaris kedua", 'isi_format' => 'text']);
    $rendered = app(LetterBodyService::class)->render($letter);
    expect($rendered)->toContain('&lt;strong&gt;biasa&lt;/strong&gt;', '&amp; aman', '<br />', 'Baris kedua')
        ->not->toContain('<strong>');
});

it('sanitizes rich text again before rendering stored content', function () {
    $letter = new OutgoingLetter(['isi' => '<p><em>Isi</em></p><img src=x onerror=alert(1)>', 'isi_format' => 'html']);
    expect(app(LetterBodyService::class)->render($letter))->toBe('<p><em>Isi</em></p>');
});
