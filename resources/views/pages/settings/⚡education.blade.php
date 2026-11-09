<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $level = '';
    public string $institution_name = '';
    public string $major = '';
    public string $start_year = '';
    public string $graduation_year = '';
    public string $degree = '';

    private function ownGtk(): \App\Models\Gtk
    {
        $gtk = Auth::user()->gtk;
        abort_unless($gtk, 403);

        return $gtk;
    }

    #[Computed]
    public function educations()
    {
        return $this->ownGtk()->educations()->orderByDesc('start_year')->orderByDesc('id')->get();
    }

    public function create(): void
    {
        $this->ownGtk();
        $this->cancel();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $education = $this->ownGtk()->educations()->findOrFail($id);
        $this->cancel();
        $this->editingId = $education->id;
        foreach (['level', 'institution_name', 'major', 'start_year', 'graduation_year', 'degree'] as $field) {
            $this->{$field} = (string) ($education->{$field} ?? '');
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        $gtk = $this->ownGtk();
        $education = $this->editingId ? $gtk->educations()->findOrFail($this->editingId) : null;
        $data = $this->validate([
            'level' => ['required', Rule::in(['SD/MI', 'SMP/MTs', 'SMA/MA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'])],
            'institution_name' => ['required', 'string', 'max:150'],
            'major' => ['nullable', 'string', 'max:150'],
            'start_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'graduation_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year,
                ...($this->start_year !== '' ? ['gte:start_year'] : [])],
            'degree' => ['nullable', 'string', 'max:100'],
        ]);
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        if ($education) {
            $education->update($data);
        } else {
            $gtk->educations()->create($data);
        }

        unset($this->educations);
        $this->cancel();
        Flux::toast(variant: 'success', text: 'Riwayat pendidikan berhasil disimpan.');
    }

    public function delete(int $id): void
    {
        $this->ownGtk()->educations()->findOrFail($id)->delete();
        unset($this->educations);
        if ($this->editingId === $id) {
            $this->cancel();
        }
        Flux::toast(variant: 'success', text: 'Riwayat pendidikan berhasil dihapus.');
    }

    public function cancel(): void
    {
        $this->reset('showForm', 'editingId', 'level', 'institution_name', 'major', 'start_year', 'graduation_year', 'degree');
        $this->resetValidation();
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="lg">Riwayat pendidikan</flux:heading>
        <flux:button wire:click="create">Tambah pendidikan</flux:button>
    </div>

    @forelse ($this->educations as $education)
        <div wire:key="own-education-{{ $education->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 space-y-2">
            <flux:heading>{{ $education->level }} — {{ $education->institution_name }}</flux:heading>
            <flux:text>{{ $education->major ?: 'Jurusan belum diisi' }} · {{ $education->degree ?: 'Gelar belum diisi' }}</flux:text>
            <flux:text>{{ $education->start_year ?: '?' }} – {{ $education->graduation_year ?: 'Sekarang' }}</flux:text>
            <div class="flex gap-2">
                <flux:button size="sm" wire:click="edit({{ $education->id }})">Edit</flux:button>
                <flux:button size="sm" variant="danger" wire:click="delete({{ $education->id }})" wire:confirm="Hapus riwayat pendidikan ini?">Hapus</flux:button>
            </div>
        </div>
    @empty
        <flux:text>Belum ada riwayat pendidikan.</flux:text>
    @endforelse

    @if ($showForm)
        <form wire:submit="save" class="space-y-6 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>{{ $editingId ? 'Edit pendidikan' : 'Tambah pendidikan' }}</flux:heading>
            <flux:select wire:model="level" label="Jenjang Pendidikan" required>
                <flux:select.option value="">Pilih jenjang</flux:select.option>
                <flux:select.option value="SD/MI">SD / MI</flux:select.option>
                <flux:select.option value="SMP/MTs">SMP / MTs</flux:select.option>
                <flux:select.option value="SMA/MA">SMA / MA</flux:select.option>
                <flux:select.option value="D1">D1</flux:select.option>
                <flux:select.option value="D2">D2</flux:select.option>
                <flux:select.option value="D3">D3</flux:select.option>
                <flux:select.option value="D4">D4</flux:select.option>
                <flux:select.option value="S1">S1</flux:select.option>
                <flux:select.option value="S2">S2</flux:select.option>
                <flux:select.option value="S3">S3</flux:select.option>
            </flux:select>
            <flux:input wire:model="institution_name" label="Nama institusi" required maxlength="150" />
            <flux:input wire:model="major" label="Jurusan" maxlength="150" />
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <flux:input wire:model="start_year" label="Tahun masuk" type="number" min="1900" :max="now()->year" />
                <flux:input wire:model="graduation_year" label="Tahun lulus (kosongkan jika masih belajar)" type="number" min="1900" :max="now()->year" />
            </div>
            <flux:input wire:model="degree" label="Gelar" maxlength="100" />
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Simpan pendidikan</flux:button>
                <flux:button type="button" wire:click="cancel">Batal</flux:button>
            </div>
        </form>
    @endif
</section>
