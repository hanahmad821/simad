@props(['uploads' => []])
<div class="space-y-3">
    <flux:input type="file" wire:model="attachmentUploads" multiple
        accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png"
        label="Unggah lampiran (opsional)" />
    <flux:description>PDF, Word (.docx), Excel (.xlsx), JPG, atau PNG. Maksimal 5 berkas per surat, masing-masing 5 MB. Anda dapat memilih beberapa berkas sekaligus.</flux:description>
    <flux:description>Kosongkan keterangan Lampiran jika ingin jumlah berkas ditampilkan otomatis pada surat.</flux:description>
    <p wire:loading wire:target="attachmentUploads" class="text-sm text-emerald-700 dark:text-emerald-300">Mengunggah berkas…</p>
    <flux:error name="attachmentUploads" />
    @if ($uploads)
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($uploads as $index => $upload)
                @if ($upload instanceof \Illuminate\Http\UploadedFile)
                    <li class="flex items-start justify-between gap-3 p-3" wire:key="pending-attachment-{{ $index }}-{{ $upload->getFilename() }}">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-medium">{{ $upload->getClientOriginalName() }}</p>
                            <p class="text-xs text-zinc-500">{{ number_format($upload->getSize() / 1024, 0, ',', '.') }} KB · Belum disimpan</p>
                            <flux:error name="attachmentUploads.{{ $index }}" />
                        </div>
                        <flux:button type="button" size="xs" variant="ghost" wire:click="removeAttachmentUpload({{ $index }})" aria-label="Batalkan {{ $upload->getClientOriginalName() }}">Batal</flux:button>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</div>
