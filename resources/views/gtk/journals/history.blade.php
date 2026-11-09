<x-layouts::app title="Riwayat Jurnal Mengajar Saya">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading size="xl">Riwayat Jurnal Mengajar Saya</flux:heading><flux:text>Jurnal setiap sesi pembelajaran Anda, meliputi kelas, mata pelajaran, jumlah JP, materi, catatan, dan refleksi.</flux:text></div>
            <flux:button :href="route('gtk.journals.index')">Jurnal Hari Ini</flux:button>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input type="month" name="month" label="Bulan" :value="$month->format('Y-m')" :max="today()->format('Y-m')" required />
            <flux:select name="status" label="Status jurnal">
                @foreach (['all' => 'Semua status', 'completed' => 'Selesai', 'draft' => 'Draf'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Tampilkan</flux:button>
        </form>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        <flux:button :href="route('gtk.reports.download', ['kind' => 'teaching', 'month' => $month->format('Y-m')])">Download Jurnal Mengajar (PDF)</flux:button>
        <div class="flex flex-wrap gap-3"><flux:badge>Sesi tercatat: {{ $summary['sessions'] }}</flux:badge><flux:badge color="green">Jurnal selesai: {{ $summary['completed'] }}</flux:badge><flux:badge>Total: {{ $summary['periods'] }} JP</flux:badge></div>
        <flux:text>Ringkasan dan PDF mencakup seluruh bulan terpilih. Beberapa JP berurutan dalam satu sesi dihitung sebagai satu jurnal.</flux:text>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Tanggal</th><th class="p-3">Kelas</th><th class="p-3">Mata pelajaran</th><th class="p-3">JP</th><th class="p-3">Isi jurnal</th><th class="p-3">Status</th><th class="p-3">Tindakan</th></tr></thead>
                <tbody>
                    @forelse ($journals as $journal)
                        @php($parts = explode(' / ', $journal->label, 3))
                        <tr class="border-t border-zinc-200 align-top dark:border-zinc-700">
                            <td class="p-3 whitespace-nowrap">{{ $journal->journal_date->format('d/m/Y') }}</td>
                            <td class="p-3">{{ $parts[1] ?? '-' }}</td><td class="p-3">{{ $parts[0] }}</td><td class="p-3">{{ count($journal->schedule_ids ?? []) }}</td>
                            <td class="p-3 min-w-64"><details><summary class="cursor-pointer underline">Lihat isi jurnal</summary><dl class="mt-3 space-y-3"><div><dt class="font-medium">Uraian materi</dt><dd class="whitespace-pre-wrap">{{ $journal->material ?: '-' }}</dd></div><div><dt class="font-medium">Catatan</dt><dd class="whitespace-pre-wrap">{{ $journal->notes ?: '-' }}</dd></div><div><dt class="font-medium">Refleksi singkat</dt><dd class="whitespace-pre-wrap">{{ $journal->reflection ?: '-' }}</dd></div></dl></details></td>
                            <td class="p-3"><flux:badge :color="$journal->isComplete() ? 'green' : 'zinc'">{{ $journal->isComplete() ? 'Selesai' : 'Draf' }}</flux:badge></td>
                            <td class="p-3"><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('gtk.journals.index', ['date' => $journal->journal_date->toDateString()]).'#'.$journal->session_key">Buka sesi</flux:button>
                                <x-gtk-journal-delete :journal="$journal" :approved="in_array($journal->journal_date->toDateString(), $approvedDates, true)" :history="true" />
                            </div></td>
                        </tr>
                    @empty <tr><td colspan="7" class="p-8 text-center">Belum ada jurnal mengajar pada periode dan status terpilih.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
        {{ $journals->links() }}
    </div>
</x-layouts::app>
