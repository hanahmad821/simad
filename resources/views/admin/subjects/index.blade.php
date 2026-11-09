<x-layouts::app title="Mata Pelajaran">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">Mata Pelajaran</flux:heading>
                <flux:text class="mt-1">Kelola mata pelajaran dan lihat guru pengampu berdasarkan tahun pelajaran.</flux:text>
            </div>
            <flux:button variant="primary" :href="route('admin.subjects.create')">Tambah Mata Pelajaran</flux:button>
        </div>

        @if (session('status'))
            <flux:callout variant="success">{{ session('status') }}</flux:callout>
        @endif

        <form method="GET" action="{{ route('admin.subjects.index') }}" class="grid gap-4 sm:grid-cols-3 sm:items-end">
            <flux:input label="Cari mata pelajaran" name="q" :value="$search" placeholder="Nama atau kode pelajaran" maxlength="100" />
            <flux:select label="Tahun pelajaran" name="academic_year_id">
                <option value="">Tahun pelajaran aktif</option>
                @foreach ($academicYears as $item)
                    <option value="{{ $item->id }}" @selected($academicYear?->id === $item->id)>{{ $item->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Tampilkan</flux:button>
                <flux:button :href="route('admin.subjects.index')">Reset</flux:button>
            </div>
        </form>

        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>
        @endif
        @if (! $academicYear)
            <flux:text>Belum ada tahun pelajaran aktif. Daftar mata pelajaran tetap ditampilkan; pilih tahun pelajaran untuk melihat pengampunya.</flux:text>
        @else
            <flux:text>Pengampu tahun pelajaran {{ $academicYear->name }}. Penugasan pengampu diatur melalui menu Kelas.</flux:text>
        @endif

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th scope="col" class="p-3">Kode</th>
                        <th scope="col" class="p-3">Mata Pelajaran</th>
                        <th scope="col" class="p-3">Kategori</th>
                        <th scope="col" class="p-3">Guru Pengampu / Kelas</th>
                        <th scope="col" class="p-3">Status</th>
                        <th scope="col" class="p-3">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr class="border-b border-zinc-200 align-top dark:border-zinc-700">
                            <td class="p-3">{{ $subject->code }}</td>
                            <td class="p-3 font-medium">{{ $subject->name }}</td>
                            <td class="p-3">{{ $subject->category ?: '—' }}</td>
                            <td class="p-3">
                                <ul class="space-y-2">
                                    @forelse ($subject->teachingAssignments->groupBy('gtk_id') as $assignments)
                                        <li>
                                            <span class="font-medium">{{ $assignments->first()->gtk?->full_name ?: 'Guru tidak tersedia' }}</span>
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                @foreach ($assignments->unique('school_class_id')->sortBy('schoolClass.name') as $assignment)
                                                    @if ($assignment->schoolClass)
                                                        <a class="text-green-700 underline dark:text-green-400" href="{{ route('admin.classes.show', [$assignment->schoolClass, 'academic_year_id' => $academicYear->id]) }}">{{ $assignment->schoolClass->name }}</a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </li>
                                    @empty
                                        <li class="text-zinc-500 dark:text-zinc-400">{{ $academicYear ? 'Belum ada pengampu pada tahun pelajaran ini' : 'Pilih tahun pelajaran untuk melihat pengampu' }}</li>
                                    @endforelse
                                </ul>
                            </td>
                            <td class="p-3"><flux:badge :color="$subject->is_active ? 'green' : 'zinc'">{{ $subject->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge></td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" :href="route('admin.subjects.edit', $subject)">Edit</flux:button>
                                    @if ($subject->teaching_assignments_count === 0)
                                        <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" onsubmit="return confirm('Hapus mata pelajaran ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <flux:button type="submit" size="sm" variant="danger">Hapus</flux:button>
                                        </form>
                                    @else
                                        <flux:text size="sm">Sudah digunakan. Nonaktifkan melalui Edit.</flux:text>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center">{{ $search !== '' ? 'Tidak ada mata pelajaran yang sesuai dengan pencarian.' : 'Belum ada data mata pelajaran.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $subjects->links() }}
    </div>
</x-layouts::app>
