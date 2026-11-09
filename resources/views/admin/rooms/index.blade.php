<x-layouts::app title="Manajemen Ruangan">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">Manajemen Ruangan</flux:heading>
                <flux:text class="mt-1">Kelola ruang kelas, laboratorium, dan ruangan lain untuk penjadwalan.</flux:text>
            </div>
            <flux:button variant="primary" :href="route('admin.rooms.create')">Tambah Ruangan</flux:button>
        </div>

        @if (session('status'))
            <flux:callout variant="success">{{ session('status') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="warning">{{ $errors->first() }}</flux:callout>
        @endif

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input label="Cari ruangan" name="q" :value="$search" placeholder="Kode, nama, atau gedung" maxlength="100" />
            <flux:select label="Status" name="status">
                @foreach (['all' => 'Semua status', 'active' => 'Aktif', 'inactive' => 'Nonaktif'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Tampilkan</flux:button>
            <flux:button :href="route('admin.rooms.index')">Reset</flux:button>
        </form>
        <flux:text>Atur ruang bawaan setiap kelas melalui menu Kelas. Ruang tersebut akan dipilih otomatis saat membuat jadwal dan dapat diganti untuk kegiatan di ruangan lain.</flux:text>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-3">Kode / Ruangan</th><th class="p-3">Gedung</th><th class="p-3">Kapasitas</th><th class="p-3">Ruang Bawaan Kelas</th><th class="p-3">Status</th><th class="p-3">Tindakan</th></tr></thead>
                <tbody>
                    @forelse ($rooms as $room)
                        <tr class="border-b border-zinc-200 align-top dark:border-zinc-700">
                            <td class="p-3"><div class="font-medium">{{ $room->name }}</div><div class="text-zinc-500">{{ $room->code }}</div></td>
                            <td class="p-3">{{ $room->building ?: '—' }}</td>
                            <td class="p-3">{{ $room->capacity ? $room->capacity.' orang' : 'Belum ditentukan' }}</td>
                            <td class="p-3">
                                @forelse ($room->schoolClasses as $class)
                                    <a class="mr-2 underline" href="{{ route('admin.classes.edit', $class) }}">{{ $class->name }}</a>
                                @empty
                                    <span class="text-zinc-500">Belum ditetapkan</span>
                                @endforelse
                            </td>
                            <td class="p-3"><flux:badge :color="$room->is_active ? 'green' : 'zinc'">{{ $room->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge></td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" :href="route('admin.rooms.edit', $room)">Ubah</flux:button>
                                    @if ($room->school_classes_count === 0 && $room->schedules_count === 0)
                                        <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" onsubmit="return confirm('Hapus ruangan ini?')">
                                            @csrf @method('DELETE')
                                            <flux:button type="submit" size="sm" variant="danger">Hapus</flux:button>
                                        </form>
                                    @else
                                        <flux:text size="sm">Sedang digunakan. Nonaktifkan melalui Ubah.</flux:text>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center">Belum ada ruangan yang sesuai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $rooms->links() }}
    </div>
</x-layouts::app>
