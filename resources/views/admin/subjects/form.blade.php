<x-layouts::app :title="$subject->exists ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'">
    <div class="mx-auto max-w-3xl space-y-6">
        <flux:heading size="xl">{{ $subject->exists ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran' }}</flux:heading>
        @if ($errors->any())
            <flux:callout variant="warning">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </flux:callout>
        @endif
        <form method="POST" action="{{ $subject->exists ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}" class="space-y-4">
            @csrf
            @if ($subject->exists) @method('PUT') @endif
            <flux:input label="Kode mata pelajaran" name="code" :value="old('code', $subject->code)" required maxlength="30" />
            <flux:input label="Nama mata pelajaran" name="name" :value="old('name', $subject->name)" required maxlength="100" />
            <flux:input label="Kategori (opsional)" name="category" :value="old('category', $subject->category)" placeholder="Contoh: PAI, Umum, atau Muatan Lokal" maxlength="50" />
            <flux:select label="Status" name="is_active">
                <option value="1" @selected(old('is_active', $subject->is_active) == 1)>Aktif</option>
                <option value="0" @selected(old('is_active', $subject->is_active) == 0)>Nonaktif</option>
            </flux:select>
            <flux:text>Mata pelajaran nonaktif tidak tersedia untuk penugasan baru. Penugasan dan jadwal yang sudah ada tetap tersimpan.</flux:text>
            <div class="flex gap-3">
                <flux:button variant="primary" type="submit">Simpan Mata Pelajaran</flux:button>
                <flux:button :href="route('admin.subjects.index')">Kembali</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
