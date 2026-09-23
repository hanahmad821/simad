<?php

use App\Models\OutgoingLetter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;
new
#[Title('Review Surat Keluar')]
class extends Component
{
    public OutgoingLetter $outgoingLetter;

    public bool $showRejectForm = false;

    public string $rejectionReason = '';

    public function mount(OutgoingLetter $outgoingLetter): void
    {
        $this->outgoingLetter = $outgoingLetter->load([
            'letterType',
            'creator',
        ]);
    }

public function approve(): void
{
    if ($this->outgoingLetter->status !== 'pending') {
        return;
    }

    $this->outgoingLetter->update([
        'status' => 'issued',
        'approved_by' => Auth::id(),
        'approved_at' => now(),
        'rejection_reason' => null,
        'verification_token' => $this->outgoingLetter->verification_token
            ?? Str::random(64),
    ]);

    $this->outgoingLetter->refresh();

    $this->dispatch(
        'notify',
        type: 'success',
        message: 'Surat berhasil disetujui dan diterbitkan.'
    );
}

    public function openRejectForm(): void
    {
        $this->rejectionReason = '';
        $this->showRejectForm = true;
    }

    public function reject(): void
    {
        if ($this->outgoingLetter->status !== 'pending') {
            return;
        }

        $this->validate([
            'rejectionReason' => [
                'required',
                'string',
                'max:2000',
            ],
        ], [
            'rejectionReason.required' => 'Alasan pengembalian wajib diisi.',
        ]);

        $this->outgoingLetter->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $this->rejectionReason,
        ]);

        $this->redirectRoute('headmaster.outgoing-letters.index');
    }

    public function back(): void
    {
        $this->redirectRoute('headmaster.outgoing-letters.index');
    }
};
?>

<div>

    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">

            <div>
                <flux:heading size="xl">
                    Review Surat Keluar
                </flux:heading>

                <flux:text class="mt-1">
                    Periksa surat sebelum memberikan persetujuan.
                </flux:text>
            </div>

            <flux:button
                wire:click="back"
                icon="arrow-left"
            >
                Kembali
            </flux:button>

        </div>

        {{-- Status --}}
        @php
            $statusColor = match ($outgoingLetter->status) {
                'draft' => 'zinc',
                'pending' => 'yellow',
                'issued' => 'green',
                'cancelled' => 'red',
                default => 'zinc',
            };

            $statusLabel = match ($outgoingLetter->status) {
                'draft' => 'Draft',
                'pending' => 'Menunggu Persetujuan',
                'issued' => 'Terbit',
                'cancelled' => 'Dibatalkan',
                default => ucfirst($outgoingLetter->status),
            };
        @endphp

        <flux:card>

            <div class="flex items-center justify-between gap-4">

                <div>
                    <flux:heading size="lg">
                        {{ $outgoingLetter->letterType?->name ?? $outgoingLetter->jenis_surat }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ $outgoingLetter->nomor_surat }}
                    </flux:text>
                </div>

                <flux:badge :color="$statusColor">
                    {{ $statusLabel }}
                </flux:badge>

            </div>

        </flux:card>

        {{-- Informasi surat --}}
        <flux:card>

            <flux:heading size="lg">
                Informasi Surat
            </flux:heading>

            <div class="mt-5 grid gap-5 md:grid-cols-2">

                <div>
                    <flux:text class="text-sm">
                        Nomor Surat
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->nomor_surat }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm">
                        Jenis Surat
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->letterType?->name ?? $outgoingLetter->jenis_surat }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm">
                        Tanggal Surat
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->tanggal_surat?->translatedFormat('d F Y') }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm">
                        Tujuan
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->tujuan }}
                    </div>
                </div>

                <div class="md:col-span-2">
                    <flux:text class="text-sm">
                        Perihal
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->perihal }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm">
                        Dibuat Oleh
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->creator?->name ?? '-' }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm">
                        Dibuat Pada
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $outgoingLetter->created_at?->translatedFormat('d F Y H:i') }}
                    </div>
                </div>

            </div>

        </flux:card>

        {{-- Data dinamis --}}
        @if ($outgoingLetter->letterType?->fields)

            <flux:card>

                <flux:heading size="lg">
                    Data Surat
                </flux:heading>

                <div class="mt-5 space-y-5">

                    @foreach ($outgoingLetter->letterType->fields as $field)

                        @php
                            $name = $field['name'] ?? '';
                            $label = $field['label'] ?? $name;
                            $type = $field['type'] ?? 'text';
                            $value = data_get($outgoingLetter->data ?? [], $name);
                        @endphp

                        <div>

                            <flux:text class="text-sm">
                                {{ $label }}
                            </flux:text>

                            @if ($type === 'repeater' && is_array($value))

                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    @foreach ($value as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>

                            @elseif ($type === 'student' && $value)

                                @php
                                    $student = \App\Models\Student::find($value);
                                @endphp

                                <div class="mt-1 font-medium">
                                    {{ $student?->full_name ?? '-' }}
                                </div>

                            @else

                                <div class="mt-1 whitespace-pre-line font-medium">
                                    {{ $value ?: '-' }}
                                </div>

                            @endif

                        </div>

                    @endforeach

                </div>

            </flux:card>

        @endif

        {{-- Isi surat --}}
        @if ($outgoingLetter->isi)

            <flux:card>

                <flux:heading size="lg">
                    Isi Surat
                </flux:heading>

                <div class="mt-4 whitespace-pre-line text-sm leading-7">
                    {{ $outgoingLetter->isi }}
                </div>

            </flux:card>

        @endif

        {{-- Keterangan --}}
        @if ($outgoingLetter->keterangan)

            <flux:card>

                <flux:heading size="lg">
                    Keterangan
                </flux:heading>

                <div class="mt-4 whitespace-pre-line text-sm">
                    {{ $outgoingLetter->keterangan }}
                </div>

            </flux:card>

        @endif

        {{-- Alasan pengembalian sebelumnya --}}
        @if ($outgoingLetter->rejection_reason)

            <flux:card>

                <flux:heading size="lg">
                    Alasan Pengembalian
                </flux:heading>

                <flux:text class="mt-3 whitespace-pre-line">
                    {{ $outgoingLetter->rejection_reason }}
                </flux:text>

            </flux:card>

        @endif

        {{-- Action --}}
        @if ($outgoingLetter->status === 'pending')

            <flux:card>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>
                        <flux:heading size="lg">
                            Keputusan
                        </flux:heading>

                        <flux:text class="mt-1">
                            Surat dapat disetujui atau dikembalikan kepada pembuat untuk diperbaiki.
                        </flux:text>
                    </div>

                    <div class="flex gap-2">

                        <flux:button
                            variant="danger"
                            wire:click="openRejectForm"
                            icon="arrow-uturn-left"
                        >
                            Kembalikan
                        </flux:button>

                        <flux:button
                            variant="primary"
                            wire:click="approve"
                            wire:confirm="Apakah Anda yakin ingin menyetujui surat ini?"
                            icon="check"
                        >
                            Setujui
                        </flux:button>

                    </div>

                </div>

            </flux:card>

        @endif

        {{-- Form pengembalian --}}
        @if ($showRejectForm)

            <flux:modal wire:model="showRejectForm" name="reject-letter">

                <div class="space-y-6">

                    <div>
                        <flux:heading size="lg">
                            Kembalikan Surat
                        </flux:heading>

                        <flux:text class="mt-1">
                            Berikan alasan agar pembuat mengetahui bagian yang perlu diperbaiki.
                        </flux:text>
                    </div>

                    <flux:textarea
                        wire:model="rejectionReason"
                        label="Alasan Pengembalian"
                        placeholder="Contoh: Perihal surat perlu diperbaiki..."
                        rows="5"
                    />

                    <div class="flex justify-end gap-2">

                        <flux:button
                            type="button"
                            wire:click="$set('showRejectForm', false)"
                        >
                            Batal
                        </flux:button>

                        <flux:button
                            variant="danger"
                            wire:click="reject"
                        >
                            Kembalikan Surat
                        </flux:button>

                    </div>

                </div>

            </flux:modal>

        @endif

    </div>
</div>
