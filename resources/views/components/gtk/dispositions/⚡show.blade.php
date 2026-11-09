<?php

use App\Models\LetterDisposition;
use App\Services\LetterDispositionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

new class extends \Livewire\Component {
    #[Locked]
    public LetterDisposition $disposition;
    public string $notes = '';

    public function mount(LetterDisposition $disposition): void
    {
        abort_unless(Auth::check(), 403);
        app(LetterDispositionService::class)->markRead($disposition, Auth::user());
        $this->disposition = $disposition->load(['incomingLetter', 'fromUser.gtk', 'toUser.gtk']);
        $this->notes = $this->disposition->notes ?? '';
    }

    public function startProcessing(): void
    {
        abort_unless(Auth::check(), 403);
        app(LetterDispositionService::class)->start($this->disposition, Auth::user());
        session()->flash('success', 'Disposisi ditandai sedang diproses.');
    }

    public function complete(): void
    {
        abort_unless(Auth::check(), 403);
        app(LetterDispositionService::class)->complete($this->disposition, Auth::user(), $this->notes);
        session()->flash('success', 'Disposisi berhasil diselesaikan.');
    }

    public function render()
    {
        abort_unless($this->disposition->to_user_id === Auth::id(), 403);

        return $this->view();
    }
};
?>

<div>
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">

            <div>
                <flux:heading size="xl">
                    Detail Disposisi
                </flux:heading>

                <flux:text class="mt-1">
                    {{ $disposition->incomingLetter->nomor_surat }}
                </flux:text>
            </div>

            <flux:button href="{{ route('gtk.dispositions.index') }}" variant="ghost" icon="arrow-left">
                Kembali
            </flux:button>

        </div>


        @if (session()->has('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @error('status')<flux:callout variant="warning">{{ $message }}</flux:callout>@enderror

        {{-- Status --}}
        <flux:card>
            <div class="flex items-center justify-between">

                <div>
                    <div class="text-sm text-zinc-500">
                        Status Disposisi
                    </div>

                    <div class="mt-1">
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
                    </div>
                </div>

                @if ($disposition->status === 'read')
                    <flux:button wire:click="startProcessing" wire:loading.attr="disabled" wire:target="startProcessing" variant="primary">
                        Mulai Diproses
                    </flux:button>
                @elseif ($disposition->status === 'in_progress')
                    <flux:button wire:click="complete" wire:loading.attr="disabled" wire:target="complete" variant="primary">
                        Tandai Selesai
                    </flux:button>
                @endif

            </div>
        </flux:card>


        {{-- Informasi Surat --}}
        <flux:card>

            <flux:heading size="lg">
                Informasi Surat
            </flux:heading>

            <div class="mt-6 grid gap-5 md:grid-cols-2">

                <div>
                    <div class="text-sm text-zinc-500">
                        Nomor Surat
                    </div>

                    <div class="font-medium">
                        {{ $disposition->incomingLetter->nomor_surat }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">
                        Pengirim
                    </div>

                    <div class="font-medium">
                        {{ $disposition->incomingLetter->pengirim }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">
                        Tanggal Surat
                    </div>

                    <div class="font-medium">
                        {{ $disposition->incomingLetter->tanggal_surat->format('d/m/Y') }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">
                        Tanggal Masuk
                    </div>

                    <div class="font-medium">
                        {{ $disposition->incomingLetter->tanggal_masuk->format('d/m/Y') }}
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div class="text-sm text-zinc-500">
                        Perihal
                    </div>

                    <div class="font-medium">
                        {{ $disposition->incomingLetter->perihal }}
                    </div>
                </div>

                @if ($disposition->incomingLetter->file)
                    <div class="md:col-span-2">
                        <flux:button href="{{ Storage::disk('public')->url($disposition->incomingLetter->file) }}"
                            target="_blank" variant="ghost" icon="document-text">
                            Lihat Surat
                        </flux:button>
                    </div>
                @endif

            </div>

        </flux:card>


        {{-- Instruksi --}}
        <flux:card>

            <flux:heading size="lg">
                Instruksi Disposisi
            </flux:heading>

            <div class="mt-4 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                {{ $disposition->instruction }}
            </div>

            <div class="mt-5 grid gap-5 md:grid-cols-2">

                <div>
                    <div class="text-sm text-zinc-500">
                        Dari
                    </div>

                    <div class="font-medium">
                        {{ $disposition->fromUser->name }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-zinc-500">
                        Deadline
                    </div>

                    <div class="font-medium">
                        {{ $disposition->deadline?->format('d/m/Y') ?? 'Tidak ada' }}
                    </div>
                </div>

            </div>

        </flux:card>


        {{-- Catatan Penyelesaian --}}
        @if ($disposition->status === 'in_progress' || $disposition->status === 'completed')

            <flux:card>

                <flux:heading size="lg">
                    Catatan Penyelesaian
                </flux:heading>

                <flux:textarea wire:model="notes" label="Catatan hasil tindak lanjut" class="mt-4" placeholder="Tuliskan catatan hasil tindak lanjut..."
                    rows="5" :disabled="$disposition->status === 'completed'" />

                @if ($disposition->status === 'in_progress')
                    <div class="mt-4 flex justify-end">
                        <flux:button wire:click="complete" wire:loading.attr="disabled" wire:target="complete" variant="primary">
                            Tandai Selesai
                        </flux:button>
                    </div>
                @endif

            </flux:card>

        @endif

    </div>
</div>
