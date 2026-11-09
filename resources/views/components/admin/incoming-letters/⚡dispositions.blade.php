<?php

use App\Models\IncomingLetter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

new class extends \Livewire\Component {
    #[Locked]
    public IncomingLetter $incomingLetter;

    public function mount(IncomingLetter $incomingLetter): void
    {
        abort_unless(Auth::user()?->canManageLetters(), 403);
        $this->incomingLetter = $incomingLetter;
    }

    public function render()
    {
        abort_unless(Auth::user()?->canManageLetters(), 403);

        return $this->view([
            'dispositions' => $this->incomingLetter->dispositions()->with(['fromUser', 'toUser'])->latest()->get(),
        ]);
    }
};
?>

<div class="space-y-6" wire:poll.30s>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Pantau Disposisi Surat</flux:heading>
            <flux:text class="mt-1">Disposisi dibuat oleh Kepala Madrasah. Admin dan Staf dapat memantau perkembangan tindak lanjut.</flux:text>
        </div>
        <flux:button :href="route((auth()->user()->isStaff() ? 'staff.' : 'admin.').'incoming-letters.index')" icon="arrow-left">Kembali</flux:button>
    </div>
    <x-incoming-letter-disposition-history :letter="$incomingLetter" :dispositions="$dispositions" />
</div>
