@props(['letter', 'editable' => false, 'uploads' => []])
@if (app(\App\Services\OutgoingLetterAttachmentService::class)->supports($letter))
    @php($attachments = $letter->attachments()->orderBy('id')->get())
    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">Lampiran Berkas</flux:heading>
            <flux:text class="mt-1">Berkas pendukung yang menyertai surat.</flux:text>
        </div>
        @if ($attachments->isNotEmpty())
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($attachments as $attachment)
                    <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="letter-attachment-{{ $attachment->id }}">
                        <div class="min-w-0 w-full sm:w-auto sm:flex-1">
                            <p class="break-words text-sm font-medium">{{ $attachment->original_name }}</p>
                            <p class="text-xs text-zinc-500">{{ $attachment->formattedSize() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button href="{{ route('outgoing-letters.attachments.download', [$letter, $attachment]) }}" size="sm" icon="arrow-down-tray" aria-label="Unduh {{ $attachment->original_name }}">Unduh</flux:button>
                            @if ($editable && $letter->status === 'draft')
                                <flux:button type="button" size="sm" variant="ghost" wire:click="deleteAttachment({{ $attachment->id }})" wire:confirm="Hapus lampiran ini dari surat?" aria-label="Hapus {{ $attachment->original_name }}">Hapus</flux:button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <flux:text>Belum ada berkas lampiran.</flux:text>
        @endif
        @if ($editable && $letter->status === 'draft')
            <x-letter-attachment-upload :uploads="$uploads" />
            <flux:button type="button" wire:click="saveAttachments" wire:loading.attr="disabled" wire:target="attachmentUploads,saveAttachments" variant="primary">Simpan Lampiran</flux:button>
        @elseif ($attachments->isNotEmpty())
            <flux:text class="text-xs">Lampiran tersimpan sebagai bagian dari arsip surat.</flux:text>
        @endif
    </flux:card>
@endif
