<x-layouts::app title="Tambah Absensi">
<div class="mx-auto max-w-2xl space-y-6">
    <div><flux:heading size="xl">Tambah Absensi</flux:heading><flux:text class="mt-1">{{ auth()->user()->gtk->full_name }} · Pengajuan akan diperiksa oleh kepala madrasah.</flux:text></div>
    @include('gtk.absences.messages')
    <form method="POST" action="{{ route('gtk.absences.store') }}" enctype="multipart/form-data" class="space-y-5 rounded-xl border border-zinc-200 p-5 sm:p-6 dark:border-zinc-700">
        @csrf
        <flux:select label="Jenis Absen" name="type" required>
            <option value="">Pilih jenis absen</option>
            @foreach(\App\Models\GtkAbsenceRequest::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $value }} — {{ $label }}</option>@endforeach
        </flux:select>
        <flux:textarea label="Keterangan Singkat" name="description" rows="3" maxlength="1000" required placeholder="Tuliskan alasan atau kegiatan yang menyebabkan ketidakhadiran.">{{ old('description') }}</flux:textarea>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input label="Tanggal Mulai" name="start_date" type="date" :value="old('start_date', $date)" required />
            <flux:input label="Tanggal Akhir" name="end_date" type="date" :value="old('end_date', $date)" required />
        </div>
        <flux:text>Tanggal mulai dan akhir ikut dihitung. Untuk satu hari, isi kedua tanggal dengan tanggal yang sama. Rentang tidak boleh bertumpang tindih dengan pengajuan aktif atau presensi yang sudah tercatat.</flux:text>
        <flux:input label="Bukti" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf" required description="JPG, PNG, atau PDF. Maksimal 500 KB. Contoh: surat dokter, surat izin, atau surat tugas." />
        @if($errors->any())<flux:text>Pilih ulang berkas bukti sebelum mengirim kembali.</flux:text>@endif
        <div class="flex flex-wrap gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:button type="submit" variant="primary">Kirim Pengajuan</flux:button>
            <flux:button :href="route('gtk.absences.index')">Kembali</flux:button>
        </div>
    </form>
</div>
</x-layouts::app>
