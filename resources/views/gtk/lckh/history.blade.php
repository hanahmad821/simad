<x-layouts::app title="Riwayat LCKH Saya">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading size="xl">Riwayat LCKH Saya</flux:heading><flux:text>Riwayat kegiatan harian Anda beserta status approval dan catatan kepala sekolah.</flux:text></div>
            <flux:button :href="route('gtk.lckh.index')">Isi LCKH Hari Ini</flux:button>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input type="month" name="month" label="Bulan" :value="$month->format('Y-m')" :max="today()->format('Y-m')" required />
            <flux:select name="status" label="Filter status">
                @foreach (['all' => 'Semua status', 'completed' => 'LCKH selesai', 'draft' => 'LCKH draf', 'missing' => 'Belum diisi', 'pending' => 'Menunggu approval', 'approved' => 'Disetujui', 'revision_requested' => 'Perlu perbaikan'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Tampilkan</flux:button>
        </form>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        <flux:button :href="route('gtk.reports.download', ['kind' => 'lckh', 'month' => $month->format('Y-m')])">Download LCKH (PDF)</flux:button>
        <div class="flex flex-wrap gap-3">
            <flux:badge color="green">Selesai: {{ $summary['completed'] }}</flux:badge>
            <flux:badge>Draf: {{ $summary['draft'] }}</flux:badge>
            <flux:badge>Belum diisi: {{ $summary['missing'] }}</flux:badge>
            <flux:badge color="red">Perlu perbaikan: {{ $summary['revision_requested'] }}</flux:badge>
        </div>
        <flux:text>Ringkasan dan PDF mencakup seluruh bulan terpilih. Approval harian mencakup presensi dan LCKH. Hari kerja tanpa LCKH ditampilkan agar mudah dilengkapi.</flux:text>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Tanggal</th><th class="p-3">Kegiatan dan hasil pekerjaan</th><th class="p-3">Status LCKH</th><th class="p-3">Approval harian</th><th class="p-3">Catatan kepala sekolah</th><th class="p-3">Tindakan</th></tr></thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr class="border-t border-zinc-200 align-top dark:border-zinc-700">
                            <td class="p-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                            <td class="p-3 min-w-56 whitespace-pre-wrap">{{ $day['lckh']?->activities ?: 'Belum ada kegiatan yang dicatat.' }}</td>
                            <td class="p-3">{{ $day['lckh']?->isComplete() ? 'Selesai' : ($day['lckh'] ? 'Draf' : 'Belum diisi') }}</td>
                            <td class="p-3"><flux:badge :color="$day['approval']?->status === 'approved' ? 'green' : ($day['approval']?->status === 'revision_requested' ? 'red' : 'zinc')">{{ ['pending' => 'Menunggu approval', 'approved' => 'Disetujui', 'revision_requested' => 'Perlu perbaikan'][$day['approval']?->status ?? 'pending'] }}</flux:badge></td>
                            <td class="p-3 whitespace-pre-wrap">{{ $day['approval']?->notes ?: '-' }}</td>
                            <td class="p-3"><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('gtk.lckh.index', ['date' => $day['date']])">{{ $day['approval']?->status === 'revision_requested' ? 'Perbaiki LCKH' : ($day['lckh'] ? 'Lihat / Ubah' : 'Isi LCKH') }}</flux:button>
                                <x-gtk-journal-delete :journal="$day['lckh']" :approved="$day['approval']?->status === 'approved'" :history="true" />
                            </div></td>
                        </tr>
                    @empty <tr><td colspan="6" class="p-8 text-center">Tidak ada riwayat yang sesuai dengan periode dan status terpilih.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
