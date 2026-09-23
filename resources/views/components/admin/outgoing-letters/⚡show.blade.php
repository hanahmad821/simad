<?php

use App\Models\OutgoingLetter;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Services\OutgoingLetterDocumentService;
use App\Models\LetterType;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
new
    #[Title('Detail Surat Keluar')]
    class extends Component {
    use WithFileUploads;
    public OutgoingLetter $outgoingLetter;

    public bool $editing = false;

    public string $tujuan = '';
    public string $tanggalSurat = '';
    public string $perihal = '';
    public string $isi = '';
    public bool $useTemplate = true;
    public string $keterangan = '';
    public $documentFile;
    public array $formData = [];

    public function mount(OutgoingLetter $outgoingLetter): void
    {
        $this->outgoingLetter = $outgoingLetter->load([
            'letterType',
            'creator',
        ]);
    }

    public function back(): void
    {
        $this->redirectRoute('admin.outgoing-letters.index');
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
        if ($this->outgoingLetter->status !== 'draft') {
            return;
        }

        $this->outgoingLetter->update([
            'status' => 'pending',
            'rejection_reason' => null,
        ]);

        $this->outgoingLetter->refresh();

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Surat berhasil diajukan untuk persetujuan Kepala Madrasah.'
        );
    }

    public function edit(): void
    {
        if ($this->outgoingLetter->status !== 'draft') {
            return;
        }

        $this->tujuan = $this->outgoingLetter->tujuan;
        $this->tanggalSurat = $this->outgoingLetter->tanggal_surat?->format('Y-m-d') ?? '';
        $this->perihal = $this->outgoingLetter->perihal;
        $this->isi = $this->outgoingLetter->isi ?? '';
        $this->useTemplate = $this->outgoingLetter->use_template;
        $this->keterangan = $this->outgoingLetter->keterangan ?? '';
        $this->formData = $this->outgoingLetter->data ?? [];

        $this->editing = true;
    }

    #[Computed]
    public function selectedLetterType(): ?LetterType
    {
        return LetterType::find($this->letterTypeIdForm);
    }

    #[Computed]
    public function formFields(): array
    {
        return $this->selectedLetterType?->fields ?? [];
    }

    public function updateLetter(): void
    {
        if ($this->outgoingLetter->status !== 'draft') {
            return;
        }

        $rules = [
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

        foreach ($this->formFields as $field) {

            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $required = $field['required'] ?? false;

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

        $this->outgoingLetter->update([
            'tujuan' => $this->tujuan,
            'tanggal_surat' => $this->tanggalSurat,
            'perihal' => $this->perihal,
            'use_template' => $this->useTemplate,
            'data' => $this->formData,
            'isi' => $this->isi ?: null,
            'keterangan' => $this->keterangan ?: null,

            // Catatan revisi sudah ditindaklanjuti.
            'rejection_reason' => null,
        ]);

        $this->outgoingLetter->refresh();

        $this->editing = false;

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Surat berhasil diperbarui.'
        );
    }

    public function uploadDocument(): void
    {
        if ($this->outgoingLetter->use_template) {
            return;
        }

        if ($this->outgoingLetter->status !== 'issued') {
            return;
        }

        $this->validate([
            'documentFile' => [
                'required',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ],
        ]);

        if ($this->outgoingLetter->file) {
            Storage::disk('public')->delete($this->outgoingLetter->file);
        }

        $path = $this->documentFile->store(
            'outgoing-letters',
            'public'
        );

        $this->outgoingLetter->update([
            'file' => $path,
        ]);

        $this->outgoingLetter->refresh();

        $this->reset('documentFile');

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Dokumen surat berhasil diupload.'
        );
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
            <flux:button href="{{ route('admin.outgoing-letters.word', $outgoingLetter) }}" icon="document-arrow-down">
                Generate Word
            </flux:button>
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
                </flux:heading>

                <flux:text class="mt-1">
                    {{ $outgoingLetter->nomor_surat }}
                </flux:text>
            </div>

            @php
                $statusColor = match ($outgoingLetter->status) {
                    'draft' => 'zinc',
                    'pending' => 'yellow',
                    'issued' => 'green',
                    'cancelled' => 'red',
                    default => 'zinc',
                };

                $statusLabel = match ($outgoingLetter->status) {
                    'draft' => 'Draft',
                    'pending' => 'Menunggu Persetujuan',
                    'issued' => 'Terbit',
                    'cancelled' => 'Dibatalkan',
                    default => ucfirst($outgoingLetter->status),
                };
            @endphp

            <flux:badge :color="$statusColor">
                {{ $statusLabel }}
            </flux:badge>
        </div>

        @if (!$outgoingLetter->use_template && $outgoingLetter->status === 'issued')
            <flux:card class="mt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm">
                            Dokumen Surat
                        </flux:heading>

                        <flux:text class="mt-1 text-sm">
                            Upload dokumen surat yang sudah dibuat dan ditandatangani.
                        </flux:text>
                    </div>

                    @if ($outgoingLetter->file)
                        <flux:badge color="green">
                            Sudah diupload
                        </flux:badge>
                    @else
                        <flux:badge color="amber">
                            Belum ada dokumen
                        </flux:badge>
                    @endif
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <flux:input type="file" wire:model="documentFile" accept=".pdf,.doc,.docx" label="Dokumen Surat" />

                        <flux:text class="mt-1 text-xs text-zinc-500">
                            Format PDF, DOC, atau DOCX. Maksimal 10 MB.
                        </flux:text>

                        @error('documentFile')
                            <flux:text class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </flux:text>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button wire:click="uploadDocument" wire:loading.attr="disabled" wire:target="uploadDocument"
                            variant="primary" icon="arrow-up-tray">
                            <span wire:loading.remove wire:target="uploadDocument">
                                Upload Dokumen
                            </span>

                            <span wire:loading wire:target="uploadDocument">
                                Mengupload...
                            </span>
                        </flux:button>

                        @if ($outgoingLetter->file)
                            <flux:button :href="Storage::disk('public')->url($outgoingLetter->file)" target="_blank" icon="eye">
                                Lihat Dokumen
                            </flux:button>
                        @endif
                    </div>
                </div>
            </flux:card>
        @endif
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


    {{-- Data Surat --}}
    @if ($outgoingLetter->letterType && count($outgoingLetter->letterType->fields ?? []))
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

                @foreach ($outgoingLetter->letterType->fields ?? [] as $field)

                    @php
                        $name = $field['name'] ?? '';
                        $label = $field['label'] ?? $name;
                        $type = $field['type'] ?? 'text';
                        $value = data_get($outgoingLetter->data ?? [], $name);
                    @endphp

                    <div class="{{ $type === 'textarea' || $type === 'repeater' ? 'sm:col-span-2' : '' }}">

                        <flux:text size="sm" class="text-zinc-500">
                            {{ $label }}
                        </flux:text>

                        @if ($type === 'repeater')

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

            <div class="prose prose-sm max-w-none dark:prose-invert">
                {!! nl2br(e($outgoingLetter->isi)) !!}
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


    {{-- File --}}
    @if ($outgoingLetter->file)
        <flux:card class="flex items-center justify-between gap-4">

            <div>
                <flux:heading size="sm">
                    File Surat
                </flux:heading>

                <flux:text class="mt-1">
                    File surat yang tersimpan.
                </flux:text>
            </div>

            <flux:button href="{{ Storage::url($outgoingLetter->file) }}" target="_blank" icon="arrow-top-right-on-square">
                Buka File
            </flux:button>

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

                <flux:input wire:model="tujuan" label="Tujuan" />

                <flux:input type="date" wire:model="tanggalSurat" label="Tanggal Surat" />

                <flux:input wire:model="perihal" label="Perihal" />

                @foreach ($this->formFields as $field)

                    @php
                        $name = $field['name'];
                        $label = $field['label'] ?? $name;
                        $type = $field['type'] ?? 'text';
                    @endphp

                    @if ($type === 'repeater')

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

                    @else

                        <flux:input wire:model="formData.{{ $name }}" label="{{ $label }}" />

                    @endif

                @endforeach

                <flux:textarea wire:model="isi" label="Isi Surat" rows="8" />

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
