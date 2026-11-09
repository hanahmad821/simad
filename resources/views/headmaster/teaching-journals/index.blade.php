<x-layouts::app title="Rekap Jurnal Mengajar">
    <div class="space-y-6">
        <flux:heading size="xl">Rekap Jurnal Mengajar</flux:heading>
        <flux:text>Rekap terpisah per guru: kelas, mata pelajaran, jumlah jam pelajaran, materi, catatan, dan refleksi setiap sesi.</flux:text>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input type="month" name="month" label="Bulan" :value="$month->format('Y-m')" :max="today()->format('Y-m')" required />
            <flux:button type="submit">Tampilkan</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm"><thead><tr><th class="p-3">Guru</th><th class="p-3">Sesi tercatat</th><th class="p-3">Jurnal selesai</th><th class="p-3">Jumlah JP</th><th class="p-3">Rekap</th></tr></thead><tbody>
                @forelse ($teachers as $gtk)
                    @php($entries = $journals->get($gtk->id, collect()))
                    <tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3">{{ $gtk->full_name }}</td><td class="p-3">{{ $entries->count() }}</td><td class="p-3">{{ $entries->filter(fn ($entry) => $entry->isComplete())->count() }}</td><td class="p-3">{{ $entries->sum(fn ($entry) => count($entry->schedule_ids ?? [])) }}</td><td class="p-3"><flux:button :href="route('headmaster.teaching-journals.show', [$gtk, 'month' => $month->format('Y-m')])">Lihat jurnal</flux:button></td></tr>
                @empty <tr><td colspan="5" class="p-6">Belum ada data guru.</td></tr> @endforelse
            </tbody></table>
        </div>
        {{ $teachers->links() }}
    </div>
</x-layouts::app>
