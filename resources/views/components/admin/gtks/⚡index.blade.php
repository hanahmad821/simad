<?php

use App\Models\Gtk;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $full_name = '';
    public string $nip = '';
    public string $nuptk = '';
    public string $gender = '';
    public string $birth_place = '';
    public string $birth_date = '';
    public string $employment_status = '';
    public string $employee_number = '';
    public string $position = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $gtk = Gtk::findOrFail($id);

        $this->editingId = $gtk->id;

        $this->full_name = $gtk->full_name ?? '';
        $this->nip = $gtk->nip ?? '';
        $this->nuptk = $gtk->nuptk ?? '';
        $this->gender = $gtk->gender ?? '';
        $this->birth_place = $gtk->birth_place ?? '';
        $this->birth_date = $gtk->birth_date?->format('Y-m-d') ?? '';
        $this->employment_status = $gtk->employment_status ?? '';
        $this->employee_number = $gtk->employee_number ?? '';
        $this->position = $gtk->position ?? '';
        $this->phone = $gtk->phone ?? '';
        $this->email = $gtk->email ?? '';
        $this->address = $gtk->address ?? '';
        $this->is_active = (bool) $gtk->is_active;

        $this->resetValidation();

        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:150'],

            'nip' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('gtks', 'nip')->ignore($this->editingId),
            ],

            'nuptk' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('gtks', 'nuptk')->ignore($this->editingId),
            ],

            'gender' => [
                'required',
                Rule::in(['male', 'female']),
            ],

            'birth_place' => ['nullable', 'string', 'max:100'],

            'birth_date' => ['nullable', 'date'],

            'employment_status' => [
                'required',
                Rule::in(['pns', 'non_pns', 'yayasan']),
            ],

            'employee_number' => ['nullable', 'string', 'max:50'],

            'position' => ['nullable', 'string', 'max:100'],

            'phone' => ['nullable', 'string', 'max:30'],

            'email' => ['nullable', 'email', 'max:150'],

            'address' => ['nullable', 'string'],

            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $gtk = Gtk::findOrFail($this->editingId);

            $gtk->update($validated);

            session()->flash('success', 'Data GTK berhasil diperbarui.');
        } else {
            Gtk::create($validated);

            session()->flash('success', 'Data GTK berhasil ditambahkan.');
        }

        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $gtk = Gtk::findOrFail($id);

        $gtk->delete();

        session()->flash('success', 'Data GTK berhasil dihapus.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;

        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'full_name',
            'nip',
            'nuptk',
            'gender',
            'birth_place',
            'birth_date',
            'employment_status',
            'employee_number',
            'position',
            'phone',
            'email',
            'address',
        ]);

        $this->is_active = true;

        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'gtks' => Gtk::query()
                ->when($this->search, function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('full_name', 'like', '%' . $this->search . '%')
                            ->orWhere('nip', 'like', '%' . $this->search . '%')
                            ->orWhere('nuptk', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('full_name')
                ->paginate(10),
        ];
    }
};
?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                Data GTK
            </flux:heading>

            <flux:text class="mt-1">
                Kelola data Guru dan Tenaga Kependidikan.
            </flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            Tambah GTK
        </flux:button>
    </div>


    {{-- Flash Message --}}
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif


    {{-- Table Card --}}
    <flux:card>

        {{-- Search --}}
        <div class="mb-5 max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIP, atau NUPTK..."
                icon="magnifying-glass" />
        </div>


        {{-- Table --}}
        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">

                        <th class="px-4 py-3 text-left">
                            No
                        </th>

                        <th class="px-4 py-3 text-left">
                            Nama
                        </th>

                        <th class="px-4 py-3 text-left">
                            NIP / NUPTK
                        </th>

                        <th class="px-4 py-3 text-left">
                            Status Kepegawaian
                        </th>

                        <th class="px-4 py-3 text-left">
                            Jabatan
                        </th>

                        <th class="px-4 py-3 text-center">
                            Status
                        </th>

                        <th class="px-4 py-3 text-right">
                            Aksi
                        </th>

                    </tr>
                </thead>

                <tbody>

                    @forelse ($gtks as $gtk)

                        <tr wire:key="gtk-{{ $gtk->id }}" class="border-b border-zinc-100 dark:border-zinc-800">

                            {{-- No --}}
                            <td class="px-4 py-3">
                                {{ $gtks->firstItem() + $loop->index }}
                            </td>


                            {{-- Nama --}}
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $gtk->full_name }}
                                </div>

                                @if ($gtk->gender)
                                    <div class="text-xs text-zinc-500">
                                        {{ $gtk->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                                    </div>
                                @endif
                            </td>


                            {{-- NIP / NUPTK --}}
                            <td class="px-4 py-3">

                                @if ($gtk->nip)
                                    <div>
                                        {{ $gtk->nip }}
                                    </div>
                                @endif

                                @if ($gtk->nuptk)
                                    <div class="text-xs text-zinc-500">
                                        NUPTK: {{ $gtk->nuptk }}
                                    </div>
                                @endif

                                @if (!$gtk->nip && !$gtk->nuptk)
                                    -
                                @endif

                            </td>


                            {{-- Status Kepegawaian --}}
                            <td class="px-4 py-3">
                                @if ($gtk->employment_status === 'pns')
                                    PNS
                                @elseif ($gtk->employment_status === 'non_pns')
                                    Non-PNS
                                @elseif ($gtk->employment_status === 'yayasan')
                                    Yayasan
                                @else
                                    -
                                @endif
                            </td>


                            {{-- Jabatan --}}
                            <td class="px-4 py-3">
                                {{ $gtk->position ?: '-' }}
                            </td>


                            {{-- Status --}}
                            <td class="px-4 py-3 text-center">

                                @if ($gtk->is_active)

                                    <flux:badge color="green">
                                        Aktif
                                    </flux:badge>

                                @else

                                    <flux:badge color="zinc">
                                        Tidak Aktif
                                    </flux:badge>

                                @endif

                            </td>


                            {{-- Action --}}
                            <td class="px-4 py-3">

                                <div class="flex justify-end gap-2">

                                    <flux:button size="sm" icon="eye" :href="route('admin.gtks.show', $gtk)" wire:navigate>
                                        Detail
                                    </flux:button>

                                    <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $gtk->id }})">
                                        Edit
                                    </flux:button>

                                    <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $gtk->id }})"
                                        wire:confirm="Yakin ingin menghapus data {{ $gtk->full_name }}?">
                                        Hapus
                                    </flux:button>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="7" class="px-4 py-10 text-center">
                                <flux:text>
                                    @if ($search)
                                        Data GTK tidak ditemukan.
                                    @else
                                        Belum ada data GTK.
                                    @endif
                                </flux:text>
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- Pagination --}}
        @if ($gtks->hasPages())
            <div class="mt-5">
                {{ $gtks->links() }}
            </div>
        @endif

    </flux:card>


    {{-- Form --}}
    <flux:modal wire:model="showForm" class="md:w-[700px]">

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Edit Data GTK' : 'Tambah GTK' }}
                </flux:heading>

                <flux:text class="mt-1">
                    Lengkapi data Guru atau Tenaga Kependidikan.
                </flux:text>
            </div>


            {{-- Data Utama --}}
            <div class="space-y-4">

                <flux:heading size="sm">
                    Data Utama
                </flux:heading>


                <flux:input label="Nama Lengkap" wire:model="full_name" placeholder="Nama lengkap" required />


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:input label="NIP" wire:model="nip" placeholder="NIP" />

                    <flux:input label="NUPTK" wire:model="nuptk" placeholder="NUPTK" />

                </div>


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:select label="Jenis Kelamin" wire:model="gender">
                        <flux:select.option value="">
                            Pilih jenis kelamin
                        </flux:select.option>

                        <flux:select.option value="male">
                            Laki-laki
                        </flux:select.option>

                        <flux:select.option value="female">
                            Perempuan
                        </flux:select.option>
                    </flux:select>


                    <flux:input type="date" label="Tanggal Lahir" wire:model="birth_date" />

                </div>


                <flux:input label="Tempat Lahir" wire:model="birth_place" placeholder="Tempat lahir" />

            </div>


            {{-- Kepegawaian --}}
            <div class="space-y-4">

                <flux:heading size="sm">
                    Data Kepegawaian
                </flux:heading>


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:select label="Status Kepegawaian" wire:model="employment_status">
                        <flux:select.option value="">
                            Pilih status
                        </flux:select.option>

                        <flux:select.option value="pns">
                            PNS
                        </flux:select.option>

                        <flux:select.option value="non_pns">
                            Non-PNS
                        </flux:select.option>

                        <flux:select.option value="yayasan">
                            Yayasan
                        </flux:select.option>
                    </flux:select>


                    <flux:input label="Nomor Pegawai" wire:model="employee_number" placeholder="Nomor pegawai" />

                </div>


                <flux:input label="Jabatan" wire:model="position" placeholder="Contoh: Guru Mata Pelajaran" />

            </div>


            {{-- Kontak --}}
            <div class="space-y-4">

                <flux:heading size="sm">
                    Kontak
                </flux:heading>


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:input label="Nomor HP" wire:model="phone" placeholder="08xxxxxxxxxx" />

                    <flux:input type="email" label="Email" wire:model="email" placeholder="email@example.com" />

                </div>


                <flux:textarea label="Alamat" wire:model="address" placeholder="Alamat lengkap" rows="3" />

            </div>


            {{-- Status --}}
            <flux:checkbox wire:model="is_active" label="GTK masih aktif" />


            {{-- Footer --}}
            <div class="flex justify-end gap-2">

                <flux:button wire:click="closeForm" variant="ghost">
                    Batal
                </flux:button>

                <flux:button wire:click="save" variant="primary">
                    {{ $editingId ? 'Simpan Perubahan' : 'Simpan' }}
                </flux:button>

            </div>

        </div>

    </flux:modal>

</div>
