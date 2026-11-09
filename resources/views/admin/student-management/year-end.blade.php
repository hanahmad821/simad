<x-layouts::app title="Proses Akhir Tahun">
<div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-3"><div><flux:heading size="xl">Proses Akhir Tahun</flux:heading><flux:text class="mt-1">Selesaikan keputusan siswa per kelas: naik, tinggal, lulus, atau ditunda.</flux:text></div><div class="flex gap-2"><flux:button :href="route('admin.student-management.history')">Riwayat Proses</flux:button><flux:button :href="route('admin.academic-years.index')">Tahun Pelajaran</flux:button></div></div>
    @include('admin.classes.messages')
    <form method="GET" class="flex flex-wrap items-end gap-3"><flux:select name="source_year_id" label="Tahun asal">@foreach($years as $y)<option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->name }}</option>@endforeach</flux:select><flux:button type="submit">Tampilkan</flux:button></form>
    @unless($source)
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($classes as $class)
        @php($counts = $progress[$class->id])
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between"><flux:heading size="lg">{{ $class->name }}</flux:heading><flux:badge :color="$counts['total'] && !$counts['pending'] && !$counts['deferred'] ? 'green' : 'zinc'">{{ $class->grade === 12 ? 'Kelulusan' : 'Kenaikan kelas' }}</flux:badge></div>
            <div class="grid grid-cols-3 gap-3 text-center text-sm"><div><div class="text-xl font-semibold">{{ $counts['final'] }}</div>Selesai</div><div><div class="text-xl font-semibold">{{ $counts['deferred'] }}</div>Ditunda</div><div><div class="text-xl font-semibold">{{ $counts['pending'] }}</div>Belum diproses</div></div>
            <flux:button class="w-full" :href="route('admin.student-progressions.index', ['source_year_id' => $year->id, 'source_class_id' => $class->id])">{{ $counts['pending'] || $counts['deferred'] ? 'Lanjutkan' : 'Lihat Hasil' }}</flux:button>
        </flux:card>
        @empty<flux:callout>Belum ada rombel untuk tahun ini. <a class="underline" href="{{ route('admin.classes.index') }}">Kelola rombel</a>.</flux:callout>@endforelse
    </div>
    @else
    <div class="flex flex-wrap items-center justify-between gap-2"><div><flux:heading size="lg">{{ $source->name }} · {{ $year->name }}</flux:heading><flux:text>1. Pilih kelas → <strong>2. Atur hasil</strong> → 3. Tinjau → 4. Selesai</flux:text></div><flux:button :href="route('admin.student-progressions.index', ['source_year_id' => $year->id])">Kelas Lain</flux:button></div>
    <form method="POST" action="{{ route('admin.student-management.preview') }}" class="space-y-4"
        x-data="{ rows: @js($rows), targets: @js($targets), grade: {{ $source->grade }}, search: '', filter: '', bulk: '', target: '',
            apply(selectedOnly) { if(!this.bulk) return; this.rows.forEach(r => {if(!r.final && (!selectedOnly || r.selected)){r.action=this.bulk;r.target_class_id=['promote','repeat'].includes(this.bulk)?this.target:'';}}); },
            choices(action) {return this.targets.filter(t=>t.grade === this.grade + (action === 'promote' ? 1 : 0));},
            count(action) {return this.rows.filter(r=>!r.final && r.action===action).length;}
        }">
        @csrf<input type="hidden" name="kind" value="year_end"><input type="hidden" name="source_class_id" value="{{ $source->id }}">
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><flux:heading size="sm">Isi massal, lalu sesuaikan pengecualian</flux:heading><flux:text>Tahun tujuan: {{ $next?->name ?: 'Belum tersedia' }}</flux:text></div>
            @if(!$next)<flux:callout>Untuk naik atau tinggal kelas, <a class="underline" href="{{ route('admin.academic-years.index') }}">buat tahun berikutnya</a> dan rombel tujuan terlebih dahulu.</flux:callout>@endif
            <div class="flex flex-wrap items-end gap-3">
                <flux:select label="Hasil" x-model="bulk" x-on:change="target=''"><option value="">Pilih hasil</option>@if($source->grade < 12)<option value="promote">Naik kelas</option>@else<option value="graduate">Lulus</option>@endif<option value="repeat">Tinggal kelas</option><option value="defer">Ditunda</option></flux:select>
                <div x-show="['promote','repeat'].includes(bulk)" x-cloak><flux:select label="Kelas tujuan" x-model="target"><option value="">Pilih kelas</option><template x-for="t in choices(bulk)" :key="t.id"><option :value="String(t.id)" x-text="t.name"></option></template></flux:select></div>
                <flux:button type="button" x-on:click="apply(false)">Terapkan ke Semua Belum Final</flux:button><flux:button type="button" x-on:click="apply(true)">Terapkan ke Pilihan</flux:button>
            </div>
            <div class="flex flex-wrap gap-3"><flux:input label="Cari siswa" x-model="search" placeholder="Nama atau NIS" /><flux:select label="Filter hasil" x-model="filter"><option value="">Semua</option><option value="pending">Belum diproses</option><option value="defer">Ditunda</option><option value="final">Selesai</option><option value="promote">Naik kelas</option><option value="repeat">Tinggal kelas</option><option value="graduate">Lulus</option></flux:select></div>
        </flux:card>
        <flux:card class="space-y-4">
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3"><input type="checkbox" aria-label="Pilih semua belum final" x-on:change="rows.forEach(r=>{if(!r.final)r.selected=$event.target.checked})"></th><th class="p-3">Siswa</th><th class="p-3">Hasil</th><th class="p-3">Tujuan / Alasan</th></tr></thead>
            <tbody><template x-for="(row,index) in rows" :key="row.student_id"><tr class="border-b border-zinc-100 dark:border-zinc-800" x-show="(row.name+' '+(row.nis||'')).toLowerCase().includes(search.toLowerCase()) && (!filter || (filter==='pending' ? !row.action : filter==='final' ? row.final : row.action===filter))">
                <td class="p-3"><input type="checkbox" x-model="row.selected" :disabled="row.final" :aria-label="'Pilih '+row.name"><input type="hidden" :name="'rows['+index+'][student_id]'" :value="row.student_id" :disabled="row.final"></td>
                <td class="p-3"><div class="font-medium" x-text="row.name"></div><span class="text-xs text-zinc-500" x-text="row.nis || 'NIS belum diisi'"></span><span x-show="row.final" class="ml-2 text-xs text-green-600">Selesai</span></td>
                <td class="p-3"><select class="w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-800" x-model="row.action" :name="'rows['+index+'][action]'" :disabled="row.final" x-on:change="row.target_class_id=''" :aria-label="'Hasil '+row.name">
                    <option value="">Belum diproses</option>@if($source->grade < 12)<option value="promote">Naik kelas</option>@else<option value="graduate">Lulus</option>@endif<option value="repeat">Tinggal kelas</option><option value="defer">Ditunda</option></select></td>
                <td class="p-3"><div x-show="['promote','repeat'].includes(row.action)"><select class="w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-800" x-model="row.target_class_id" :name="'rows['+index+'][target_class_id]'" :disabled="row.final || !['promote','repeat'].includes(row.action)" :aria-label="'Tujuan '+row.name"><option value="">Pilih kelas tujuan</option><template x-for="t in choices(row.action)" :key="t.id"><option :value="String(t.id)" x-text="t.name"></option></template></select></div>
                    <input x-show="row.action==='defer'" class="w-full rounded-lg border border-zinc-300 bg-transparent p-2" x-model="row.reason" :name="'rows['+index+'][reason]'" :disabled="row.final || row.action!=='defer'" placeholder="Alasan ditunda (wajib)" :aria-label="'Alasan '+row.name">
                    <span x-show="row.action==='graduate'" class="text-zinc-500">Menjadi alumni</span></td>
            </tr></template></tbody></table></div>
            <p x-show="rows.length===0" class="p-4 text-zinc-500">Belum ada anggota untuk diproses.</p>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><p class="text-sm" x-text="count('promote')+' naik · '+count('repeat')+' tinggal · '+count('graduate')+' lulus · '+count('defer')+' ditunda'"></p><flux:text>Siswa tanpa pilihan tetap belum diproses.</flux:text></div>
                <flux:input type="date" name="date" label="Tanggal keputusan / kelulusan" :value="$date" required />
                <flux:button type="submit" variant="primary" icon="eye" x-bind:disabled="!rows.some(r=>!r.final && r.action)">Tinjau Pilihan</flux:button>
            </div>
        </flux:card>
    </form>
    @endunless
    <flux:text><a class="underline" href="{{ route('admin.student-management.issues') }}">Periksa masalah data lama</a></flux:text>
</div>
</x-layouts::app>