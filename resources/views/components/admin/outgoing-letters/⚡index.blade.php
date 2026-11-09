<?php

use App\Models\LetterType;
use App\Models\OutgoingLetter;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Services\OutgoingLetterNumberService;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;


new class extends \Livewire\Component {
    use WithPagination;
    use \App\Livewire\Concerns\HasAssignmentRecipients;
    use \Livewire\WithFileUploads;

    public array $attachmentUploads = [];

    #[Url]
    public string $search = '';

    #[Url]
    public string $letterTypeId = '';

    #[Url]
    public string $status = '';

    public bool $showForm = false;

    public string $letterTypeIdForm = '';
    public string $tujuan = '';
    public string $tanggalSurat = '';
    public string $perihal = '';
    public string $isi = '';
    public string $isiFormat = 'text';
    public bool $useTemplate = true;
    public string $signatureMethod = 'qr';
    public string $keterangan = '';

    public array $formData = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->canManageLetters(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLetterTypeId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'letterTypeId',
            'status',
        ]);

        $this->resetPage();
    }

    public function updatedLetterTypeIdForm(): void
    {
        $this->formData = [];
        if ($this->isAssignmentLetter) $this->formData['penerima_tugas'] = [['gtk_id' => '', 'pangkat' => '']];
        $this->reset('attachmentUploads');
        if ($this->perihal === '') $this->perihal = $this->selectedLetterType?->name ?? '';
    }

    public function updatedUseTemplate(): void
    {
        $this->reset('attachmentUploads');
    }

    public function removeAttachmentUpload(int $index): void
    {
        unset($this->attachmentUploads[$index]);
        $this->attachmentUploads = array_values($this->attachmentUploads);
        $this->resetValidation(['attachmentUploads', 'attachmentUploads.*']);
    }

    public function with(): array
    {
        $letterTypes = LetterType::query()->where('is_active', true)->orderBy('name')->get();

        return [
            'letters' => OutgoingLetter::query()
                ->with(['letterType', 'creator'])
                ->when($this->search, function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('nomor_surat', 'like', "%{$this->search}%")
                            ->orWhere('tujuan', 'like', "%{$this->search}%")
                            ->orWhere('perihal', 'like', "%{$this->search}%");
                    });
                })
                ->when(
                    $this->letterTypeId,
                    fn($query) => $query->where(
                        'letter_type_id',
                        $this->letterTypeId
                    )
                )
                ->when(
                    $this->status,
                    fn($query) => $query->where('status', $this->status)
                )
                ->latest('tanggal_surat')
                ->latest('id')
                ->paginate(10),

            'letterTypes' => $letterTypes,
            'templateLetterTypes' => $letterTypes->filter(fn ($type) => app(\App\Services\LetterTemplateCatalog::class)->availableFor($type)),
        ];
    }

    #[Computed]
    public function students()
    {
        return Student::query()
            ->where('is_active', true)
            ->with([
                'classHistories.schoolClass',
            ])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nisn']);
    }

    #[Computed]
    public function selectedLetterType(): ?LetterType
    {
        if (!$this->letterTypeIdForm) {
            return null;
        }

        return LetterType::query()
            ->where('is_active', true)
            ->find($this->letterTypeIdForm);
    }

    #[Computed]
    public function formFields(): array
    {
        return $this->selectedLetterType ? app(\App\Services\LetterTemplateCatalog::class)->fieldsFor($this->selectedLetterType) : [];
    }

    #[Computed]
    public function usesBody(): bool
    {
        return app(\App\Services\LetterTemplateCatalog::class)->usesBody($this->selectedLetterType?->template_view);
    }

    public function openCreate(): void
    {
        $this->reset([
            'letterTypeIdForm',
            'tujuan',
            'perihal',
            'isi',
            'isiFormat',
            'keterangan',
            'formData',
            'attachmentUploads',
        ]);

        $this->tanggalSurat = now()->format('Y-m-d');
        $this->useTemplate = true;
        $this->signatureMethod = 'qr';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;

        $this->reset([
            'letterTypeIdForm',
            'tujuan',
            'tanggalSurat',
            'perihal',
            'isi',
            'isiFormat',
            'keterangan',
            'formData',
            'attachmentUploads',
        ]);

        $this->useTemplate = true;
        $this->signatureMethod = 'qr';
        $this->resetValidation();
    }

    public function addRepeater(string $field): void
    {
        $this->formData[$field] ??= [];

        $this->formData[$field][] = '';
    }

    public function removeRepeater(string $field, int $index): void
    {
        unset($this->formData[$field][$index]);

        $this->formData[$field] = array_values(
            $this->formData[$field]
        );
    }

    public function save(): void
    {
        $this->validate(['letterTypeIdForm' => ['required', 'integer', 'exists:letter_types,id']]);
        $letterType = LetterType::query()
            ->where('is_active', true)
            ->findOrFail($this->letterTypeIdForm);

        if ($this->useTemplate && ! app(\App\Services\LetterTemplateCatalog::class)->availableFor($letterType)) {
            $this->addError('letterTypeIdForm', 'Jenis surat ini belum memiliki template baku. Pilih jenis lain atau gunakan Surat Manual.');
            return;
        }

        $this->prepareAssignmentTarget();

        $rules = [
            'signatureMethod' => ['required', 'in:qr,wet'],
            'letterTypeIdForm' => [
                'required',
                'exists:letter_types,id',
            ],

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

        /*
        |--------------------------------------------------------------------------
        | Validasi field dinamis
        |--------------------------------------------------------------------------
        |
        | Hanya surat template yang memiliki field dinamis.
        | Surat manual cukup menyimpan metadata surat.
        |
        */

        $rules['isi'] = [$this->useTemplate && $this->usesBody ? 'required' : 'nullable', 'string', 'max:100000'];

        $this->validate($rules);
        $this->validate(['isiFormat' => ['required', 'in:text,html']]);
        if ($this->useTemplate && $this->usesBody) {
            $this->isi = app(\App\Services\LetterBodyService::class)->clean($this->isi, $this->isiFormat);
        }


        if ($this->useTemplate) {
            $catalog = app(\App\Services\LetterTemplateCatalog::class);
            $this->validate(app(\App\Services\LetterTemplateService::class)->letterRules($catalog->fieldsFor($letterType)));
        }

        /*
        |--------------------------------------------------------------------------
        | Generate nomor surat
        |--------------------------------------------------------------------------
        */

        $date = \Carbon\Carbon::parse($this->tanggalSurat);


        /*
        |--------------------------------------------------------------------------
        | Simpan surat
        |--------------------------------------------------------------------------
        */

        $outgoingLetter = app(\App\Services\OutgoingLetterAttachmentService::class)->create(Auth::user(), $this->attachmentUploads, function () use ($letterType, $date) {
        $lockedType = LetterType::whereKey($letterType->id)->lockForUpdate()->firstOrFail();
        abort_unless($lockedType->is_active, 422, 'Jenis surat tidak aktif. Pilih jenis surat aktif.');
        if ($this->useTemplate) {
            $catalog = app(\App\Services\LetterTemplateCatalog::class);
            if (! $catalog->availableFor($lockedType)) throw \Illuminate\Validation\ValidationException::withMessages(['letterTypeIdForm' => 'Template baku tidak tersedia. Pilih ulang jenis surat.']);
            $this->validate(app(\App\Services\LetterTemplateService::class)->letterRules($catalog->fieldsFor($lockedType)));
            $this->validate(['isi' => [$catalog->usesBody($lockedType->template_view) ? 'required' : 'nullable', 'string', 'max:100000']]);
        }
        $number = app(OutgoingLetterNumberService::class)->generate($lockedType->code, $date);
        $letter = new OutgoingLetter([
            'letter_type_id' => $letterType->id,

            'nomor_urut' => $number['nomor_urut'],
            'nomor_tahun' => $number['nomor_tahun'],
            'nomor_surat' => $number['nomor_surat'],

            // Kompatibilitas dengan struktur lama
            'jenis_surat' => $lockedType->name,

            'use_template' => $this->useTemplate,
            'signature_method' => $this->signatureMethod,

            'tujuan' => $this->tujuan,
            'tanggal_surat' => $this->tanggalSurat,
            'perihal' => $this->perihal,

            /*
            |--------------------------------------------------------------------------
            | Data surat
            |--------------------------------------------------------------------------
            |
            | Template:
            |   simpan dynamic fields
            |
            | Manual:
            |   belum ada dynamic fields
            |
            */

            'data' => $this->useTemplate
                ? $this->formData
                : null,

            /*
            |--------------------------------------------------------------------------
            | Isi surat
            |--------------------------------------------------------------------------
            |
            | Surat manual tidak menggunakan isi dari sistem.
            | Isinya berasal dari file PDF yang nanti diupload.
            |
            */

            'isi' => $this->useTemplate
                ? ($this->isi ?: null)
                : null,

            'isi_format' => $this->useTemplate ? $this->isiFormat : 'text',
            'keterangan' => $this->keterangan ?: null,

            /*
            |--------------------------------------------------------------------------
            | Semua surat baru mulai dari draft
            |--------------------------------------------------------------------------
            |
            | Template:
            |   draft → pending → issued
            |
            | Manual:
            |   draft → upload → pending → issued
            |
            */

            'status' => 'draft',

            'created_by' => Auth::id(),
        ]);
        if ($this->useTemplate) app(\App\Services\LetterTemplateService::class)->capture($letter, $lockedType);
        $letter->save();
        return $letter;
        });

        $this->closeForm();

        /*
        |--------------------------------------------------------------------------
        | Pesan berbeda berdasarkan jenis surat
        |--------------------------------------------------------------------------
        */

        if ($outgoingLetter->use_template) {
            session()->flash(
                'success',
                "Surat berhasil dibuat dengan nomor {$outgoingLetter->nomor_surat}."
            );
        } else {
            session()->flash(
                'success',
                "Nomor surat {$outgoingLetter->nomor_surat} berhasil dibooking. Silakan upload dokumen surat melalui halaman detail."
            );
        }
    }

    public function submitForApproval(int $id): void
    {
        $letter = OutgoingLetter::findOrFail($id);

        app(\App\Services\OutgoingLetterWorkflowService::class)->submit($letter);

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Surat berhasil diajukan untuk persetujuan Kepala Madrasah.'
        );
    }

};
?>

<div class="space-y-6">

    <flux:error name="documentFile" />

    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                Surat Keluar
            </flux:heading>

            <flux:subheading>
                Kelola dan catat surat keluar madrasah.
            </flux:subheading>
        </div>

        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            Surat Keluar Baru
        </flux:button>
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-4">

            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nomor, tujuan, atau perihal..."
                icon="magnifying-glass" />

            <flux:select wire:model.live="letterTypeId">
                <option value="">
                    Semua Jenis Surat
                </option>

                @foreach ($letterTypes as $type)
                    <option value="{{ $type->id }}">
                        {{ $type->name }} ({{ $type->code }})
                    </option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status">
                <option value="">
                    Semua Status
                </option>

                <option value="draft">
                    Draft
                </option>

                <option value="pending">Menunggu Persetujuan</option>
                <option value="awaiting_signature">Menunggu Tanda Tangan Basah</option>
                <option value="issued">
                    Terbit
                </option>

                <option value="cancelled">
                    Dibatalkan
                </option>
            </flux:select>

            <flux:button wire:click="resetFilters" variant="ghost" icon="arrow-path">
                Reset
            </flux:button>

        </div>
    </flux:card>

    <flux:card class="overflow-hidden">

        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">

                        <th class="px-4 py-3 text-left font-medium">
                            Nomor Surat
                        </th>

                        <th class="px-4 py-3 text-left font-medium">
                            Jenis
                        </th>

                        <th class="px-4 py-3 text-left font-medium">
                            Tujuan
                        </th>

                        <th class="px-4 py-3 text-left font-medium">
                            Perihal
                        </th>

                        <th class="px-4 py-3 text-left font-medium">
                            Tanggal
                        </th>

                        <th class="px-4 py-3 text-left font-medium">
                            Status
                        </th>

                        <th class="px-4 py-3 text-right font-medium">
                            Aksi
                        </th>

                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">

                    @forelse ($letters as $letter)

                                        <tr>

                                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                                {{ $letter->nomor_surat }}
                                                <div class="text-xs text-zinc-500">{{ $letter->signatureLabel() }}</div>
                                            </td>

                                            <td class="px-4 py-3">

                                                @if ($letter->letterType)

                                                    <div>
                                                        {{ $letter->letterType->name }}
                                                    </div>

                                                    <div class="mt-1 flex flex-wrap gap-1">
                                                        <div class="text-xs text-zinc-500">
                                                            Kode: {{ $letter->letterType->code }}
                                                        </div>
                                                        <div>
                                                            @if ($letter->use_template)
                                                                <flux:badge size="sm" color="blue">
                                                                    Template
                                                                </flux:badge>
                                                            @else
                                                                <flux:badge size="sm" color="zinc">
                                                                    Manual
                                                                </flux:badge>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else

                                                    -

                                                @endif

                                            </td>

                                            <td class="px-4 py-3">
                                                {{ $letter->tujuan }}
                                            </td>

                                            <td class="px-4 py-3">
                                                {{ $letter->perihal }}
                                            </td>

                                            <td class="px-4 py-3 whitespace-nowrap">
                                                {{ $letter->tanggal_surat?->format('d/m/Y') }}
                                            </td>

                                            <td class="px-4 py-3">
                                                <div class="space-y-2">

                                                    <flux:badge
                                                        :color="match ($letter->status) {
                                                                                                                                                                                                                                                                                                                                                                                                                            'draft' => $letter->rejection_reason ? 'red' : 'zinc',
                                                                                                                                                                                                                                                                                                                                                                                                                            'awaiting_signature' => 'yellow',
                            'pending' => 'yellow',
                                                                                                                                                                                                                                                                                                                                                                                                                            'issued' => 'green',
                                                                                                                                                                                                                                                                                                                                                                                                                            'cancelled' => 'red',
                                                                                                                                                                                                                                                                                                                                                                                                                            default => 'zinc',
                                                                                                                                                                                                                                                                                                                                                                                                                        }">
                                                        {{ $letter->statusLabel() }}
                                                    </flux:badge>

                                                    @if ($letter->status === 'draft' && $letter->rejection_reason)
                                                        <div class="max-w-xs text-xs text-red-600 dark:text-red-400">
                                                            <span class="font-medium">Catatan:</span>
                                                            {{ $letter->rejection_reason }}
                                                        </div>
                                                    @endif

                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-right">

                                                <flux:button href="{{ route((auth()->user()->isStaff() ? 'staff.' : 'admin.').'outgoing-letters.show', $letter) }}" variant="ghost"
                                                    size="sm" icon="eye">
                                                    Lihat
                                                </flux:button>
                                                @if ($letter->status === 'draft')
                                                    <flux:button size="sm" icon="paper-airplane"
                                                        wire:click="submitForApproval({{ $letter->id }})"
                                                        wire:confirm="Ajukan surat ini untuk persetujuan Kepala Madrasah?">
                                                        Ajukan Persetujuan
                                                    </flux:button>
                                                @endif
                                            </td>

                                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-zinc-500">
                                Belum ada surat keluar.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>
        </div>

        @if ($letters->hasPages())

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $letters->links() }}
            </div>

        @endif

    </flux:card>

    @if ($showForm)

        <flux:modal wire:model.self="showForm" class="w-[calc(100vw-2rem)] md:w-[960px] max-w-none">

            <div class="space-y-6">

                <div>
                    <flux:heading size="lg">
                        Surat Keluar Baru
                    </flux:heading>

                    <flux:subheading>
                        Nomor surat akan dibuat otomatis oleh sistem.
                    </flux:subheading>
                </div>

                <div class="space-y-4">

                    {{-- Metode Surat --}}
                    <div>
                        <flux:label>Metode Surat</flux:label>

                        <div class="mt-2 flex gap-6">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="radio" wire:model.live="useTemplate" value="1"
                                    class="text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                    Gunakan Template
                                </span>
                            </label>

                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="radio" wire:model.live="useTemplate" value="0"
                                    class="text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                    Surat Manual
                                </span>
                            </label>
                        </div>

                        <flux:description class="mt-1">
                            Surat manual digunakan untuk surat yang dibuat di luar sistem.
                        </flux:description>
                    </div>

<flux:select wire:model.live="signatureMethod" label="Metode tanda tangan">
                        <option value="qr">QR Code persetujuan kepala sekolah</option>
                        <option value="wet">Tanda tangan basah</option>
                    </flux:select>
                    <flux:error name="signatureMethod" />
                    <flux:description>
                        {{ $signatureMethod === 'wet' ? 'Setelah disetujui, cetak tanpa QR Code, minta tanda tangan kepala sekolah, lalu unggah scan PDF untuk arsip.' : 'QR Code verifikasi dibubuhkan setelah surat disetujui kepala sekolah.' }}
                    </flux:description>

                    {{-- Jenis Surat --}}
                    <flux:select wire:model.live="letterTypeIdForm" label="Jenis Surat">
                        <option value="">
                            {{ $useTemplate ? 'Pilih jenis surat dengan template baku' : 'Pilih jenis surat' }}
                        </option>

                        @foreach ($useTemplate ? $templateLetterTypes : $letterTypes as $type)
                            <option value="{{ $type->id }}">
                                {{ $type->name }} — Kode {{ $type->code }}
                            </option>
                        @endforeach
                    </flux:select>

                    <flux:error name="letterTypeIdForm" />
                    @if ($useTemplate)
                        <flux:description>Template mengikuti pengaturan Jenis Surat. @if(auth()->user()->isAdmin())<a href="{{ route('admin.letter-types.index') }}" class="underline">Kelola jenis surat</a>@endif</flux:description>
                    @endif

                    {{-- Dynamic Fields - hanya Template --}}
                    @if ($useTemplate && $this->selectedLetterType)

                        <div class="space-y-4">

                            @foreach ($this->formFields as $field)

                                <div>

                                    <flux:label>
                                        {{ $field['label'] }}

                                        @if ($field['required'] ?? false)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </flux:label>

                                    {{-- Text --}}
                                    @if (($field['type'] ?? 'text') === 'gtk_assignments')
                                        <x-assignment-recipients :field="$field['name']" :rows="$formData[$field['name']] ?? []" />
                                    @elseif (($field['type'] ?? 'text') === 'text')

                                        <flux:input aria-label="{{ $field['label'] }}" wire:model="formData.{{ $field['name'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}" />

                                        {{-- Textarea --}}
                                    @elseif ($field['type'] === 'textarea')

                                        <flux:textarea aria-label="{{ $field['label'] }}" wire:model="formData.{{ $field['name'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}" />

                                    @elseif ($field['type'] === 'date')
                                        <flux:input aria-label="{{ $field['label'] }}" type="date" wire:model="formData.{{ $field['name'] }}" />
                                    @elseif ($field['type'] === 'gtk')
                                        <flux:select wire:model="formData.{{ $field['name'] }}">
                                            <option value="">Pilih GTK</option>
                                            @foreach (\App\Models\Gtk::where('is_active', true)->orderBy('full_name')->get() as $gtk)
                                                <option value="{{ $gtk->id }}">{{ $gtk->full_name }}</option>
                                            @endforeach
                                        </flux:select>

                                        {{-- Student / Select2 --}}
                                    @elseif ($field['type'] === 'student')

                                        <div wire:ignore class="mt-2" id="student-select-wrapper-{{ $field['name'] }}">
                                            <select id="student-select-{{ $field['name'] }}" class="student-select w-full"
                                                data-field="{{ $field['name'] }}">
                                                <option value="">Pilih siswa</option>

                                                @foreach ($this->students as $student)
                                                    <option value="{{ $student->id }}">
                                                        {{ $student->full_name }}

                                                        @if ($student->classHistories->first()?->schoolClass)
                                                            — {{ $student->classHistories->first()->schoolClass->name }}
                                                        @endif

                                                        @if ($student->nisn)
                                                            — NISN: {{ $student->nisn }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Repeater --}}
                                    @elseif ($field['type'] === 'repeater')

                                        <div class="space-y-2">

                                            @foreach (
                                                    $formData[$field['name']] ?? ['']
                                                    as $index => $value
                                                )

                                                <div class="flex gap-2">

                                                    <flux:input wire:model="formData.{{ $field['name'] }}.{{ $index }}"
                                                        placeholder="Isi poin..." />

                                                    <flux:button type="button" variant="ghost"
                                                        wire:click="removeRepeater('{{ $field['name'] }}', {{ $index }})">
                                                        Hapus
                                                    </flux:button>

                                                </div>

                                            @endforeach

                                            <flux:button type="button" variant="ghost" wire:click="addRepeater('{{ $field['name'] }}')">
                                                + Tambah Poin
                                            </flux:button>

                                        </div>

                                    @endif

                                    @error("formData.{$field['name']}")
                                        <flux:error>
                                            {{ $message }}
                                        </flux:error>
                                    @enderror

                                </div>

                            @endforeach

                        </div>

                    @endif

                    {{-- Tujuan --}}
                    @if (!$this->isAssignmentLetter)
                        <flux:textarea wire:model="tujuan" label="Tujuan" rows="2" placeholder="Contoh: Bapak/Ibu Orang Tua/Wali Murid&#10;MA Ma’arif NU 1 Kemranjen" />
                    @endif

                    <flux:error name="tujuan" />

                    {{-- Tanggal --}}
                    <flux:input wire:model="tanggalSurat" type="date" label="Tanggal Surat" />

                    <flux:error name="tanggalSurat" />

                    {{-- Perihal --}}
                    <flux:input wire:model="perihal" label="Perihal" placeholder="Masukkan perihal surat" />

                    <flux:error name="perihal" />

                    {{-- Isi Surat - hanya Template --}}
                    @if ($useTemplate && $this->usesBody)
<x-letter-editor wire:key="create-letter-body-{{ $letterTypeIdForm }}" :sample="app(\App\Services\LetterTemplateCatalog::class)->find($this->selectedLetterType?->template_view)['default_body'] ?? ''" />
                        <flux:description>Salam dan tanda tangan ditambahkan otomatis. Periksa susunan akhir melalui pratinjau PDF.</flux:description>


                    @endif

                    @if ($useTemplate && $this->selectedLetterType?->template_view === 'pdf.letters.pemberitahuan')
                        <x-letter-attachment-upload :uploads="$attachmentUploads" />
                    @endif

                    {{-- Keterangan --}}
                    <flux:textarea wire:model="keterangan" label="Keterangan" rows="3"
                        placeholder="Keterangan tambahan (opsional)" />

                    <flux:error name="keterangan" />

                </div>

                <div class="flex justify-end gap-2">

                    <flux:button wire:click="closeForm" variant="ghost">
                        Batal
                    </flux:button>

                    <flux:button wire:click="save" wire:loading.attr="disabled" wire:target="attachmentUploads,save" variant="primary">
                        {{ $useTemplate ? 'Buat Surat' : 'Booking Nomor' }}
                    </flux:button>

                </div>

            </div>

        </flux:modal>

    @endif

</div>

@script
<script>
    function initStudentSelect() {
        $('.student-select').each(function () {
            const $select = $(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            const field = $select.data('field');

            $select.select2({
                width: '100%',
                placeholder: 'Pilih siswa...',
                allowClear: true,
                dropdownParent: $select.parent(),
            });

            $select.on('change.student', function () {
                const value = $(this).val();

                $wire.set(
                    `formData.${field}`,
                    value ? parseInt(value) : null
                );
            });
        });
    }

    initStudentSelect();

    Livewire.hook('morphed', () => {
        initStudentSelect();
    });
</script>
@endscript

