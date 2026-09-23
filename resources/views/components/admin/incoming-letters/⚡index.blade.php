<?php

use App\Models\IncomingLetter;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends \Livewire\Component {
    use WithFileUploads, WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $nomor_surat = '';

    public string $pengirim = '';

    public string $tanggal_surat = '';

    public string $tanggal_masuk = '';

    public string $perihal = '';

    public $file = null;

    public ?string $existingFile = null;

    public string $keterangan = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();

        $this->tanggal_surat = now()->format('Y-m-d');
        $this->tanggal_masuk = now()->format('Y-m-d');

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $letter = IncomingLetter::findOrFail($id);

        $this->editingId = $letter->id;
        $this->nomor_surat = $letter->nomor_surat;
        $this->pengirim = $letter->pengirim;
        $this->tanggal_surat = $letter->tanggal_surat?->format('Y-m-d') ?? '';
        $this->tanggal_masuk = $letter->tanggal_masuk?->format('Y-m-d') ?? '';
        $this->perihal = $letter->perihal;
        $this->existingFile = $letter->file;
        $this->keterangan = $letter->keterangan ?? '';

        $this->file = null;

        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'nomor_surat' => ['required', 'string', 'max:255'],
            'pengirim' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'tanggal_masuk' => ['required', 'date'],
            'perihal' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'file' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
        ];

        $this->validate($rules);

        $letter = $this->editingId
            ? IncomingLetter::findOrFail($this->editingId)
            : new IncomingLetter();

        $letter->nomor_surat = $this->nomor_surat;
        $letter->pengirim = $this->pengirim;
        $letter->tanggal_surat = $this->tanggal_surat;
        $letter->tanggal_masuk = $this->tanggal_masuk;
        $letter->perihal = $this->perihal;
        $letter->keterangan = $this->keterangan ?: null;

        if ($this->file) {
            if ($letter->file && Storage::disk('public')->exists($letter->file)) {
                Storage::disk('public')->delete($letter->file);
            }

            $letter->file = $this->file->store(
                'incoming-letters',
                'public'
            );
        }

        $letter->save();

        $this->closeModal();

        session()->flash(
            'success',
            $this->editingId
            ? 'Surat masuk berhasil diperbarui.'
            : 'Surat masuk berhasil ditambahkan.'
        );
    }

    public function delete(int $id): void
    {
        $letter = IncomingLetter::findOrFail($id);

        if (
            $letter->file &&
            Storage::disk('public')->exists($letter->file)
        ) {
            Storage::disk('public')->delete($letter->file);
        }

        $letter->delete();

        session()->flash(
            'success',
            'Surat masuk berhasil dihapus.'
        );
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'nomor_surat',
            'pengirim',
            'tanggal_surat',
            'tanggal_masuk',
            'perihal',
            'file',
            'existingFile',
            'keterangan',
        ]);

        $this->resetValidation();
    }

    public function render()
    {
        $letters = IncomingLetter::query()
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('nomor_surat', 'like', '%' . $this->search . '%')
                            ->orWhere('pengirim', 'like', '%' . $this->search . '%')
                            ->orWhere('perihal', 'like', '%' . $this->search . '%');
                    });
                }
            )
            ->orderByDesc('tanggal_masuk')
            ->paginate(15);

        return $this->view([
            'letters' => $letters,
        ]);
    }
};
?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">
                Surat Masuk
            </flux:heading>

            <flux:subheading>
                Kelola arsip surat yang diterima madrasah.
            </flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            Tambah Surat
        </flux:button>
    </div>


    {{-- Flash message --}}
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle" dismissible>
            {{ session('success') }}
        </flux:callout>
    @endif


    {{-- Search --}}
    <flux:card>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Cari nomor surat, pengirim, atau perihal..." />
    </flux:card>


    {{-- Table --}}
    <flux:card class="overflow-hidden p-0">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            No.
                        </th>

                        <th class="px-4 py-3 text-left">
                            Nomor Surat
                        </th>

                        <th class="px-4 py-3 text-left">
                            Pengirim
                        </th>

                        <th class="px-4 py-3 text-left">
                            Tanggal
                        </th>

                        <th class="px-4 py-3 text-left">
                            Perihal
                        </th>

                        <th class="px-4 py-3 text-center">
                            File
                        </th>

                        <th class="px-4 py-3 text-right">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">

                    @forelse ($letters as $letter)

                        <tr wire:key="letter-{{ $letter->id }}">

                            <td class="px-4 py-3">
                                {{ $letters->firstItem() + $loop->index }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $letter->nomor_surat }}
                                </div>

                                <div class="text-xs text-zinc-500">
                                    Surat masuk:
                                    {{ $letter->tanggal_masuk?->format('d/m/Y') }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                {{ $letter->pengirim }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $letter->tanggal_surat?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="max-w-sm">
                                    {{ $letter->perihal }}
                                </div>

                                @if ($letter->keterangan)
                                    <div class="mt-1 text-xs text-zinc-500">
                                        {{ $letter->keterangan }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if ($letter->file)
                                    <a href="{{ Storage::disk('public')->url($letter->file) }}" target="_blank"
                                        class="inline-flex">
                                        <flux:button size="sm" variant="ghost" icon="document">
                                            Lihat
                                        </flux:button>
                                    </a>
                                @else
                                    <span class="text-xs text-zinc-400">
                                        Tidak ada
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="arrow-right-circle"
                                        :href="route('admin.incoming-letters.dispositions',$letter)">
                                        Disposisi
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" icon="pencil"
                                        wire:click="edit({{ $letter->id }})">
                                        Edit
                                    </flux:button>

                                    <flux:button size="sm" variant="danger" icon="trash"
                                        wire:click="delete({{ $letter->id }})"
                                        wire:confirm="Yakin ingin menghapus surat ini?">
                                        Hapus
                                    </flux:button>

                                </div>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="text-zinc-500">
                                    Belum ada data surat masuk.
                                </div>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </flux:card>


    {{-- Pagination --}}
    {{ $letters->links() }}


    {{-- Modal --}}
    <flux:modal wire:model="showModal" class="md:w-[650px]">
        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Edit Surat Masuk' : 'Tambah Surat Masuk' }}
                </flux:heading>

                <flux:subheading>
                    Lengkapi data surat yang diterima madrasah.
                </flux:subheading>
            </div>


            <div class="grid gap-4 sm:grid-cols-2">

                <flux:input label="Nomor Surat" wire:model="nomor_surat" placeholder="Contoh: 001/ABC/2026" />

                <flux:input label="Pengirim" wire:model="pengirim" placeholder="Nama instansi / pengirim" />

                <flux:input type="date" label="Tanggal Surat" wire:model="tanggal_surat" />

                <flux:input type="date" label="Tanggal Masuk" wire:model="tanggal_masuk" />

            </div>


            <flux:input label="Perihal" wire:model="perihal" placeholder="Perihal surat" />


            <div class="space-y-2">
                <flux:label>
                    Scan Surat
                </flux:label>

                <input type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full rounded-lg border border-zinc-300 bg-white text-sm
               file:mr-4 file:border-0 file:bg-zinc-100 file:px-4 file:py-2
               file:text-sm file:font-medium
               hover:file:bg-zinc-200
               dark:border-zinc-600 dark:bg-zinc-800
               dark:file:bg-zinc-700 dark:file:text-zinc-100" />

                <flux:error name="file" />

                <div wire:loading wire:target="file" class="text-sm text-zinc-500">
                    Mengunggah file...
                </div>

                @if ($file)
                    <div class="text-sm text-green-600">
                        File siap diunggah:
                        <strong>{{ $file->getClientOriginalName() }}</strong>
                    </div>
                @endif

                <div class="text-xs text-zinc-500">
                    Format: PDF, JPG, JPEG, PNG. Maksimal 10 MB.
                </div>
            </div>

            @if ($existingFile)
                <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">

                    <div class="flex items-center justify-between gap-3">

                        <div class="flex items-center gap-2">
                            <flux:icon name="document" class="size-5 text-zinc-500" />

                            <span>
                                File surat tersimpan
                            </span>
                        </div>

                        <a href="{{ Storage::disk('public')->url($existingFile) }}" target="_blank">
                            <flux:button size="sm" variant="ghost">
                                Lihat
                            </flux:button>
                        </a>

                    </div>

                </div>
            @endif


            <flux:textarea label="Keterangan" wire:model="keterangan" placeholder="Catatan tambahan (opsional)"
                rows="3" />


            <div class="flex justify-end gap-2">

                <flux:button variant="ghost" wire:click="closeModal">
                    Batal
                </flux:button>

                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    {{ $editingId ? 'Simpan Perubahan' : 'Simpan' }}
                </flux:button>

            </div>

        </div>
    </flux:modal>

</div>
