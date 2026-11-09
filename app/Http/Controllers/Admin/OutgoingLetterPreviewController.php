<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutgoingLetter;
use App\Services\OutgoingLetterPdfService;

class OutgoingLetterPreviewController extends Controller
{
    public function __invoke(
        OutgoingLetter $outgoingLetter,
        OutgoingLetterPdfService $service
    ) {
        abort_unless(
            in_array($outgoingLetter->status, ['draft', 'pending'], true),
            403
        );

        abort_unless(
            $outgoingLetter->use_template,
            404
        );

        $path = $service->preview($outgoingLetter);

        return response()->file($path, ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }
}
