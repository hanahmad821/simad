<?php

use App\Models\OutgoingLetter;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Services\OutgoingLetterDocumentService;
use App\Services\OutgoingLetterPdfService;
use App\Services\PdfQrAnchorService;
use Illuminate\Validation\ValidationException;
use App\Models\LetterType;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new
    #[Title('Detail Surat Keluar')]
    class extends Component {
    use WithFileUploads;
    use \App\Livewire\Concerns\HasAssignmentRecipients;
    public OutgoingLetter $outgoingLetter;

    public bool $editing = false;

    public string $tujuan = '';
    public string $tanggalSurat = '';
    public string $perihal = '';
    public string $isi = '';
    public string $isiFormat = 'text';
    public bool $useTemplate = true;
    public string $signatureMethod = 'qr';
    public $signedDocument;
    public array $attachmentUploads = [];
    public string $keterangan = '';
    public $documentFile;
    public array $formData = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->canManageLetters(), 403);
    }

    public function mount(OutgoingLetter $outgoingLetter): void
    {
        $this->outgoingLetter = $outgoingLetter->load([
            'letterType',
            'creator',
        ]);
    }

    public function back(): void
    {
        $this->redirectRoute((auth()->user()->isStaff() ? 'staff.' : 'admin.').'outgoing-letters.index');
    }

    public function generateWord()
    {
        try {
            $path = app(OutgoingLetterDocumentService::class)
                ->generate($this->outgoingLetter);

            return response()->download(
                $path,
                'Surat-' . $this->outgoingLetter->nomor_surat . '.docx'
            )->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: $e->getMessage()
            );
        }
    }
    public function submitForApproval(): void
    {
        try {
            app(\App\Services\OutgoingLetterWorkflowService::class)->submit($this->outgoingLetter);
        } catch (ValidationException $e) {
            $this->addError('documentFile', $e->validator->errors()->first());
            return;
        }

        $this->outgoingLetter->refresh();

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Surat berhasil diajukan untuk persetujuan Kepala Madrasah.'
        );
    }

    public function edit(): void
    {
        $this->outgoingLetter->refresh();
        if ($this->outgoingLetter->status !== 'draft') {
            return;
        }

        $this->tujuan = $this->outgoingLetter->tujuan;
        $this->tanggalSurat = $this->outgoingLetter->tanggal_surat?->format('Y-m-d') ?? '';
        $this->perihal = $this->outgoingLetter->perihal;
        $this->isi = $this->outgoingLetter->isi ?? '';
        $this->isiFormat = $this->outgoingLetter->isi_format ?? 'text';
        $this->useTemplate = $this->outgoingLetter->use_template;
        $this->signatureMethod = $this->outgoingLetter->signature_method ?? 'qr';
        $this->keterangan = $this->outgoingLetter->keterangan ?? '';
        $this->formData = $this->outgoingLetter->data ?? [];

        $this->editing = true;
    }

    #[Computed]
    public function selectedLetterType(): ?LetterType
    {
        return $this->outgoingLetter->letterType;
    }

    #[Computed]
    public function formFields(): array
    {
        return $this->outgoingLetter->template_snapshot['fields'] ?? $this->selectedLetterType?->fields ?? [];
    }

    #[Computed]
    public function usesBody(): bool
    {
        $definition = app(\App\Services\LetterTemplateService::class)->definition($this->outgoingLetter);
        if (($definition['engine'] ?? null) === 'editor') return false;
        return $definition['uses_body'] ?? app(\App\Services\LetterTemplateCatalog::class)->usesBody($definition['template_view'] ?? null);
    }

    public function updateLetter(): void
    {
        $this->outgoingLetter->refresh();
        if ($this->outgoingLetter->status !== 'draft') {
            return;
        }

        $this->prepareAssignmentTarget();

        $rules = [
            'signatureMethod' => ['required', 'in:qr,wet'],
            'tujuan' => [
                'required',
                'string',
                'max:255',
            ],

            'tanggalSurat' => [
                'required',
                'date',
            ],

            'perihal' => [
                'required',
                'string',
                'max:255',
            ],

            'keterangan' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];

        $rules['isi'] = [$this->outgoingLetter->use_template && $this->usesBody ? 'required' : 'nullable', 'string', 'max:100000'];

        foreach ($this->outgoingLetter->use_template ? $this->formFields : [] as $field) {

            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $required = $field['required'] ?? false;

            if ($type === 'gtk_assignments') continue;

            if ($type === 'repeater') {

                $rules["formData.{$name}"] = $required
                    ? ['required', 'array', 'min:1']
                    : ['nullable', 'array'];

                $rules["formData.{$name}.*"] = [
                    'required',
                    'string',
                    'max:1000',
                ];

                continue;
            }

            $fieldRules = $required
                ? ['required']
                : ['nullable'];

            if ($type === 'student') {
                $fieldRules[] = 'exists:students,id';
            } else {
                $fieldRules[] = 'string';
            }

            $fieldRules[] = 'max:5000';

            $rules["formData.{$name}"] = $fieldRules;
        }

        $this->validate($rules);
        $this->validate(['isiFormat' => ['required', 'in:text,html']]);
        if ($this->outgoingLetter->use_template && $this->usesBody) {
            $this->isi = app(\App\Services\LetterBodyService::class)->clean($this->isi, $this->isiFormat);
        }

        if ($this->outgoingLetter->use_template) {
            $this->validate(app(\App\Services\LetterTemplateService::class)->letterRules($this->formFields));
        }

        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->outgoingLetter = OutgoingLetter::whereKey($this->outgoingLetter->id)->lockForUpdate()->firstOrFail();
            if ($this->outgoingLetter->status !== 'draft') {
                throw ValidationException::withMessages(['perihal' => 'Surat sudah diajukan. Muat ulang halaman sebelum mengubah surat.']);
            }
            $this->outgoingLetter->update([
                'tujuan' => $this->tujuan,
                'tanggal_surat' => $this->tanggalSurat,
                'perihal' => $this->perihal,
                'signature_method' => $this->signatureMethod,
                'data' => $this->formData,
                'isi' => $this->isi ?: null,
                'isi_format' => $this->isiFormat,
                'keterangan' => $this->keterangan ?: null,

                // Catatan revisi sudah ditindaklanjuti.
                'rejection_reason' => null,
            ]);

            $this->outgoingLetter->refresh();

            if ($this->outgoingLetter->use_template && $this->outgoingLetter->template_snapshot) {
                app(\App\Services\LetterTemplateService::class)->capture($this->outgoingLetter);
                $this->outgoingLetter->save();
            }
        });

        $this->editing = false;

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Surat berhasil diperbarui.'
        );
    }

    public function removeAttachmentUpload(int $index): void
    {
        unset($this->attachmentUploads[$index]);
        $this->attachmentUploads = array_values($this->attachmentUploads);
        $this->resetValidation(['attachmentUploads', 'attachmentUploads.*']);
    }

    public function saveAttachments(): void
    {
        app(\App\Services\OutgoingLetterAttachmentService::class)->add(auth()->user(), $this->outgoingLetter, $this->attachmentUploads);
        $this->reset('attachmentUploads');
        $this->outgoingLetter->refresh();
        $this->dispatch('notify', type: 'success', message: 'Lampiran berhasil disimpan.');
    }

    public function deleteAttachment(int $id): void
    {
        app(\App\Services\OutgoingLetterAttachmentService::class)->remove(auth()->user(), $this->outgoingLetter, $id);
        $this->outgoingLetter->refresh();
        $this->dispatch('notify', type: 'success', message: 'Lampiran berhasil dihapus.');
    }

    public function uploadDocument(): void
    {
        $this->outgoingLetter->refresh();
        /*
        |--------------------------------------------------------------------------
        | Hanya surat manual
        |--------------------------------------------------------------------------
        */

        if ($this->outgoingLetter->use_template) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Dokumen hanya boleh diupload ketika masih draft / revisi
        |--------------------------------------------------------------------------
        */

        if (
            !in_array($this->outgoingLetter->status, [
                'draft',
                'revision',
            ], true)
        ) {
            return;
        }

        $this->validate([
            'documentFile' => [
                'required',
                'file',
                'mimes:pdf',
                'max:10240',
            ],
        ]);

        try {
            if (! $this->outgoingLetter->usesWetSignature()) {
                app(PdfQrAnchorService::class)->locate($this->documentFile->getRealPath());
            }
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['documentFile' => $e->getMessage()]);
        }

        $oldPath = $this->outgoingLetter->file;
        $path = $this->documentFile->store('outgoing-letters/manual', 'public');
        if (! $path) {
            throw ValidationException::withMessages(['documentFile' => 'PDF gagal disimpan. Silakan coba lagi.']);
        }
        try {
            $this->outgoingLetter->update(['file' => $path]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->outgoingLetter->refresh();

        $this->reset('documentFile');

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Dokumen surat berhasil diupload.'
        );
    }

    public function removeDocument(): void
    {
        $this->outgoingLetter->refresh();
        if ($this->outgoingLetter->use_template) {
            return;
        }

        if (
            !in_array($this->outgoingLetter->status, [
                'draft',
                'revision',
            ], true)
        ) {
            return;
        }

        if ($this->outgoingLetter->file) {
            Storage::disk('public')->delete(
                $this->outgoingLetter->file
            );
        }

        $this->outgoingLetter->update([
            'file' => null,
        ]);

        $this->outgoingLetter->refresh();

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Dokumen surat berhasil dihapus.'
        );
    }

    public function downloadFinal()
    {
        if ($this->outgoingLetter->status !== 'issued') {
            return;
        }

        if (!$this->outgoingLetter->final_file) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Dokumen final belum tersedia.'
            );

            return;
        }

        $path = storage_path(
            'app/' . $this->outgoingLetter->final_file
        );

        if (!file_exists($path)) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'File dokumen final tidak ditemukan.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Nama file download
        |--------------------------------------------------------------------------
        |
        | Nomor surat bisa mengandung "/" atau "\".
        | Karakter tersebut harus dibersihkan karena nama file
        | tidak boleh mengandung path separator.
        |
        */

        $safeNomorSurat = str_replace(
            ['/', '\\'],
            '-',
            $this->outgoingLetter->nomor_surat
        );

        $filename = 'Surat-' . $safeNomorSurat . '.docx';

        return response()->download(
            $path,
            $filename
        );
    }
    public function downloadFinalPdf()
    {
        if ($this->outgoingLetter->status !== 'issued') {
            return;
        }

        if (!$this->outgoingLetter->final_pdf_file) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Dokumen final belum tersedia.'
            );

            return;
        }

        $path = storage_path(
            'app/' . $this->outgoingLetter->final_pdf_file
        );

        if (!file_exists($path)) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'File dokumen final tidak ditemukan.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Nama file download
        |--------------------------------------------------------------------------
        |
        | Nomor surat bisa mengandung "/" atau "\".
        | Karakter tersebut harus dibersihkan karena nama file
        | tidak boleh mengandung path separator.
        |
        */

        $safeNomorSurat = str_replace(
            ['/', '\\'],
            '-',
            $this->outgoingLetter->nomor_surat
        );

        $filename = 'Surat-' . $safeNomorSurat . '.pdf';

        return response()->download(
            $path,
            $filename
        );
    }

    public function uploadSignedDocument(): void
    {
        $this->validate(['signedDocument' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        app(\App\Services\OutgoingLetterWorkflowService::class)->archiveSigned($this->outgoingLetter, $this->signedDocument);
        $this->outgoingLetter->refresh();
        $this->reset('signedDocument');
        $this->dispatch('notify', type: 'success', message: 'Arsip tanda tangan basah tersimpan. Surat selesai.');
    }

    public function previewPdf()
    {
        try {
            if (!$this->outgoingLetter->use_template) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: 'Preview ini hanya untuk surat template.'
                );

                return;
            }

            $path = app(OutgoingLetterPdfService::class)
                ->preview($this->outgoingLetter);

            return response()->file($path);

        } catch (\Throwable $e) {
            report($e);

            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Gagal membuat preview PDF: ' .
                $e->getMessage()
            );
        }
    }
};
?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">
                Detail Surat Keluar
            </flux:heading>

            <flux:text class="mt-1">
                Informasi lengkap surat keluar
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="arrow-left" wire:click="back">
                Kembali
            </flux:button>

            @if ($outgoingLetter->status === 'draft')
                <flux:button wire:click="edit" icon="pencil">
                    Edit
                </flux:button>

                <flux:button wire:click="submitForApproval"
                    wire:confirm="Ajukan surat ini untuk persetujuan Kepala Madrasah?" icon="paper-airplane">
                    Ajukan Persetujuan
                </flux:button>
            @endif
            @if (
                            $outgoingLetter->status === 'draft' &&
                            $outgoingLetter->use_template
                        )
                        <flux:button href="{{ route(
                    (auth()->user()->isStaff() ? 'staff.' : 'admin.').'outgoing-letters.preview',
                    $outgoingLetter
                ) }}" target="_blank" icon="eye">
                            Preview PDF
                        </flux:button>
            @endif
            @if ($outgoingLetter->use_template && in_array($outgoingLetter->status, ['issued', 'awaiting_signature'], true))
            <flux:button href="{{ route((auth()->user()->isStaff() ? 'staff.' : 'admin.').'outgoing-letters.word', $outgoingLetter) }}" icon="document-arrow-down">
                Generate Word
            </flux:button>
            @endif
        </div>
    </div>

    {{-- Catatan Revisi --}}
    @if ($outgoingLetter->status === 'draft' && $outgoingLetter->rejection_reason)

        <flux:card class="border-red-200 dark:border-red-800">

            <div class="flex gap-3">

                <div class="shrink-0">
                    <flux:icon name="exclamation-triangle" class="size-6 text-red-500" />
                </div>

                <div class="min-w-0">

                    <flux:heading size="lg" class="text-red-700 dark:text-red-400">
                        Surat Perlu Direvisi
                    </flux:heading>

                    <flux:text class="mt-1">
                        Surat ini dikembalikan oleh Kepala Madrasah.
                        Perbaiki surat sesuai catatan berikut sebelum diajukan kembali.
                    </flux:text>

                    <div class="mt-4 rounded-lg bg-red-50 p-4 dark:bg-red-950/30">
                        <flux:text class="whitespace-pre-line text-red-800 dark:text-red-300">
                            {{ $outgoingLetter->rejection_reason }}
                        </flux:text>
                    </div>

                </div>

            </div>

        </flux:card>

    @endif


    {{-- Informasi Surat --}}
    <flux:card class="space-y-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading size="lg">
                    {{ $outgoingLetter->letterType?->name ?? $outgoingLetter->jenis_surat }}

                    @if ($outgoingLetter->use_template)
                        <flux:badge color="blue">
                            Template
                        </flux:badge>
                    @else
                        <flux:badge color="zinc">
                            Manual
                        </flux:badge>
                    @endif
                </flux:heading>

                <flux:text class="mt-1">
                    {{ $outgoingLetter->nomor_surat }}
                </flux:text>
            </div>

            @php
                $statusColor = match ($outgoingLetter->status) {
                    'draft' => 'zinc',
                    'awaiting_signature' => 'yellow',
                    'pending' => 'yellow',
                    'issued' => 'green',
                    'cancelled' => 'red',
                    default => 'zinc',
                };

                $statusLabel = $outgoingLetter->statusLabel();
            @endphp

            <div>
                <flux:badge :color="$statusColor">
                    {{ $statusLabel }}
                </flux:badge>

                @if ($outgoingLetter->status === 'issued')

                    @if ($outgoingLetter->final_docx_file)
                        <flux:button wire:click="downloadFinal" icon="document-text">
                            Download Word
                        </flux:button>
                    @endif

                    @if ($outgoingLetter->final_pdf_file)
                        <flux:button wire:click="downloadFinalPdf" icon="document">
                            Download PDF
                        </flux:button>
                    @endif
                @endif
            </div>
        </div>

        <flux:separator />


        {{-- Metadata --}}
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

            <div>
                <flux:text size="sm" class="text-zinc-500">
                    Nomor Surat
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $outgoingLetter->nomor_surat }}
                </flux:text>
            </div>

            <div>
                <flux:text size="sm" class="text-zinc-500">
                    Jenis Surat
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $outgoingLetter->letterType?->name ?? $outgoingLetter->jenis_surat }}
                </flux:text>
            </div>

            <div>
                <flux:text size="sm" class="text-zinc-500">
                    Tanggal Surat
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $outgoingLetter->tanggal_surat?->translatedFormat('d F Y') }}
                </flux:text>
            </div>

            <div>
                <flux:text size="sm" class="text-zinc-500">
                    Tujuan
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $outgoingLetter->tujuan }}
                </flux:text>
            </div>

            <div>
                <flux:text size="sm" class="text-zinc-500">
                    Perihal
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $outgoingLetter->perihal }}
                </flux:text>
            </div>

            <div>
                <flux:text size="sm" class="text-zinc-500">
                    Dibuat Oleh
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $outgoingLetter->creator?->name ?? '-' }}
                </flux:text>
            </div>

        </div>

    </flux:card>

    @if (
            !$outgoingLetter->use_template
            && in_array($outgoingLetter->status, ['draft', 'revision'])
        )

        <flux:card class="mt-6">

            <div class="space-y-1">
                <flux:heading size="sm">
                    Dokumen Surat
                </flux:heading>

                <flux:text class="text-sm">
                    @if ($outgoingLetter->usesWetSignature())
                        Unggah PDF surat sebelum diajukan untuk diperiksa kepala sekolah. Sediakan ruang tanda tangan basah; tidak perlu penanda QR Code.
                    @else
                    Upload PDF hasil ekspor Word dengan tepat satu penanda <code>[[QR_CODE]]</code> pada baris tersendiri.
                    Gunakan teks 10–12 pt dan sisakan area putih 30 × 30 mm ke kanan dan bawah penanda.
                    QR akan muncul di lokasi tersebut setelah Kepala Madrasah menyetujui surat. PDF hasil scan tidak didukung.
                    @endif
                </flux:text>
            </div>

            @error('documentFile')
                <flux:error>{{ $message }}</flux:error>
            @enderror

            @if ($outgoingLetter->file)

                <div class="mt-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">

                    <div class="flex items-center justify-between gap-4">

                        <div class="flex min-w-0 items-center gap-3">

                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                                <flux:icon name="document-text" />
                            </div>

                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">
                                    {{ basename($outgoingLetter->file) }}
                                </div>

                                <div class="text-xs text-zinc-500">
                                    Dokumen sumber surat manual
                                </div>
                            </div>

                        </div>

                        <div class="flex shrink-0 gap-2">

                            <flux:button :href="Storage::disk('public')->url($outgoingLetter->file)" target="_blank" size="sm"
                                variant="ghost" icon="eye">
                                Lihat
                            </flux:button>

                            <flux:button wire:click="removeDocument" wire:confirm="Hapus dokumen surat ini?" size="sm"
                                variant="ghost" icon="trash">
                                Hapus
                            </flux:button>

                        </div>

                    </div>

                </div>

            @else

                <div class="mt-4 space-y-4">

                    <flux:input type="file" wire:model="documentFile" accept=".pdf,application/pdf" label="File Surat" />

                    <flux:text class="text-xs text-zinc-500">
                        Format PDF, maksimal 10 MB. Untuk QR Code, teks harus dapat diseleksi.
                    </flux:text>


                    <div>

                        <flux:button wire:click="uploadDocument" wire:loading.attr="disabled" wire:target="uploadDocument"
                            variant="primary" icon="arrow-up-tray">
                            <span wire:loading.remove wire:target="uploadDocument">
                                Upload Dokumen
                            </span>

                            <span wire:loading wire:target="uploadDocument">
                                Mengupload...
                            </span>
                        </flux:button>

                    </div>

                </div>

            @endif

        </flux:card>

    @endif


    {{-- Data Surat --}}
    @if (count($this->formFields))
        <flux:card class="space-y-6">

            <div>
                <flux:heading size="lg">
                    Data Surat
                </flux:heading>

                <flux:text class="mt-1">
                    Data yang diisi saat pembuatan surat.
                </flux:text>
            </div>

            <flux:separator />

            <div class="grid gap-6 sm:grid-cols-2">

                @foreach ($this->formFields as $field)

                    @php
                        $name = $field['name'] ?? '';
                        $label = $field['label'] ?? $name;
                        $type = $field['type'] ?? 'text';
                        $value = data_get($outgoingLetter->data ?? [], $name);
                    @endphp

                    <div class="{{ in_array($type, ['textarea', 'repeater', 'gtk_assignments']) ? 'sm:col-span-2' : '' }}">

                        <flux:text size="sm" class="text-zinc-500">
                            {{ $label }}
                        </flux:text>

                        @if ($type === 'gtk_assignments')
                            <x-assignment-recipient-summary :letter="$outgoingLetter" />
                        @elseif ($type === 'date')
                            <flux:text class="mt-1 font-medium">{{ $value ? \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('l, d F Y') : '-' }}</flux:text>
                        @elseif ($type === 'repeater')

                            @if (is_array($value) && count($value))
                                <div class="mt-2 space-y-2">
                                    @foreach ($value as $item)
                                        <div class="flex gap-2">
                                            <div class="mt-2 size-1.5 shrink-0 rounded-full bg-zinc-400"></div>

                                            <flux:text>
                                                {{ $item }}
                                            </flux:text>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <flux:text class="mt-1 text-zinc-400">
                                    -
                                </flux:text>
                            @endif

                        @elseif ($type === 'student')

                            @php
                                $student = $value
                                    ? \App\Models\Student::find($value)
                                    : null;
                            @endphp

                            <flux:text class="mt-1 font-medium">
                                {{ $student?->full_name ?? '-' }}

                                @if ($student?->classHistories?->first()?->schoolClass)
                                    — {{ $student->classHistories->first()->schoolClass->name }}
                                @endif
                            </flux:text>

                        @else

                            <flux:text class="mt-1 whitespace-pre-line font-medium">
                                {{ $value ?: '-' }}
                            </flux:text>

                        @endif

                    </div>

                @endforeach

            </div>

        </flux:card>
    @endif


    {{-- Isi Surat --}}
    @if ($outgoingLetter->isi)
        <flux:card class="space-y-6">

            <div>
                <flux:heading size="lg">
                    Isi Surat
                </flux:heading>
            </div>

            <flux:separator />

            <div class="letter-body text-sm leading-7">
                {!! app(\App\Services\LetterBodyService::class)->render($outgoingLetter) !!}
            </div>

        </flux:card>
    @endif


    {{-- Keterangan --}}
    @if ($outgoingLetter->keterangan)
        <flux:card class="space-y-4">

            <flux:heading size="lg">
                Keterangan
            </flux:heading>

            <flux:text class="whitespace-pre-line">
                {{ $outgoingLetter->keterangan }}
            </flux:text>

        </flux:card>
    @endif


    <x-outgoing-letter-attachments :letter="$outgoingLetter" :editable="true" :uploads="$attachmentUploads" />

    <x-outgoing-letter-document :letter="$outgoingLetter" />

    @if ($outgoingLetter->usesWetSignature() && $outgoingLetter->status === 'awaiting_signature')
        <flux:card class="space-y-4">
            <flux:heading size="lg">Unggah hasil tanda tangan basah</flux:heading>
            <flux:text>Cetak dokumen yang disetujui, minta tanda tangan kepala sekolah, lalu unggah scan PDF. Surat selesai setelah arsip tersimpan.</flux:text>
            <flux:input type="file" wire:model="signedDocument" accept=".pdf,application/pdf" label="Scan surat bertanda tangan (PDF, maksimal 10 MB)" />
            <flux:error name="signedDocument" />
            <flux:button wire:click="uploadSignedDocument" wire:loading.attr="disabled" wire:target="signedDocument,uploadSignedDocument" variant="primary">Simpan arsip dan selesaikan</flux:button>
        </flux:card>
    @endif

    @if ($editing)

        <flux:card>

            <flux:heading size="lg">
                Edit Surat
            </flux:heading>

            <div class="mt-5 space-y-5">

                <div>
                    <flux:text class="text-sm">
                        Jenis Surat
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->letterType?->name ?? $outgoingLetter->jenis_surat }}
                    </div>

                    <flux:text class="mt-1 text-xs">
                        Jenis surat tidak dapat diubah.
                    </flux:text>
                </div>

<flux:select wire:model.live="signatureMethod" label="Metode tanda tangan">
                        <option value="qr">QR Code persetujuan kepala sekolah</option>
                        <option value="wet">Tanda tangan basah</option>
                    </flux:select>
                    <flux:error name="signatureMethod" />
                    <flux:description>
                        {{ $signatureMethod === 'wet' ? 'Setelah disetujui, cetak tanpa QR Code, minta tanda tangan kepala sekolah, lalu unggah scan PDF untuk arsip.' : 'QR Code verifikasi dibubuhkan setelah surat disetujui kepala sekolah.' }}
                    </flux:description>
                @if (!$this->isAssignmentLetter)
                    <flux:textarea wire:model="tujuan" label="Tujuan" rows="2" />
                @endif

                <flux:input type="date" wire:model="tanggalSurat" label="Tanggal Surat" />

                <flux:input wire:model="perihal" label="Perihal" />

                @foreach ($this->formFields as $field)

                    @php
                        $name = $field['name'];
                        $label = $field['label'] ?? $name;
                        $type = $field['type'] ?? 'text';
                    @endphp

                    @if ($type === 'gtk_assignments')
                        <x-assignment-recipients :field="$name" :rows="$formData[$name] ?? []" />
                    @elseif ($type === 'repeater')

                        <div class="space-y-2">
                            <flux:text class="font-medium">
                                {{ $label }}
                            </flux:text>

                            @foreach ($formData[$name] ?? [''] as $index => $item)

                                <div class="flex gap-2">

                                    <flux:input wire:model="formData.{{ $name }}.{{ $index }}" />

                                    <flux:button type="button" variant="ghost" icon="trash"
                                        wire:click="removeRepeater('{{ $name }}', {{ $index }})" />

                                </div>

                            @endforeach

                            <flux:button type="button" size="sm" icon="plus" wire:click="addRepeater('{{ $name }}')">
                                Tambah
                            </flux:button>
                        </div>

                    @elseif ($type === 'student')

                        {{-- Untuk sementara gunakan select biasa.
                        Kalau Show edit nanti mau Select2,
                        kita pasang seperti form Admin Index. --}}

                        <flux:select wire:model="formData.{{ $name }}" label="{{ $label }}">
                            <option value="">Pilih siswa</option>

                            @foreach (
                                    \App\Models\Student::where('is_active', true)
                                        ->orderBy('full_name')
                                        ->get()
                                    as $student
                                )
                                <option value="{{ $student->id }}">
                                    {{ $student->full_name }}
                                </option>
                            @endforeach
                        </flux:select>

                    @elseif ($type === 'gtk')
                        <flux:select wire:model="formData.{{ $name }}" label="{{ $label }}">
                            <option value="">Pilih GTK</option>
                            @foreach (\App\Models\Gtk::where('is_active', true)->orderBy('full_name')->get() as $gtk)
                                <option value="{{ $gtk->id }}">{{ $gtk->full_name }}</option>
                            @endforeach
                        </flux:select>
                    @elseif ($type === 'date')
                        <flux:input type="date" wire:model="formData.{{ $name }}" label="{{ $label }}" />
                    @elseif ($type === 'textarea')
                        <flux:textarea wire:model="formData.{{ $name }}" label="{{ $label }}" />
                    @else

                        <flux:input wire:model="formData.{{ $name }}" label="{{ $label }}" />

                    @endif

                @endforeach

                @if ($this->outgoingLetter->use_template && $this->usesBody)
<x-letter-editor wire:key="edit-letter-body-{{ $outgoingLetter->id }}" :sample="app(\App\Services\LetterTemplateCatalog::class)->find($outgoingLetter->template_snapshot['template_view'] ?? $this->selectedLetterType?->template_view)['default_body'] ?? ''" />
                    <flux:description>Salam dan tanda tangan ditambahkan otomatis. Periksa susunan akhir melalui pratinjau PDF.</flux:description>
                @endif

                <flux:textarea wire:model="keterangan" label="Keterangan" rows="4" />

                <div class="flex justify-end gap-2">

                    <flux:button type="button" wire:click="$set('editing', false)">
                        Batal
                    </flux:button>

                    <flux:button variant="primary" wire:click="updateLetter">
                        Simpan Perubahan
                    </flux:button>

                </div>

            </div>

        </flux:card>

    @else

        {{-- detail surat yang sekarang sudah kita punya --}}

    @endif
</div>
