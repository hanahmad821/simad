@props(['letter', 'dispositions'])

<flux:card>
    <flux:heading size="lg">Informasi Surat Masuk</flux:heading>
    <dl class="mt-4 grid gap-4 md:grid-cols-2">
        <div><dt class="text-sm text-zinc-500">Nomor Surat</dt><dd class="font-medium">{{ $letter->nomor_surat }}</dd></div>
        <div><dt class="text-sm text-zinc-500">Pengirim</dt><dd class="font-medium">{{ $letter->pengirim }}</dd></div>
        <div><dt class="text-sm text-zinc-500">Tanggal Surat</dt><dd>{{ $letter->tanggal_surat?->format('d/m/Y') }}</dd></div>
        <div><dt class="text-sm text-zinc-500">Tanggal Masuk</dt><dd>{{ $letter->tanggal_masuk?->format('d/m/Y') }}</dd></div>
        <div class="md:col-span-2"><dt class="text-sm text-zinc-500">Perihal</dt><dd class="font-medium">{{ $letter->perihal }}</dd></div>
        @if ($letter->keterangan)
            <div class="md:col-span-2"><dt class="text-sm text-zinc-500">Keterangan</dt><dd class="whitespace-pre-line">{{ $letter->keterangan }}</dd></div>
        @endif
    </dl>
    @if ($letter->file)
        <flux:button class="mt-4" :href="\Illuminate\Support\Facades\Storage::disk('public')->url($letter->file)" target="_blank" icon="document-text">Lihat Surat</flux:button>
    @endif
</flux:card>

<flux:card>
    <flux:heading size="lg">Riwayat Disposisi dan Tindak Lanjut</flux:heading>
    <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($dispositions as $disposition)
            <div class="space-y-3 py-4 first:pt-0 last:pb-0" wire:key="history-{{ $disposition->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-medium">{{ $disposition->toUser?->name ?? 'Penerima tidak tersedia' }}</div>
                        <div class="text-sm text-zinc-500">Dari {{ $disposition->fromUser?->name }} · {{ $disposition->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <flux:badge :color="match ($disposition->status) { 'pending' => 'yellow', 'read' => 'blue', 'in_progress' => 'indigo', 'completed' => 'green', default => 'zinc' }">
                        {{ match ($disposition->status) { 'pending' => 'Menunggu dibaca', 'read' => 'Dibaca', 'in_progress' => 'Diproses', 'completed' => 'Selesai', default => $disposition->status } }}
                    </flux:badge>
                </div>
                <div class="whitespace-pre-line text-sm">{{ $disposition->instruction }}</div>
                @if ($disposition->deadline)
                    <div class="text-sm text-zinc-500">Batas waktu: {{ $disposition->deadline->format('d/m/Y') }}</div>
                @endif
                @if ($disposition->read_at)
                    <div class="text-sm text-zinc-500">Dibaca: {{ $disposition->read_at->format('d/m/Y H:i') }}</div>
                @endif
                @if ($disposition->completed_at)
                    <div class="text-sm text-zinc-500">Diselesaikan: {{ $disposition->completed_at->format('d/m/Y H:i') }}</div>
                @endif
                @if ($disposition->notes)
                    <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800"><div class="font-medium">Hasil tindak lanjut</div><div class="mt-1 whitespace-pre-line">{{ $disposition->notes }}</div></div>
                @endif
            </div>
        @empty
            <flux:text>Menunggu disposisi dari Kepala Madrasah.</flux:text>
        @endforelse
    </div>
</flux:card>
