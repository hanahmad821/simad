<x-layouts::app :title="$room->exists ? 'Ubah Ruangan' : 'Tambah Ruangan'">
    <div class="mx-auto max-w-3xl space-y-6">
        <flux:heading size="xl">{{ $room->exists ? 'Ubah Ruangan' : 'Tambah Ruangan' }}</flux:heading>
        @if ($errors->any())
            <flux:callout variant="warning"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
        @endif
        <form method="POST" action="{{ $room->exists ? route('admin.rooms.update', $room) : route('admin.rooms.store') }}" class="space-y-4">
            @csrf
            @if ($room->exists) @method('PUT') @endif
            <flux:input label="Kode ruangan" name="code" :value="old('code', $room->code)" required maxlength="30" />
            <flux:input label="Nama ruangan" name="name" :value="old('name', $room->name)" required maxlength="100" />
            <flux:input label="Gedung (opsional)" name="building" :value="old('building', $room->building)" maxlength="100" />
            <flux:input label="Kapasitas (opsional)" name="capacity" type="number" min="1" max="65535" :value="old('capacity', $room->capacity)" />
            <flux:select label="Status" name="is_active">
                <option value="1" @selected(old('is_active', $room->is_active) == 1)>Aktif</option>
                <option value="0" @selected(old('is_active', $room->is_active) == 0)>Nonaktif</option>
            </flux:select>
            <flux:text>Ruangan nonaktif tidak tersedia untuk jadwal baru. Riwayat jadwal yang memakai ruangan ini tetap tersimpan.</flux:text>
            <div class="flex gap-3">
                <flux:button variant="primary" type="submit">Simpan Ruangan</flux:button>
                <flux:button :href="route('admin.rooms.index')">Kembali</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
