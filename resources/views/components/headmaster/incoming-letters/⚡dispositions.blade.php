<?php

use App\Models\IncomingLetter;
use App\Services\LetterDispositionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

new class extends \Livewire\Component {
    #[Locked]
    public IncomingLetter $incomingLetter;
    public bool $showModal = false;
    public string $toUserId = '';
    public string $instruction = '';
    public string $deadline = '';

    public function mount(IncomingLetter $incomingLetter): void
    {
        abort_unless(Auth::user()?->isHeadmaster(), 403);
        $this->incomingLetter = $incomingLetter;
    }

    public function openModal(): void
    {
        abort_unless(Auth::user()?->isHeadmaster(), 403);
        $this->reset('toUserId', 'instruction', 'deadline');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->isHeadmaster(), 403);
        app(LetterDispositionService::class)->send($this->incomingLetter, Auth::user(), [
            'toUserId' => $this->toUserId,
            'instruction' => $this->instruction,
            'deadline' => $this->deadline ?: null,
        ]);
        $this->closeModal();
        $this->reset('toUserId', 'instruction', 'deadline');
        session()->flash('success', 'Disposisi berhasil dikirim kepada penerima.');
    }

    public function render()
    {
        abort_unless(Auth::user()?->isHeadmaster(), 403);

        return $this->view([
            'dispositions' => $this->incomingLetter->dispositions()->with(['fromUser', 'toUser'])->latest()->get(),
            'users' => app(LetterDispositionService::class)->recipients()->with('gtk')->orderBy('name')->get(),
        ]);
    }
};
?>

<div class="space-y-6" wire:poll.30s>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Disposisi Surat Masuk</flux:heading>
            <flux:text class="mt-1">Periksa surat dari admin, lalu berikan instruksi tindak lanjut kepada guru atau staf.</flux:text>
        </div>
        <flux:button :href="route('headmaster.incoming-letters.index')" icon="arrow-left">Kembali</flux:button>
    </div>
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif
    <x-incoming-letter-disposition-history :letter="$incomingLetter" :dispositions="$dispositions" />
    <div class="flex justify-end">
        <flux:button wire:click="openModal" variant="primary" icon="paper-airplane">Buat Disposisi</flux:button>
    </div>
    <flux:modal wire:model="showModal" class="md:w-[500px]">
        <div class="space-y-6">
            <flux:heading size="lg">Buat Disposisi</flux:heading>
            <flux:text>Pilih guru atau staf dengan data GTK aktif.</flux:text>
            @if ($users->isEmpty())
                <flux:callout variant="warning">Belum ada penerima yang tersedia. Minta admin menghubungkan akun guru/staf dengan data GTK aktif.</flux:callout>
            @endif
            <flux:select wire:model="toUserId" label="Penerima" placeholder="Pilih penerima">
                @foreach ($users as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }}{{ $user->gtk?->position ? ' — '.$user->gtk->position : '' }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="instruction" label="Instruksi" placeholder="Tuliskan instruksi tindak lanjut..." rows="4" />
            <flux:input type="date" wire:model="deadline" label="Batas waktu (opsional)" :min="now()->format('Y-m-d')" />
            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">Batal</flux:button>
                <flux:button wire:click="save" wire:loading.attr="disabled" wire:target="save" :disabled="$users->isEmpty()" variant="primary">Kirim Disposisi</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
