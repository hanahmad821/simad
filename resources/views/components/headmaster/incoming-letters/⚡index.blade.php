<?php

use App\Models\IncomingLetter;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new class extends \Livewire\Component {
    use WithPagination;

    public string $search = '';
    public string $status = 'waiting';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function render()
    {
        abort_unless(Auth::user()?->isHeadmaster(), 403);
        $letters = IncomingLetter::query()
            ->withCount(['dispositions', 'dispositions as completed_dispositions_count' => fn ($query) => $query->where('status', 'completed')])
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query
                ->where('nomor_surat', 'like', '%'.$this->search.'%')
                ->orWhere('pengirim', 'like', '%'.$this->search.'%')
                ->orWhere('perihal', 'like', '%'.$this->search.'%')))
            ->when($this->status === 'waiting', fn ($query) => $query->doesntHave('dispositions'))
            ->when($this->status === 'ongoing', fn ($query) => $query->whereHas('dispositions', fn ($query) => $query->where('status', '!=', 'completed')))
            ->when($this->status === 'completed', fn ($query) => $query->has('dispositions')->whereDoesntHave('dispositions', fn ($query) => $query->where('status', '!=', 'completed')))
            ->orderByDesc('tanggal_masuk')->orderByDesc('id')->paginate(15);

        return $this->view(['letters' => $letters]);
    }
};
?>

<div class="space-y-6" wire:poll.30s>
    <div>
        <flux:heading size="xl">Surat Masuk dan Disposisi</flux:heading>
        <flux:text class="mt-1">Surat yang dicatat admin tersedia di sini untuk diperiksa dan didisposisikan.</flux:text>
    </div>
    <flux:card>
        <div class="grid gap-4 md:grid-cols-3">
            <flux:input class="md:col-span-2" wire:model.live.debounce.300ms="search" label="Pencarian" icon="magnifying-glass" placeholder="Nomor surat, pengirim, atau perihal..." />
            <flux:select wire:model.live="status" label="Status disposisi">
                <flux:select.option value="waiting">Menunggu disposisi</flux:select.option>
                <flux:select.option value="ongoing">Dalam tindak lanjut</flux:select.option>
                <flux:select.option value="completed">Selesai</flux:select.option>
                <flux:select.option value="">Semua surat</flux:select.option>
            </flux:select>
        </div>
    </flux:card>
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800"><tr>
                    <th class="px-4 py-3 text-left">Surat</th>
                    <th class="px-4 py-3 text-left">Pengirim</th>
                    <th class="px-4 py-3 text-left">Tanggal Masuk</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($letters as $letter)
                        <tr wire:key="incoming-{{ $letter->id }}">
                            <td class="px-4 py-4"><div class="font-medium">{{ $letter->nomor_surat }}</div><div class="mt-1 text-zinc-500">{{ $letter->perihal }}</div></td>
                            <td class="px-4 py-4">{{ $letter->pengirim }}</td>
                            <td class="whitespace-nowrap px-4 py-4">{{ $letter->tanggal_masuk?->format('d/m/Y') }}</td>
                            <td class="px-4 py-4"><x-incoming-letter-disposition-status :letter="$letter" /></td>
                            <td class="px-4 py-4 text-right"><flux:button size="sm" :href="route('headmaster.incoming-letters.dispositions', $letter)" icon="eye">{{ $letter->dispositions_count ? 'Lihat Disposisi' : 'Periksa & Disposisi' }}</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">Tidak ada surat pada pencarian atau status ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($letters->hasPages())<div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $letters->links() }}</div>@endif
    </flux:card>
</div>
