<?php

use App\Models\Holiday;
use Livewire\WithPagination;

new class extends \Livewire\Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $holidayId = null;

    public string $date = '';

    public string $name = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
                'unique:holidays,date,' . ($this->holidayId ?? 'NULL') . ',id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();

        $this->date = now()->format('Y-m-d');

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $holiday = Holiday::findOrFail($id);

        $this->holidayId = $holiday->id;
        $this->date = $holiday->date->format('Y-m-d');
        $this->name = $holiday->name;
        $this->is_active = $holiday->is_active;

        $this->resetValidation();

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        Holiday::updateOrCreate(
            ['id' => $this->holidayId],
            [
                'date' => $this->date,
                'name' => $this->name,
                'is_active' => $this->is_active,
            ]
        );

        session()->flash(
            'success',
            $this->holidayId
                ? 'Hari libur berhasil diperbarui.'
                : 'Hari libur berhasil ditambahkan.'
        );

        $this->closeForm();
    }

    public function delete(int $id): void
    {
        Holiday::findOrFail($id)->delete();

        session()->flash('success', 'Hari libur berhasil dihapus.');
    }

    public function toggleStatus(int $id): void
    {
        $holiday = Holiday::findOrFail($id);

        $holiday->update([
            'is_active' => ! $holiday->is_active,
        ]);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'holidayId',
            'date',
            'name',
            'is_active',
        ]);

        $this->is_active = true;

        $this->resetValidation();
    }

    public function render()
    {
        $holidays = Holiday::query()
            ->when(
                $this->search,
                fn ($query) => $query->where('name', 'like', "%{$this->search}%")
            )
            ->orderByDesc('date')
            ->paginate(10);

        return $this->view([
            'holidays' => $holidays,
        ]);
    }
}; ?>

<div class="space-y-6">

    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                Hari Libur
            </flux:heading>

            <flux:subheading>
                Kelola hari libur khusus madrasah.
            </flux:subheading>
        </div>

        <flux:button
            variant="primary"
            icon="plus"
            wire:click="create"
        >
            Tambah Hari Libur
        </flux:button>
    </div>

    @if (session('success'))
        <flux:callout
            variant="success"
            icon="check-circle"
        >
            {{ session('success') }}
        </flux:callout>
    @endif

    <div class="flex items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Cari hari libur..."
            icon="magnifying-glass"
            clearable
            class="w-full sm:w-80"
        />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Keterangan</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($holidays as $holiday)
                        <tr wire:key="holiday-{{ $holiday->id }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $holiday->date->translatedFormat('d F Y') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $holiday->name }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                <flux:badge
                                    :color="$holiday->is_active ? 'green' : 'zinc'"
                                >
                                    {{ $holiday->is_active ? 'Aktif' : 'Nonaktif' }}
                                </flux:badge>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil"
                                        wire:click="edit({{ $holiday->id }})"
                                    />

                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="power"
                                        wire:click="toggleStatus({{ $holiday->id }})"
                                    />

                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="delete({{ $holiday->id }})"
                                        wire:confirm="Yakin ingin menghapus hari libur ini?"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-zinc-500"
                            >
                                Belum ada data hari libur.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $holidays->links() }}


    {{-- Form --}}
    <flux:modal
        wire:model="showForm"
        name="holiday-form"
    >
        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $holidayId ? 'Edit Hari Libur' : 'Tambah Hari Libur' }}
                </flux:heading>

                <flux:subheading>
                    Masukkan tanggal dan keterangan hari libur.
                </flux:subheading>
            </div>

            <flux:input
                type="date"
                label="Tanggal"
                wire:model="date"
                required
            />

            <flux:input
                label="Keterangan"
                placeholder="Contoh: HUT Kemerdekaan RI"
                wire:model="name"
                required
            />

            <flux:switch
                label="Aktif"
                description="Hari libur aktif akan digunakan dalam kalender presensi."
                wire:model="is_active"
            />

            <div class="flex justify-end gap-2">
                <flux:button
                    variant="ghost"
                    wire:click="closeForm"
                >
                    Batal
                </flux:button>

                <flux:button
                    variant="primary"
                    wire:click="save"
                >
                    Simpan
                </flux:button>
            </div>

        </div>
    </flux:modal>

</div>
