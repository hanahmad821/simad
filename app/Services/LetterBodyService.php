<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use Illuminate\Validation\ValidationException;

class LetterBodyService
{
    public function clean(string $body, string $format, bool $required = true): string
    {
        $body = $format === 'html' ? app(LetterTemplateContentService::class)->sanitize($body) : $body;
        $text = $format === 'html' ? html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $body;
        $visible = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $text);
        if ($required && $visible === '') {
            throw ValidationException::withMessages(['isi' => 'Isi surat tidak boleh kosong. Tuliskan pemberitahuan atau undangan terlebih dahulu.']);
        }
        if (mb_strlen($text) > 20000) {
            throw ValidationException::withMessages(['isi' => 'Isi surat maksimal 20.000 karakter.']);
        }

        return $body;
    }

    public function render(OutgoingLetter $letter): string
    {
        return $letter->isi_format === 'html'
            ? app(LetterTemplateContentService::class)->sanitize($letter->isi ?? '')
            : nl2br(e($letter->isi ?? ''));
    }
}
