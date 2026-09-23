<?php

use App\Models\OutgoingLetter;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\Component;

new
    #[Title('Persetujuan Surat Keluar')]
    class extends Component {
    use WithPagination;

    public string $search = '';

    public function with(): array
    {
        return [
            'letters' => OutgoingLetter::query()
                ->with([
                    'letterType',
                    'creator',
                ])
                ->where('status', 'pending')
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
                Persetujuan Surat Keluar
            </flux:heading>

            <flux:text class="mt-1">
                Surat yang menunggu persetujuan Kepala Madrasah.
            </flux:text>
        </div>

        <flux:card>

            <div class="mb-4 max-w-md">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nomor, tujuan, atau perihal..."
                    icon="magnifying-glass" />
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
                                </td>

                                <td class="px-4 py-3">
                                    {{ $letter->creator?->name ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-right">

                                    <flux:button size="sm" href="{{ route('headmaster.outgoing-letters.show', $letter) }}"
                                        wire:navigate icon="eye">
                                        Review
                                    </flux:button>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-zinc-500">
                                    Tidak ada surat yang menunggu persetujuan.
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
