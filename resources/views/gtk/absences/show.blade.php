<x-layouts::app title="Detail Pengajuan Absensi">
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><flux:heading size="xl">Detail Pengajuan Absensi</flux:heading><flux:text class="mt-1">Pengajuan #{{ $absence->id }} · {{ $absence->gtk->full_name }}</flux:text></div>@include('gtk.absences.badge')</div>
    @include('gtk.absences.messages')
    <dl class="grid gap-5 rounded-xl border border-zinc-200 p-5 sm:grid-cols-2 sm:p-6 dark:border-zinc-700">
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Jenis Absen</dt><dd class="mt-1 font-medium">{{ $absence->type }} — {{ \App\Models\GtkAbsenceRequest::TYPES[$absence->type] }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Tanggal ketidakhadiran</dt><dd class="mt-1 font-medium">{{ $absence->start_date->format('d/m/Y') }} – {{ $absence->end_date->format('d/m/Y') }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-zinc-500 dark:text-zinc-400">Keterangan Singkat</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $absence->description }}</dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Diajukan oleh</dt><dd class="mt-1">{{ $absence->submitter?->name ?? 'Akun sudah dihapus' }}<div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $absence->created_at->format('d/m/Y H:i') }}</div></dd></div>
        <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Bukti</dt><dd class="mt-2"><flux:button size="sm" icon="arrow-down-tray" :href="route('gtk.absences.evidence', $absence)">Unduh Bukti</flux:button></dd></div>
        @if($absence->reviewed_at)
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Diperiksa oleh</dt><dd class="mt-1">{{ $absence->reviewer?->name ?? 'Akun sudah dihapus' }}<div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $absence->reviewed_at->format('d/m/Y H:i') }}</div></dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Catatan kepala madrasah</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $absence->review_notes ?: 'Tidak ada catatan.' }}</dd></div>
        @endif
        @if($absence->cancelled_at)<div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Dibatalkan pada</dt><dd class="mt-1">{{ $absence->cancelled_at->format('d/m/Y H:i') }}</dd></div>@endif
    </dl>
    @if($reviewing && $absence->status === 'pending' && auth()->id() !== $absence->submitted_by)
        <form method="POST" action="{{ route('headmaster.absences.decide', $absence) }}" class="space-y-4 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
            @csrf
            <flux:heading size="lg">Keputusan Kepala Madrasah</flux:heading>
            <flux:select label="Keputusan" name="status" required>
                <option value="">Pilih keputusan</option>
                <option value="approved" @selected(old('status') === 'approved')>Setujui</option>
                <option value="rejected" @selected(old('status') === 'rejected')>Tolak</option>
            </flux:select>
            <flux:textarea label="Catatan / alasan penolakan" name="review_notes" rows="3" maxlength="2000" description="Wajib diisi jika pengajuan ditolak.">{{ old('review_notes') }}</flux:textarea>
            <flux:text>Persetujuan mencatat ketidakhadiran pada seluruh tanggal dalam rentang ini. Data presensi yang sudah ada tidak akan ditimpa.</flux:text>
            <flux:button type="submit" variant="primary">Simpan Keputusan</flux:button>
        </form>
    @elseif($absence->status === 'approved')
        <flux:callout variant="success" icon="check-circle">Pengajuan disetujui dan sudah dicatat pada rekap presensi sesuai rentang tanggal.</flux:callout>
    @elseif($absence->status === 'pending' && auth()->user()->gtk_id === $absence->gtk_id)
        <div class="space-y-3"><flux:text>Menunggu persetujuan kepala madrasah. Jika ada kesalahan, batalkan dan buat pengajuan baru.@if(auth()->user()->isHeadmaster()) Pengajuan sendiri harus diperiksa oleh kepala madrasah lain.@endif</flux:text>
        <form method="POST" action="{{ route('gtk.absences.cancel', $absence) }}" onsubmit="return confirm('Batalkan pengajuan absensi ini?')">@csrf @method('DELETE')<flux:button type="submit" variant="danger">Batalkan Pengajuan</flux:button></form></div>
    @endif
    <flux:button :href="route($reviewing ? 'headmaster.absences.index' : 'gtk.absences.index')">Kembali ke Daftar</flux:button>
</div>
</x-layouts::app>
