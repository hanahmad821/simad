<x-layouts::app title="Pengampu Pelajaran">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading size="xl">Pengampu Pelajaran</flux:heading>
                <flux:text class="mt-1">Daftar guru pengampu per kelas dan tahun pelajaran. Penugasan juga dapat dikelola melalui detail GTK.</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button icon="arrow-left" :href="route('admin.schedules.index', ['academic_year_id' => $year?->id, 'school_class_id' => request('class_id')])" wire:navigate>Jadwal Pelajaran</flux:button>
                @if(!request()->boolean('create') && count($assignmentErrors) === 0)
                    <flux:button variant="primary" icon="plus" :href="route('admin.schedules.assignments', ['academic_year_id' => $year?->id, 'class_id' => request('class_id'), 'create' => 1])" wire:navigate>Tambah Pengampu</flux:button>
                @endif
            </div>
        </div>

        @if(session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif
        @if(count($assignmentErrors) > 0)
            <flux:callout variant="danger" role="alert"><ul>@foreach($assignmentErrors as $message)<li>{{ $message }}</li>@endforeach</ul></flux:callout>
        @endif

        <flux:card>
            <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:select name="academic_year_id" label="Tahun Pelajaran">
                    @foreach($years as $y)
                        <option value="{{ $y->id }}" @selected($year?->id === $y->id)>{{ $y->name }}</option>
                    @endforeach
                </flux:select>
                <flux:button type="submit">Tampilkan</flux:button>
                @if(request('class_id'))
                    <flux:button :href="route('admin.schedules.assignments', ['academic_year_id' => $year?->id])" wire:navigate>Tampilkan Semua Kelas</flux:button>
                @endif
            </form>
        </flux:card>

        @if(request()->boolean('create') || count($assignmentErrors) > 0)
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">Tambah Pengampu</flux:heading>
                    <flux:text class="mt-1">Satu mata pelajaran hanya boleh memiliki satu pengampu aktif pada kelas dan tahun yang sama.</flux:text>
                </div>
                <form method="POST" action="{{ route('admin.schedules.assignments.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:select name="school_class_id" label="Rombel" required>
                            <option value="">Pilih rombel</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}" @selected(old('school_class_id', request('class_id')) == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select name="gtk_id" label="Guru" required>
                            <option value="">Pilih guru</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected(old('gtk_id') == $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select name="subject_id" label="Mata Pelajaran" required>
                            <option value="">Pilih mata pelajaran</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:button :href="route('admin.schedules.assignments', ['academic_year_id' => $year?->id, 'class_id' => request('class_id')])" wire:navigate>Batal</flux:button>
                        <flux:button type="submit" variant="primary">Simpan Pengampu</flux:button>
                    </div>
                </form>
            </flux:card>
        @endif

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">Daftar Pengampu · {{ $year?->name ?: 'Tahun belum tersedia' }}</flux:heading>
                <flux:badge>{{ $assignments->count() }} penugasan</flux:badge>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="p-3">Kelas</th><th class="p-3">Mata Pelajaran</th><th class="p-3">Guru</th><th class="p-3">Status</th></tr></thead>
                    <tbody>
                        @forelse($assignments as $assignment)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="p-3">{{ $assignment->schoolClass->name }}</td>
                                <td class="p-3">{{ $assignment->subject->name }}</td>
                                <td class="p-3"><a class="underline underline-offset-4" href="{{ route('admin.gtks.show', $assignment->gtk) }}" wire:navigate>{{ $assignment->gtk->full_name }}</a></td>
                                <td class="p-3"><flux:badge :color="$assignment->is_active ? 'green' : 'zinc'">{{ $assignment->is_active ? 'Aktif' : 'Arsip' }}</flux:badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-center text-zinc-500">Belum ada pengampu pada pilihan ini. Klik Tambah Pengampu untuk menambahkan penugasan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    </div>
</x-layouts::app>
