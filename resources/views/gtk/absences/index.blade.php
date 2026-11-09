<x-layouts::app :title="$reviewing ? 'Approval Absensi GTK' : 'Pengajuan Absensi'">
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $reviewing ? 'Approval Absensi GTK' : 'Pengajuan Absensi' }}</flux:heading>
            <flux:text class="mt-1">{{ $reviewing ? 'Periksa bukti ketidakhadiran GTK dan berikan keputusan.' : 'Ajukan sakit, izin, cuti, dinas luar, dan ketidakhadiran lainnya.' }}</flux:text>
        </div>
        @if(!$reviewing && auth()->user()->gtk?->is_active)
            <flux:button icon="plus" variant="primary" :href="route('gtk.absences.create')">Tambah Absensi</flux:button>
        @endif
    </div>
    @include('gtk.absences.messages')
    <form method="GET" class="grid items-end gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-700">
        <flux:select label="Status" name="status">
            <option value="">Semua status</option>
            @foreach(\App\Models\GtkAbsenceRequest::STATUSES as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
        </flux:select>
        <flux:input label="Bulan ketidakhadiran" name="month" type="month" :value="$month" />
        <flux:input :label="$reviewing ? 'Cari nama GTK / keterangan' : 'Cari keterangan'" name="q" :value="$search" maxlength="100" placeholder="Ketik kata kunci" />
        <div class="flex gap-2"><flux:button type="submit" variant="primary">Tampilkan</flux:button><flux:button :href="route($reviewing ? 'headmaster.absences.index' : 'gtk.absences.index', ['status' => ''])">Reset</flux:button></div>
    </form>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900"><tr class="border-b border-zinc-200 dark:border-zinc-700">
                <th scope="col" class="p-3">No</th>
                @if($reviewing)<th scope="col" class="p-3">GTK</th>@endif
                <th scope="col" class="p-3">Tanggal mulai</th><th scope="col" class="p-3">Tanggal akhir</th><th scope="col" class="p-3">Jenis</th><th scope="col" class="p-3">Keterangan</th><th scope="col" class="p-3">Bukti</th><th scope="col" class="p-3">Status</th><th scope="col" class="p-3">Opsi</th>
            </tr></thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($absences as $absence)
                    <tr>
                        <td class="p-3 align-top">{{ $absences->firstItem() + $loop->index }}</td>
                        @if($reviewing)<td class="p-3 align-top font-medium">{{ $absence->gtk->full_name }}</td>@endif
                        <td class="whitespace-nowrap p-3 align-top">{{ $absence->start_date->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap p-3 align-top">{{ $absence->end_date->format('d/m/Y') }}</td>
                        <td class="p-3 align-top"><span class="font-semibold">{{ $absence->type }}</span><div class="mt-1 min-w-32 text-xs text-zinc-500 dark:text-zinc-400">{{ \App\Models\GtkAbsenceRequest::TYPES[$absence->type] }}</div></td>
                        <td class="min-w-48 max-w-sm break-words p-3 align-top">{{ \Illuminate\Support\Str::limit($absence->description, 160) }}</td>
                        <td class="p-3 align-top"><a class="text-green-700 underline dark:text-green-400" href="{{ route('gtk.absences.evidence', $absence) }}">Unduh bukti</a></td>
                        <td class="p-3 align-top">@include('gtk.absences.badge')</td>
                        <td class="p-3 align-top"><flux:button size="sm" :href="route('gtk.absences.show', $absence)">{{ $reviewing && $absence->status === 'pending' ? 'Periksa' : 'Detail' }}</flux:button></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $reviewing ? 10 : 9 }}" class="p-10 text-center text-zinc-500 dark:text-zinc-400">Belum ada pengajuan yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $absences->links() }}
    <flux:text>Hanya pengajuan yang disetujui yang masuk ke rekap presensi. Pengajuan yang masih diajukan dapat dibatalkan melalui halaman detail.</flux:text>
</div>
</x-layouts::app>
