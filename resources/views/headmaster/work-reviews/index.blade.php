<x-layouts::app title="Approval LCKH GTK">
    <div class="space-y-6">
        <flux:heading size="xl">Approval LCKH GTK</flux:heading>
        <flux:text>Pilih nama GTK untuk memeriksa LCKH, lalu centang tanggal yang ingin disetujui. Presensi dihitung otomatis tanpa approval.</flux:text>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        <div class="flex flex-wrap items-end justify-between gap-4">
            <form method="GET" class="flex items-end gap-3">
                <flux:input type="month" label="Bulan" name="month" :value="$month->format('Y-m')" :max="today()->format('Y-m')" required />
                <flux:button type="submit">Tampilkan</flux:button>
            </form>
        </div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">GTK</th><th class="p-3">LCKH selesai</th><th class="p-3">Disetujui</th><th class="p-3">Perlu perbaikan</th></tr></thead>
                <tbody>
                    @forelse ($gtks as $gtk)
                        @php
                            $daily = $lckhs->get($gtk->id, collect());
                            $approval = $approvals->get($gtk->id, collect());
                        @endphp
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3"><a class="text-green-700 underline dark:text-green-400" href="{{ route('headmaster.work-reviews.show', [$gtk, 'month' => $month->format('Y-m')]) }}">{{ $gtk->full_name }}</a></td>
                            <td class="p-3">{{ $daily->filter(fn ($row) => $row->isComplete())->count() }}</td>
                            <td class="p-3">{{ $approval->where('status', 'approved')->count() }}</td>
                            <td class="p-3">{{ $approval->where('status', 'revision_requested')->count() }}</td>
                        </tr>
                    @empty <tr><td colspan="4" class="p-6">Belum ada data GTK.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
        {{ $gtks->links() }}
    </div>
</x-layouts::app>
