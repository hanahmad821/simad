@props(['letter'])

<flux:card>
    <flux:text>Metode tanda tangan: {{ $letter->signatureLabel() }}</flux:text>
    @if ($letter->use_template && in_array($letter->status, ['draft', 'pending'], true))
        <flux:button class="mt-3" :href="route('outgoing-letters.preview', $letter)" target="_blank" icon="eye">Pratinjau PDF Surat</flux:button>
    @endif
    @if (in_array($letter->status, ['issued', 'awaiting_signature'], true))
        <flux:heading class="mt-2" size="sm">{{ $letter->status === 'awaiting_signature' ? 'Dokumen cetak tanpa QR Code' : 'PDF Final Surat' }}</flux:heading>
        @if ($letter->final_pdf_file)
            <flux:text class="mt-2">{{ $letter->usesWetSignature() ? ($letter->status === 'awaiting_signature' ? 'Surat sudah disetujui. Cetak dan mintakan tanda tangan basah kepala sekolah, lalu unggah scan sebagai arsip.' : 'Arsip scan surat yang telah ditandatangani kepala sekolah.') : 'Surat yang telah disetujui dan dilengkapi QR verifikasi.' }}</flux:text>
            <flux:button class="mt-4" :href="route('outgoing-letters.final-pdf', $letter)" target="_blank" icon="arrow-top-right-on-square">
                {{ $letter->status === 'awaiting_signature' ? 'Buka PDF untuk dicetak' : 'Buka PDF Final' }}
            </flux:button>
        @else
            <flux:text class="mt-2">PDF final belum tersedia.</flux:text>
        @endif
    @elseif ($letter->file)
        <flux:heading class="mt-2" size="sm">PDF Sumber Surat</flux:heading>
        <flux:text class="mt-2">{{ $letter->usesWetSignature() ? 'Dokumen awal untuk diperiksa kepala sekolah. Setelah disetujui, dokumen dicetak tanpa QR Code.' : 'Dokumen asli unggahan admin. Penanda [[QR_CODE]] akan ditutup dengan QR setelah surat disetujui.' }}</flux:text>
        <flux:button class="mt-4" :href="\Illuminate\Support\Facades\Storage::disk('public')->url($letter->file)" target="_blank" icon="eye">Buka PDF Sumber</flux:button>
    @endif
</flux:card>
