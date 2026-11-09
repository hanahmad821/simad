<?php

use App\Models\OutgoingLetter;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\Component;

new
    #[Title('Surat Keluar')]
    class extends Component {
    use WithPagination;

    public string $search = '';

    public string $status = 'pending';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'letters' => OutgoingLetter::query()
                ->with([
                    'letterType',
                    'creator',
                ])
                ->whereIn('status', ['pending', 'awaiting_signature', 'issued'])
                ->when(in_array($this->status, ['pending', 'awaiting_signature', 'issued'], true), fn ($query) => $query->where('status', $this->status))
                ->when($this->search, function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('nomor_surat', 'like', '%' . $this->search . '%')
                            ->orWhere('tujuan', 'like', '%' . $this->search . '%')
                            ->orWhere('perihal', 'like', '%' . $this->search . '%');
                    });
                })
                ->latest()
                ->paginate(10),
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <div class="space-y-6">

        <div>
            <flux:heading size="xl">
                Surat Keluar
            </flux:heading>

            <flux:text class="mt-1">
                Tinjau pengajuan surat dan pantau surat yang sudah terbit.
            </flux:text>
        </div>

        <flux:card>

            <div class="mb-4 grid gap-4 md:grid-cols-3">
                <flux:input class="md:col-span-2" label="Pencarian" wire:model.live.debounce.300ms="search" placeholder="Cari nomor, tujuan, atau perihal..."
                    icon="magnifying-glass" />
                <flux:select wire:model.live="status" label="Status surat">
                    <flux:select.option value="pending">Menunggu Persetujuan</flux:select.option>
                    <flux:select.option value="awaiting_signature">Menunggu Tanda Tangan Basah</flux:select.option>
                    <flux:select.option value="issued">Sudah Terbit</flux:select.option>
                    <flux:select.option value="">Semua Pengajuan &amp; Terbit</flux:select.option>
                </flux:select>
            </div>

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
                                Tanggal
                            </th>

                            <th class="px-4 py-3 text-left font-medium">
                                Dibuat Oleh
                            </th>

                            <th class="px-4 py-3 text-left font-medium">Status</th>

                            <th class="px-4 py-3 text-right font-medium">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">

                        @forelse ($letters as $letter)

                            <tr wire:key="letter-{{ $letter->id }}">

                                <td class="px-4 py-3">
                                    <a href="{{ route('headmaster.outgoing-letters.show', $letter) }}" wire:navigate
                                        class="font-medium text-zinc-900 hover:text-accent dark:text-zinc-100">
                                        {{ $letter->nomor_surat }}
                                    </a>
                                </td>

                                <td class="px-4 py-3">
                                    {{ $letter->letterType?->name ?? $letter->jenis_surat }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $letter->tujuan }}
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $letter->tanggal_surat?->translatedFormat('d F Y') }}
                                    @if ($letter->status === 'issued' && $letter->approved_at)
                                        <div class="mt-1 text-xs text-zinc-500">Terbit: {{ $letter->approved_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    {{ $letter->creator?->name ?? '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    <flux:badge :color="$letter->status === 'issued' ? 'green' : 'yellow'">
                                        {{ $letter->statusLabel() }}
                                    </flux:badge>
                                </td>

                                <td class="px-4 py-3 text-right">

                                    <flux:button size="sm" href="{{ route('headmaster.outgoing-letters.show', $letter) }}"
                                        wire:navigate icon="eye">
                                        {{ $letter->status === 'pending' ? 'Review' : 'Detail' }}
                                    </flux:button>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-zinc-500">
                                    Tidak ada surat yang sesuai dengan pencarian dan status yang dipilih.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

            @if ($letters->hasPages())
                <div class="mt-4">
                    {{ $letters->links() }}
                </div>
            @endif

        </flux:card>

    </div>
</div>
