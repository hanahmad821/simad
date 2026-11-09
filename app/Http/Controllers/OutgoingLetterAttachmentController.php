<?php

namespace App\Http\Controllers;

use App\Models\OutgoingLetter;
use App\Models\OutgoingLetterAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutgoingLetterAttachmentController extends Controller
{
    public function __invoke(OutgoingLetter $outgoingLetter, OutgoingLetterAttachment $attachment): BinaryFileResponse
    {
        abort_unless((int) $attachment->outgoing_letter_id === (int) $outgoingLetter->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404, 'Berkas lampiran tidak ditemukan.');

        return response()->download($disk->path($attachment->path), $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
