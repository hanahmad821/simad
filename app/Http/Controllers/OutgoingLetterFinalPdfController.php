<?php

namespace App\Http\Controllers;

use App\Models\OutgoingLetter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutgoingLetterFinalPdfController extends Controller
{
    public function __invoke(OutgoingLetter $outgoingLetter): BinaryFileResponse
    {
        abort_unless(in_array($outgoingLetter->status, ['issued', 'awaiting_signature'], true) && $outgoingLetter->final_pdf_file, 404, 'PDF final belum tersedia.');
        $path = storage_path('app/'.$outgoingLetter->final_pdf_file);
        abort_unless(is_file($path), 404, 'File PDF final tidak ditemukan.');

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
