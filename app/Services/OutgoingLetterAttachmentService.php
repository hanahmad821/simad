<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class OutgoingLetterAttachmentService
{
    public const MAX_FILES = 5;
    public const MAX_KB = 5120;

    public function supports(OutgoingLetter $letter): bool
    {
        if (!$letter->use_template) return false;
        $definition = app(LetterTemplateService::class)->definition($letter);

        return ($definition['engine'] ?? null) !== 'editor'
            && ($definition['template_view'] ?? null) === 'pdf.letters.pemberitahuan';
    }

    public function validateFiles(array $files): void
    {
        Validator::make(['attachmentUploads' => $files], [
            'attachmentUploads' => ['array', 'max:'.self::MAX_FILES],
            'attachmentUploads.*' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png',
                'extensions:pdf,docx,xlsx,jpg,jpeg,png', 'max:'.self::MAX_KB],
        ], [
            'attachmentUploads.max' => 'Maksimal 5 berkas lampiran per surat.',
            'attachmentUploads.*.max' => 'Ukuran setiap lampiran maksimal 5 MB.',
            'attachmentUploads.*.mimes' => 'Lampiran harus berupa PDF, DOCX, XLSX, JPG, JPEG, atau PNG.',
            'attachmentUploads.*.extensions' => 'Ekstensi berkas harus PDF, DOCX, XLSX, JPG, JPEG, atau PNG.',
        ])->validate();
    }

    /** Save the letter, its number, and all attachment records in one transaction. */
    public function create(User $actor, array $files, Closure $createLetter): OutgoingLetter
    {
        return $this->persist($actor, $files, $createLetter);
    }

    public function add(User $actor, OutgoingLetter $letter, array $files): OutgoingLetter
    {
        abort_unless($actor->canManageLetters(), 403);
        if ($files === []) {
            throw ValidationException::withMessages(['attachmentUploads' => 'Pilih berkas lampiran terlebih dahulu.']);
        }

        return $this->persist($actor, $files, fn () => OutgoingLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail());
    }

    private function persist(User $actor, array $files, Closure $getLetter): OutgoingLetter
    {
        abort_unless($actor->canManageLetters(), 403);
        $this->validateFiles($files);
        $stored = [];
        try {
            return DB::transaction(function () use ($actor, $files, $getLetter, &$stored) {
                $letter = $getLetter();
                if ($files === []) return $letter;
                $this->assertEditable($letter);
                if ($letter->attachments()->count() + count($files) > self::MAX_FILES) {
                    throw ValidationException::withMessages(['attachmentUploads' => 'Jumlah seluruh lampiran maksimal 5 berkas. Hapus berkas lama bila ingin menggantinya.']);
                }
                foreach ($files as $file) {
                    $path = Storage::disk('local')->putFile('outgoing-letters/attachments/'.$letter->id, $file);
                    if (!$path) {
                        throw ValidationException::withMessages(['attachmentUploads' => 'Lampiran gagal disimpan. Silakan coba lagi.']);
                    }
                    $stored[] = $path;
                    $letter->attachments()->create([
                        'original_name' => $this->filename($file), 'path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(), 'uploaded_by' => $actor->id,
                    ]);
                }

                return $letter->unsetRelation('attachments');
            });
        } catch (Throwable $exception) {
            if ($stored !== []) {
                try { Storage::disk('local')->delete($stored); } catch (Throwable $cleanup) { report($cleanup); }
            }
            throw $exception;
        }
    }

    public function remove(User $actor, OutgoingLetter $letter, int $id): void
    {
        abort_unless($actor->canManageLetters(), 403);
        $path = DB::transaction(function () use ($letter, $id) {
            $current = OutgoingLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($current);
            $attachment = $current->attachments()->whereKey($id)->firstOrFail();
            $attachment->delete();

            return $attachment->path;
        });
        if (!Storage::disk('local')->delete($path)) report(new \RuntimeException('Pembersihan lampiran gagal: '.$path));
    }

    private function assertEditable(OutgoingLetter $letter): void
    {
        if ($letter->status !== 'draft') {
            throw ValidationException::withMessages(['attachmentUploads' => 'Lampiran hanya dapat diubah saat surat masih draft.']);
        }
        if (!$this->supports($letter)) {
            throw ValidationException::withMessages(['attachmentUploads' => 'Unggahan lampiran tersedia untuk template Surat Pemberitahuan.']);
        }
    }

    private function filename(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'lampiran', 0, 200);
    }
}
