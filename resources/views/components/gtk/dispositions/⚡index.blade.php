<?php

use App\Models\LetterDisposition;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new class extends \Livewire\Component {
    use WithPagination;

    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->status = '';
        $this->resetPage();
    }

    public function render()
    {
        $dispositions = LetterDisposition::query()
            ->with([
                'incomingLetter',
                'fromUser.gtk',
            ])
            ->where('to_user_id', Auth::id())
            ->when(
                $this->status,
                fn($query) => $query->where('status', $this->status)
            )
            ->latest()
            ->paginate(15);

        return $this->view([
            'dispositions' => $dispositions,
        ]);
    }
};
?>

<div>
    <div class="space-y-6">

        <div>
            <flux:heading size="xl">
                Disposisi Saya
            </flux:heading>

            <flux:text class="mt-1">
                Surat masuk yang perlu ditindaklanjuti.
            </flux:text>
        </div>

        <flux:card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

                <flux:select wire:model.live="status" label="Status" class="sm:w-56">
                    <flux:select.option value="">
                        Semua Status
                    </flux:select.option>

                    <flux:select.option value="pending">
                        Menunggu
                    </flux:select.option>

                    <flux:select.option value="read">
                        Dibaca
                    </flux:select.option>

                    <flux:select.option value="in_progress">
                        Diproses
                    </flux:select.option>

                    <flux:select.option value="completed">
                        Selesai
                    </flux:select.option>
                </flux:select>

                @if ($status)
                    <flux:button wire:click="resetFilters" variant="ghost">
                        Reset
                    </flux:button>
                @endif

            </div>
        </flux:card>

        <flux:card class="overflow-hidden p-0">

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                Surat
                            </th>

                            <th class="px-4 py-3 text-left font-medium">
                                Dari
                            </th>

                            <th class="px-4 py-3 text-left font-medium">
                                Instruksi
                            </th>

                            <th class="px-4 py-3 text-left font-medium">
                                Deadline
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

                        @forelse ($dispositions as $disposition)

                                                <tr wire:key="disposition-{{ $disposition->id }}">

                                                    <td class="px-4 py-4">
                                                        <div class="font-medium">
                                                            {{ $disposition->incomingLetter->nomor_surat }}
                                                        </div>

                                                        <div class="mt-1 text-xs text-zinc-500">
                                                            {{ $disposition->incomingLetter->perihal }}
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-4">
                                                        {{ $disposition->fromUser->name }}
                                                    </td>

                                                    <td class="max-w-sm px-4 py-4">
                                                        <div class="line-clamp-2">
                                                            {{ $disposition->instruction }}
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        @if ($disposition->deadline)
                                                            {{ $disposition->deadline->format('d/m/Y') }}
                                                        @else
                                                            <span class="text-zinc-400">
                                                                —
                                                            </span>
                                                        @endif
                                                    </td>

                                                    <td class="px-4 py-4">
                                                        <flux:badge :color="match($disposition->status) {
                                                                                                                'pending' => 'yellow',
                                                                                                                'read' => 'blue',
                                                                                                                'in_progress' => 'indigo',
                                                                                                                'completed' => 'green',
                                                                                                                default => 'zinc',
                                                                                                            }">
                                                            {{ match ($disposition->status) {
                                'pending' => 'Menunggu',
                                'read' => 'Dibaca',
                                'in_progress' => 'Diproses',
                                'completed' => 'Selesai',
                                default => $disposition->status,
                            } }}
                                                        </flux:badge>
                                                    </td>

                                                    <td class="px-4 py-4 text-right">
                                                        <flux:button size="sm" variant="ghost" icon="eye"
                                                            :href="route('gtk.dispositions.show', $disposition)">
                                                            Lihat
                                                        </flux:button>
                                                    </td>

                                                </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-zinc-500">
                                    Belum ada disposisi.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>
                </table>
            </div>

            @if ($dispositions->hasPages())
                <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                    {{ $dispositions->links() }}
                </div>
            @endif

        </flux:card>

    </div>
</div>
