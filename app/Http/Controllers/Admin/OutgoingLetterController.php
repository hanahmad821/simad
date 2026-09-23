<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutgoingLetter;
use App\Services\OutgoingLetterDocumentService;

class OutgoingLetterController extends Controller
{
    public function word(
        OutgoingLetter $outgoingLetter,
        OutgoingLetterDocumentService $service
    ) {
        $path = $service->generate($outgoingLetter);

        $filename = 'Surat-' . preg_replace(
            '/[^A-Za-z0-9._-]/',
            '-',
            $outgoingLetter->nomor_surat
        ) . '.docx';

        return response()->download(
            $path,
            $filename
        )->deleteFileAfterSend(true);
    }
}
