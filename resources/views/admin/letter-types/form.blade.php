<x-layouts::app :title="$letterType->exists ? 'Edit Jenis Surat' : 'Tambah Jenis Surat'">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <flux:heading size="xl">{{ $letterType->exists ? 'Edit Jenis Surat' : 'Tambah Jenis Surat' }}</flux:heading>
            <flux:text class="mt-1">Pilih format surat baku. Kolom isian surat mengikuti template yang dipilih.</flux:text>
        </div>
        @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="warning"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
        @endif
        @if ($letterType->exists && ($letterType->body_html !== null || ($letterType->template_view && ! isset($templates[$letterType->template_view]))))
            <flux:callout variant="warning">Pilih template baku untuk pembuatan surat berikutnya. Surat yang sudah dibuat tetap memakai pilihan template sebelumnya.</flux:callout>
        @endif
        @php
            $selectedTemplate = old('template_view', $letterType->body_html === null ? $letterType->template_view : '');
            if (! isset($templates[$selectedTemplate ?? ''])) $selectedTemplate = '';
        @endphp
        <form method="POST" action="{{ $letterType->exists ? route('admin.letter-types.update', $letterType) : route('admin.letter-types.store') }}"
            x-data="{ selectedTemplate: @js($selectedTemplate ?? '') }" class="space-y-5">
            @csrf
            @if ($letterType->exists) @method('PUT') @endif
            <flux:input label="Nama jenis surat" name="name" :value="old('name', $letterType->name)" maxlength="150" placeholder="Contoh: Surat Keterangan untuk Beasiswa" required />
            <flux:input label="Kode klasifikasi surat" name="code" :value="old('code', $letterType->code)" maxlength="10" placeholder="Contoh: A" required />
            <flux:text size="sm">Kode klasifikasi digunakan dalam nomor surat. Beberapa jenis surat boleh memakai kode klasifikasi yang sama.</flux:text>
            <flux:select label="Template surat" name="template_view" x-model="selectedTemplate">
                <option value="" @selected(! $selectedTemplate)>Tanpa template (surat manual / unggah PDF)</option>
                @foreach ($templates as $view => $template)
                    <option value="{{ $view }}" @selected($selectedTemplate === $view)>{{ $template['name'] }}</option>
                @endforeach
            </flux:select>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div x-show="!selectedTemplate">
                    <flux:text>Jenis surat ini digunakan untuk booking nomor dan mengunggah PDF yang dibuat di luar aplikasi.</flux:text>
                </div>
                @foreach ($templates as $view => $template)
                    <div x-show="selectedTemplate === @js($view)" x-cloak class="space-y-2">
                        <flux:heading>{{ $template['name'] }}</flux:heading>
                        <flux:text>{{ $template['description'] }}</flux:text>
                        <flux:text size="sm" class="font-medium">Isian khusus saat membuat surat:</flux:text>
                        <ul class="list-disc space-y-1 pl-5 text-sm">
                            @foreach ($template['fields'] as $field)
                                <li>{{ $field['label'] }}{{ $field['required'] ? ' (wajib)' : '' }}</li>
                            @endforeach
                            @if ($template['uses_body'])<li>Isi surat (wajib)</li>@endif
                        </ul>
                        <flux:text size="sm">Nomor surat, tujuan, tanggal, perihal, dan tanda tangan dikelola oleh aplikasi.</flux:text>
                    </div>
                @endforeach
            </div>
            <flux:select label="Status" name="is_active">
                <option value="1" @selected(old('is_active', $letterType->is_active) == 1)>Aktif</option>
                <option value="0" @selected(old('is_active', $letterType->is_active) == 0)>Nonaktif</option>
            </flux:select>
            <flux:text>Jenis surat aktif tersedia saat membuat surat keluar. Jenis surat nonaktif tetap tersimpan bersama arsipnya.</flux:text>
            <div class="flex gap-3">
                <flux:button variant="primary" type="submit" :loading="false">Simpan Jenis Surat</flux:button>
                <flux:button :href="route('admin.letter-types.index')">Kembali</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
