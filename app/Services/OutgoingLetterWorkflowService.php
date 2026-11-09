<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutgoingLetterWorkflowService
{
    public function submit(OutgoingLetter $letter): void
    {
        abort_unless(Auth::user()?->canManageLetters(), 403);
        DB::transaction(function () use ($letter) {
            $current = OutgoingLetter::query()->lockForUpdate()->findOrFail($letter->id);
            if ($current->status !== 'draft') {
                throw ValidationException::withMessages(['documentFile' => 'Hanya draft yang dapat diajukan.']);
            }
            $this->validateSource($current);
            $current->update(['status' => 'pending', 'rejection_reason' => null]);
        });
    }

    private function validateSource(OutgoingLetter $letter): void
    {
        if ($letter->use_template) {
            return;
        }
        if (! $letter->file || ! Storage::disk('public')->exists($letter->file)) {
            throw ValidationException::withMessages(['documentFile' => 'Unggah PDF surat terlebih dahulu sebelum diajukan.']);
        }
        if (! $letter->usesWetSignature()) {
            try {
                app(PdfQrAnchorService::class)->locate(Storage::disk('public')->path($letter->file));
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['documentFile' => $e->getMessage()]);
            }
        }
    }

    public function approve(OutgoingLetter $letter): void
    {
        abort_unless(Auth::user()?->isHeadmaster(), 403);
        $generated = null;
        try {
            DB::transaction(function () use ($letter, &$generated) {
                $current = OutgoingLetter::query()->lockForUpdate()->findOrFail($letter->id);
                if ($current->status !== 'pending') {
                    throw ValidationException::withMessages(['status' => 'Surat ini tidak sedang menunggu persetujuan.']);
                }
                $this->validateSource($current);
                $current->update(['verification_token' => $current->usesWetSignature() ? null : ($current->verification_token ?: Str::random(64))]);
                $generated = $current->use_template
                    ? app(OutgoingLetterPdfService::class)->generate($current)
                    : app(ManualOutgoingLetterPdfService::class)->generate($current);
                $current->update([
                    'status' => $current->usesWetSignature() ? 'awaiting_signature' : 'issued',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ]);
            });
        } catch (\Throwable $e) {
            if ($generated && is_file($generated)) {
                unlink($generated);
            }
            throw $e;
        }
    }

    public function archiveSigned(OutgoingLetter $letter, UploadedFile $file): void
    {
        abort_unless(Auth::user()?->canManageLetters(), 403);
        Validator::make(['signedDocument' => $file], ['signedDocument' => ['required', 'file', 'mimes:pdf', 'max:10240']])->validate();
        $relative = null;
        try {
            DB::transaction(function () use ($letter, $file, &$relative) {
                $current = OutgoingLetter::query()->lockForUpdate()->findOrFail($letter->id);
                if (! $current->usesWetSignature() || $current->status !== 'awaiting_signature' || ! $current->approved_at) {
                    throw ValidationException::withMessages(['signedDocument' => 'Scan hanya dapat diunggah setelah surat disetujui untuk tanda tangan basah.']);
                }
                // Keep signed archives on the private local disk, served through the role-protected PDF route.
                $relative = 'generated/letters/signed-'.$current->id.'-'.Str::uuid().'.pdf';
                $target = storage_path('app/'.$relative);
                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0755, true);
                }
                if (! copy($file->getRealPath(), $target)) {
                    throw new \RuntimeException('Arsip gagal disimpan.');
                }
                $current->update([
                    'signed_file' => $relative,
                    'signed_uploaded_at' => now(),
                    'signed_uploaded_by' => Auth::id(),
                    'final_pdf_file' => $relative,
                    'status' => 'issued',
                ]);
            });
        } catch (\Throwable $e) {
            if ($relative && is_file(storage_path('app/'.$relative))) {
                unlink(storage_path('app/'.$relative));
            }
            throw $e;
        }
    }
}
