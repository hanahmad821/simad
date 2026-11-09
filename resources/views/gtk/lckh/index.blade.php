<x-layouts::app title="LCKH">
    <div class="space-y-6">
        <div><flux:heading size="xl">LCKH / Jurnal Harian</flux:heading><flux:text>Catat kegiatan dan hasil pekerjaan harian. LCKH wajib dilengkapi sebelum checkout.</flux:text></div>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input type="date" name="date" label="Tanggal" :value="$date->toDateString()" :max="today()->toDateString()" required />
            <flux:button type="submit">Tampilkan</flux:button>
            <flux:button :href="route('gtk.attendance.index')">Presensi Saya</flux:button>
            <flux:button :href="route('gtk.lckh.history', ['month' => $date->format('Y-m')])">Riwayat LCKH</flux:button>
        </form>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        @if ($review)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>Approval {{ $date->format('d/m/Y') }}: {{ ['pending' => 'Menunggu approval', 'revision_requested' => 'Perlu perbaikan', 'approved' => 'Disetujui'][$review->status] }}</flux:heading>
                <p class="mt-2 whitespace-pre-wrap text-sm">{{ $review->notes }}</p>
                @if ($review->status === 'pending' && $review->notes)<flux:text>Catatan sebelumnya tetap ditampilkan. Perubahan data menunggu approval kembali.</flux:text>@endif
            </div>
        @endif
        @php($retry = old('session_key') === 'lckh')
        <form id="lckh" method="POST" action="{{ route('gtk.lckh.store') }}" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="session_key" value="lckh">
            <div class="flex items-center justify-between gap-2">
                <flux:heading>Jurnal Harian / LCKH</flux:heading>
                <flux:badge :color="$daily?->isComplete() ? 'green' : 'zinc'">{{ $daily?->isComplete() ? 'Selesai' : 'Draf / belum diisi' }}</flux:badge>
            </div>
            <flux:textarea label="Uraian kegiatan harian dan hasilnya" name="activities" rows="5" maxlength="30000" description="Tuliskan kegiatan harian dan hasil pekerjaan Anda.">{{ $retry ? old('activities') : $daily?->activities }}</flux:textarea>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" name="status" value="draft">Simpan draf</flux:button>
                <flux:button type="submit" name="status" value="completed" variant="primary">Selesaikan LCKH</flux:button>
            </div>
        </form>
        <x-gtk-journal-delete :journal="$daily" :approved="$review?->status === 'approved'" />
    </div>
</x-layouts::app>
