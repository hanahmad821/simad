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
    public bool $useTemplate = true;
    public string $keterangan = '';

    public array $formData = [];

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
    }

    public function with(): array
    {
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

            'letterTypes' => LetterType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
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
        return $this->selectedLetterType?->fields ?? [];
    }

    public function openCreate(): void
    {
        $this->reset([
            'letterTypeIdForm',
            'tujuan',
            'perihal',
            'isi',
            'keterangan',
            'formData',
        ]);

        $this->tanggalSurat = now()->format('Y-m-d');
        $this->useTemplate = true;
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
            'keterangan',
            'formData',
        ]);

        $this->useTemplate = true;
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
        $letterType = LetterType::query()
            ->where('is_active', true)
            ->findOrFail($this->letterTypeIdForm);

        $rules = [
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
        | Validasi field template
        |--------------------------------------------------------------------------
        */

        if ($this->useTemplate) {
            foreach ($letterType->fields ?? [] as $field) {
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
        }

        $this->validate($rules);

        /*
        |--------------------------------------------------------------------------
        | Generate nomor surat
        |--------------------------------------------------------------------------
        */

        $date = \Carbon\Carbon::parse($this->tanggalSurat);

        $number = app(OutgoingLetterNumberService::class)
            ->generate($letterType->code, $date);

        /*
        |--------------------------------------------------------------------------
        | SURAT MANUAL
        |--------------------------------------------------------------------------
        |
        | Surat sudah dibuat di luar sistem.
        | Sistem hanya booking nomor + mencatat metadata.
        |
        */

        if (!$this->useTemplate) {
            OutgoingLetter::create([
                'letter_type_id' => $letterType->id,

                'nomor_urut' => $number['nomor_urut'],
                'nomor_tahun' => $number['nomor_tahun'],
                'nomor_surat' => $number['nomor_surat'],

                'jenis_surat' => $letterType->name,

                'use_template' => false,

                'tujuan' => $this->tujuan,
                'tanggal_surat' => $this->tanggalSurat,
                'perihal' => $this->perihal,

                'data' => null,
                'isi' => null,

                'keterangan' => $this->keterangan ?: null,

                // Surat manual dianggap sudah terbit.
                'status' => 'issued',

                'created_by' => Auth::id(),
            ]);

            $this->closeForm();

            session()->flash(
                'success',
                "Nomor surat berhasil dibooking: {$number['nomor_surat']}. Silakan upload dokumen surat melalui halaman detail."
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SURAT TEMPLATE
        |--------------------------------------------------------------------------
        */

        OutgoingLetter::create([
            'letter_type_id' => $letterType->id,

            'nomor_urut' => $number['nomor_urut'],
            'nomor_tahun' => $number['nomor_tahun'],
            'nomor_surat' => $number['nomor_surat'],

            'jenis_surat' => $letterType->name,

            'use_template' => true,

            'tujuan' => $this->tujuan,
            'tanggal_surat' => $this->tanggalSurat,
            'perihal' => $this->perihal,

            'data' => $this->formData,

            'isi' => $this->isi ?: null,
            'keterangan' => $this->keterangan ?: null,

            'status' => 'draft',

            'created_by' => Auth::id(),
        ]);

        $this->closeForm();

        session()->flash(
            'success',
            "Surat berhasil dibuat dengan nomor {$number['nomor_surat']}."
        );
    }

    public function submitForApproval(int $id): void
    {
        $letter = OutgoingLetter::findOrFail($id);

        if ($letter->status !== 'draft') {
            return;
        }

        $letter->update([
            'status' => 'pending',
            'rejection_reason' => null,
        ]);

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Surat berhasil diajukan untuk persetujuan Kepala Madrasah.'
        );
    }

};
?>

<div class="space-y-6">

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
                                                                                                                                                                                                                                                                                                                                                                                                        'pending' => 'yellow',
                                                                                                                                                                                                                                                                                                                                                                                                        'issued' => 'green',
                                                                                                                                                                                                                                                                                                                                                                                                        'cancelled' => 'red',
                                                                                                                                                                                                                                                                                                                                                                                                        default => 'zinc',
                                                                                                                                                                                                                                                                                                                                                                                                    }">
                                                        {{ match ($letter->status) {
                            'draft' => $letter->rejection_reason
                                ? 'Perlu Revisi'
                                : 'Draft',
                            'pending' => 'Menunggu Persetujuan',
                            'issued' => 'Terbit',
                            'cancelled' => 'Dibatalkan',
                            default => ucfirst($letter->status),
                        } }}
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

                                                <flux:button href="{{ route('admin.outgoing-letters.show', $letter) }}" variant="ghost"
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

        <flux:modal wire:model.self="showForm" class="md:w-[700px]">

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

                    {{-- Jenis Surat --}}
                    <flux:select wire:model.live="letterTypeIdForm" label="Jenis Surat">
                        <option value="">
                            Pilih jenis surat
                        </option>

                        @foreach ($letterTypes as $type)
                            <option value="{{ $type->id }}">
                                {{ $type->name }} — Kode {{ $type->code }}
                            </option>
                        @endforeach
                    </flux:select>

                    <flux:error name="letterTypeIdForm" />

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
                                    @if (($field['type'] ?? 'text') === 'text')

                                        <flux:input wire:model="formData.{{ $field['name'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}" />

                                        {{-- Textarea --}}
                                    @elseif ($field['type'] === 'textarea')

                                        <flux:textarea wire:model="formData.{{ $field['name'] }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}" />

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
                    <flux:input wire:model="tujuan" label="Tujuan" placeholder="Contoh: Orang Tua/Wali Siswa" />

                    <flux:error name="tujuan" />

                    {{-- Tanggal --}}
                    <flux:input wire:model="tanggalSurat" type="date" label="Tanggal Surat" />

                    <flux:error name="tanggalSurat" />

                    {{-- Perihal --}}
                    <flux:input wire:model="perihal" label="Perihal" placeholder="Masukkan perihal surat" />

                    <flux:error name="perihal" />

                    {{-- Isi Surat - hanya Template --}}
                    @if ($useTemplate)
                        <flux:textarea wire:model="isi" label="Isi Surat" rows="6" placeholder="Isi surat..." />

                        <flux:error name="isi" />
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

                    <flux:button wire:click="save" variant="primary">
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
