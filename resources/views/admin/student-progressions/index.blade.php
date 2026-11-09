<x-layouts::app title="Kenaikan Kelas & Kelulusan">
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><flux:heading size="xl">Kenaikan Kelas & Kelulusan</flux:heading>
        <flux:text class="mt-1">Proses siswa per tahun pelajaran. Riwayat kelas, kode siswa, dan presensi lama tetap tersimpan.</flux:text></div>
        <flux:button :href="route('admin.students.index')" wire:navigate>Daftar Siswa</flux:button>
    </div>
    @include('admin.classes.messages')
    <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" @if($errors->has('name') || $errors->has('start_date') || $errors->has('end_date') || $errors->has('year_id')) open @endif>
        <summary class="cursor-pointer font-medium">Kelola tahun pelajaran</summary>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.student-progressions.years.store') }}" class="space-y-3">
                @csrf
                <flux:heading size="sm">Tambah tahun pelajaran</flux:heading>
                <flux:input name="name" label="Nama" placeholder="2027/2028" :value="old('name')" required />
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input type="date" name="start_date" label="Tanggal mulai" :value="old('start_date')" required />
                    <flux:input type="date" name="end_date" label="Tanggal selesai" :value="old('end_date')" required />
                </div>
                <flux:button type="submit">Tambah Tahun Pelajaran</flux:button>
            </form>
            <form method="POST" action="{{ route('admin.student-progressions.years.activate') }}" class="space-y-3"
                onsubmit="return confirm('Ganti tahun pelajaran aktif? Daftar kelas siswa dan jadwal akan mengikuti tahun yang dipilih.')">
                @csrf
                <flux:heading size="sm">Tahun pelajaran aktif</flux:heading>
                <flux:text>Aktifkan tahun baru setelah pembagian kelas dan jadwal siap.</flux:text>
                <flux:select name="year_id" label="Tahun pelajaran" required>
                    @foreach($years as $year)<option value="{{ $year->id }}" @selected($year->is_active)>{{ $year->name }}{{ $year->is_active ? ' (Aktif)' : '' }}</option>@endforeach
                </flux:select>
                <flux:button type="submit">Aktifkan Tahun Pelajaran</flux:button>
            </form>
        </div>
    </details>
    <flux:card>
        <form method="GET" class="grid items-end gap-3 sm:grid-cols-3">
            <flux:select name="source_year_id" label="Tahun pelajaran asal" required>
                <option value="">Pilih tahun pelajaran</option>
                @foreach($years as $year)<option value="{{ $year->id }}" @selected($sourceYear?->id == $year->id)>{{ $year->name }}</option>@endforeach
            </flux:select>
            <flux:select name="source_class_id" label="Kelas asal" required>
                <option value="">Pilih kelas</option>
                @foreach($classes as $class)<option value="{{ $class->id }}" @selected($sourceClass?->id == $class->id)>{{ $class->name }}</option>@endforeach
            </flux:select>
            <flux:button type="submit">Tampilkan Siswa</flux:button>
        </form>
    </flux:card>
    @if($sourceYear && $sourceClass)
    <form method="POST" action="{{ route('admin.student-progressions.preview') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="source_year_id" value="{{ $sourceYear->id }}">
        <input type="hidden" name="source_class_id" value="{{ $sourceClass->id }}">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ $sourceClass->name }} · {{ $sourceYear->name }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select label="Proses utama" name="default_action" required>
                    @foreach($actions as $value => $label)<option value="{{ $value }}" @selected(old('default_action', $sourceClass->grade == 12 ? 'graduate' : 'promote') === $value)>{{ $label }}</option>@endforeach
                </flux:select>
                <flux:select label="Tahun pelajaran tujuan" name="destination_year_id">
                    <option value="">Tidak diperlukan untuk lulus/pindah/keluar</option>
                    @foreach($years->filter(fn($y) => $y->start_date->gt($sourceYear->end_date)) as $year)<option value="{{ $year->id }}" @selected(old('destination_year_id') == $year->id)>{{ $year->name }}</option>@endforeach
                </flux:select>
                <flux:select label="Kelas tujuan utama" name="default_target_class_id">
                    <option value="">Pilih untuk naik/tinggal kelas</option>
                    @foreach($classes->where('is_active', true)->whereIn('grade', [$sourceClass->grade, $sourceClass->grade + 1]) as $class)<option value="{{ $class->id }}" @selected(old('default_target_class_id') == $class->id)>{{ $class->name }}</option>@endforeach
                </flux:select>
                <flux:input type="date" label="Tanggal efektif" name="effective_date" :value="old('effective_date', today()->toDateString())" required />
            </div>
            <flux:text>Pilih siswa yang diproses. Atur pengecualian per siswa untuk tinggal kelas atau kelas tujuan berbeda. Siswa yang tidak dipilih tidak berubah.</flux:text>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="p-3"><input type="checkbox" aria-label="Pilih semua siswa" onchange="this.closest('table').querySelectorAll('input[name=&quot;selected[]&quot;]').forEach(input => input.checked = this.checked)"></th>
                        <th class="p-3">Siswa</th><th class="p-3">Proses</th><th class="p-3">Kelas tujuan</th>
                    </tr></thead>
                    <tbody>
                    @forelse($members as $member)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="p-3"><input type="checkbox" name="selected[]" value="{{ $member->student_id }}" aria-label="Pilih {{ $member->student->full_name }}" @checked(in_array($member->student_id, old('selected', [])))></td>
                            <td class="p-3"><div class="font-medium">{{ $member->student->full_name }}</div><div class="text-xs text-zinc-500">NIS: {{ $member->student->nis ?: '—' }}</div></td>
                            <td class="p-3"><flux:select name="choices[{{ $member->student_id }}][action]" aria-label="Proses {{ $member->student->full_name }}">
                                <option value="">Ikuti proses utama</option>
                                @foreach($actions as $value => $label)<option value="{{ $value }}" @selected(old('choices.'.$member->student_id.'.action') === $value)>{{ $label }}</option>@endforeach
                            </flux:select></td>
                            <td class="p-3"><flux:select name="choices[{{ $member->student_id }}][target_class_id]" aria-label="Kelas tujuan {{ $member->student->full_name }}">
                                <option value="">Ikuti kelas tujuan utama</option>
                                @foreach($classes->where('is_active', true)->whereIn('grade', [$sourceClass->grade, $sourceClass->grade + 1]) as $class)<option value="{{ $class->id }}" @selected(old('choices.'.$member->student_id.'.target_class_id') == $class->id)>{{ $class->name }}</option>@endforeach
                            </flux:select></td>
                        </tr>
                    @empty<tr><td colspan="4" class="p-6 text-center text-zinc-500">Tidak ada siswa aktif yang belum diproses pada kelas dan tahun pelajaran ini.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            @if($members->isNotEmpty())<flux:button type="submit" variant="primary" icon="eye">Tinjau Ringkasan</flux:button>@endif
        </flux:card>
    </form>
    @endif
    <flux:card class="space-y-3">
        <flux:heading size="lg">Riwayat Proses Terbaru</flux:heading>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr class="border-b"><th class="p-3">Siswa</th><th class="p-3">Kelas / Tahun asal</th><th class="p-3">Proses</th><th class="p-3">Tujuan</th><th class="p-3">Tanggal efektif</th><th class="p-3">Operator</th></tr></thead>
            <tbody>@forelse($recent as $item)<tr class="border-b"><td class="p-3">{{ $item->full_name }}</td><td class="p-3">{{ $item->class_name }} · {{ $item->year_name }}</td><td class="p-3">{{ $actions[$item->action] }}</td><td class="p-3">{{ $item->destination_class ? $item->destination_class.' · '.$item->destination_year : 'Arsip' }}</td><td class="p-3">{{ $item->effective_date }}</td><td class="p-3">{{ $item->actor }}</td></tr>
            @empty<tr><td colspan="6" class="p-4 text-zinc-500">Belum ada proses kenaikan kelas atau perubahan status.</td></tr>@endforelse</tbody>
        </table></div>
    </flux:card>
</div>
</x-layouts::app>
