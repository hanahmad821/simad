<?php

use App\Models\IncomingLetter;
use App\Models\LetterDisposition;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;

new class extends \Livewire\Component {
    public IncomingLetter $incomingLetter;

    public bool $showModal = false;

    #[Validate('required|exists:users,id')]
    public string $toUserId = '';

    #[Validate('required|string|max:5000')]
    public string $instruction = '';

    #[Validate('nullable|date')]
    public string $deadline = '';

    public function mount(IncomingLetter $incomingLetter): void
    {
        $this->incomingLetter = $incomingLetter;
    }

    public function openModal(): void
    {
        $this->reset([
            'toUserId',
            'instruction',
            'deadline',
        ]);

        $this->resetValidation();

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->validate();

        LetterDisposition::create([
            'incoming_letter_id' => $this->incomingLetter->id,
            'from_user_id' => Auth::id(),
            'to_user_id' => $this->toUserId,
            'instruction' => $this->instruction,
            'deadline' => $this->deadline ?: null,
            'status' => 'pending',
        ]);

        $this->closeModal();

        session()->flash(
            'success',
            'Disposisi berhasil dikirim.'
        );
    }

    public function render()
    {
        $dispositions = $this->incomingLetter
            ->dispositions()
            ->with([
                'fromUser.gtk',
                'toUser.gtk',
            ])
            ->latest()
            ->get();

        $users = User::query()
            ->whereIn('role', ['teacher', 'staff', 'headmaster', 'admin'])
            ->with('gtk')
            ->whereHas('gtk', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('name')
            ->get();

        return $this->view([
            'dispositions' => $dispositions,
            'users' => $users,
        ]);
    }
};
?>

<div>
    {{-- If you do not have a consistent goal in life, you can not live it in a consistent way. - Marcus Aurelius --}}
    <div class="space-y-6">

        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">
                    Disposisi Surat
                </flux:heading>

                <flux:text class="mt-1">
                    {{ $incomingLetter->nomor_surat }}
                </flux:text>
            </div>

            <flux:button wire:click="openModal" variant="primary" icon="paper-airplane">
                Buat Disposisi
            </flux:button>
        </div>
        <flux:card>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <flux:text class="text-sm text-zinc-500">
                        Nomor Surat
                    </flux:text>

                    <div class="font-medium">
                        {{ $incomingLetter->nomor_surat }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm text-zinc-500">
                        Pengirim
                    </flux:text>

                    <div class="font-medium">
                        {{ $incomingLetter->pengirim }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm text-zinc-500">
                        Tanggal Surat
                    </flux:text>

                    <div class="font-medium">
                        {{ $incomingLetter->tanggal_surat->format('d/m/Y') }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm text-zinc-500">
                        Perihal
                    </flux:text>

                    <div class="font-medium">
                        {{ $incomingLetter->perihal }}
                    </div>
                </div>
            </div>
        </flux:card>
        <flux:card>
            <flux:heading size="lg">
                Riwayat Disposisi
            </flux:heading>

            <div class="mt-6 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($dispositions as $disposition)
                                <div class="py-5 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="font-medium">
                                                {{ $disposition->toUser->name }}
                                            </div>

                                            <div class="text-sm text-zinc-500">
                                                dari {{ $disposition->fromUser->name }}
                                            </div>
                                        </div>

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

                                    <div class="mt-3">
                                        <div class="text-sm font-medium">
                                            Instruksi
                                        </div>

                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $disposition->instruction }}
                                        </div>
                                    </div>

                                    @if ($disposition->deadline)
                                        <div class="mt-3 text-sm text-zinc-500">
                                            Deadline:
                                            {{ $disposition->deadline->format('d/m/Y') }}
                                        </div>
                                    @endif

                                    @if ($disposition->notes)
                                        <div class="mt-3 text-sm text-zinc-500">
                                            Catatan:
                                            {{ $disposition->notes }}
                                        </div>
                                    @endif
                                </div>
                @empty
                    <div class="py-8 text-center text-sm text-zinc-500">
                        Belum ada disposisi.
                    </div>
                @endforelse
            </div>
        </flux:card>
        <flux:modal wire:model="showModal" class="md:w-[500px]">
            <div class="space-y-6">

                <div>
                    <flux:heading size="lg">
                        Buat Disposisi
                    </flux:heading>

                    <flux:text class="mt-1">
                        Teruskan surat kepada GTK terkait.
                    </flux:text>
                </div>

                <flux:select wire:model="toUserId" label="Penerima" placeholder="Pilih penerima">
                    @foreach ($users as $user)
                        <flux:select.option value="{{ $user->id }}">
                            {{ $user->name }}
                            @if ($user->gtk?->position)
                                — {{ $user->gtk->position }}
                            @endif
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea wire:model="instruction" label="Instruksi" placeholder="Tuliskan instruksi disposisi..."
                    rows="4" />

                <flux:input type="date" wire:model="deadline" label="Deadline" />

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">
                        Batal
                    </flux:button>

                    <flux:button wire:click="save" variant="primary">
                        Kirim Disposisi
                    </flux:button>
                </div>

            </div>
        </flux:modal>

    </div>
</div>
