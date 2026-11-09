<x-layouts::app title="Jurnal Mengajar">
    <div class="space-y-6">
        <div>
            <flux:heading size="xl">Jurnal Mengajar</flux:heading>
            <flux:text>Lengkapi dan simpan presensi seluruh siswa pada sesi pembelajaran terlebih dahulu, kemudian isi uraian materi, catatan, dan refleksi.</flux:text>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input type="date" name="date" label="Tanggal" :value="$date->toDateString()" :max="today()->toDateString()" required />
            <flux:button type="submit">Tampilkan</flux:button>
            <flux:button :href="route('gtk.attendance.index')">Presensi Saya</flux:button>
            <flux:button :href="route('gtk.journals.history', ['month' => $date->format('Y-m')])">Riwayat Jurnal Mengajar</flux:button>
        </form>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>{{ $requirements['missing']->isEmpty() ? 'Jurnal sesi pembelajaran sudah lengkap' : 'Jurnal sesi pembelajaran belum lengkap' }}</flux:heading>
            <flux:text>Jurnal mengajar: {{ $requirements['sessions']->count() - $requirements['missing']->count() }} dari {{ $requirements['sessions']->count() }} sesi selesai.</flux:text>
            @foreach ($requirements['missing'] as $missing)
                <a class="mt-2 block text-green-700 underline dark:text-green-400" href="#{{ $missing['key'] }}">Lengkapi {{ $missing['label'] }}</a>
            @endforeach
        </div>
        @forelse ($requirements['sessions'] as $session)
            @php($journal = $requirements['journals']->get($session['key']))
            @php($retry = old('session_key') === $session['key'])
            @php($progress = $studentAttendance->get($session['key']))
            <form id="{{ $session['key'] }}" method="POST" action="{{ route('gtk.journals.store') }}" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="hidden" name="session_key" value="{{ $session['key'] }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading>{{ $session['label'] }}</flux:heading>
                    <flux:badge :color="$journal?->isComplete() ? 'green' : 'zinc'">{{ $journal?->isComplete() ? 'Selesai' : 'Draf / belum diisi' }}</flux:badge>
                </div>
                <flux:text>Presensi siswa: {{ $progress['recorded'] }}/{{ $progress['total'] }} siswa tercatat.</flux:text>
                @if (! $progress['complete'])
                    <flux:callout variant="warning">{{ $progress['total'] === 0 ? 'Daftar siswa kelas belum tersedia. Hubungi administrator untuk melengkapi data kelas sebelum melakukan presensi.' : 'Jurnal terkunci. Lengkapi dan simpan presensi seluruh siswa untuk sesi dan tanggal ini terlebih dahulu.' }}</flux:callout>
                @endif
                <flux:button :href="route('teacher.student-attendances.index', ['date' => $date->toDateString(), 'schedule' => $session['schedule_ids'][0]])">{{ $progress['complete'] ? 'Lihat Presensi Siswa' : 'Isi Presensi Siswa' }}</flux:button>
                <fieldset @disabled(! $progress['complete']) class="space-y-4 disabled:opacity-50">
                <flux:textarea label="Uraian materi" name="material" rows="3" maxlength="20000">{{ $retry ? old('material') : $journal?->material }}</flux:textarea>
                <flux:textarea label="Catatan" name="notes" rows="2" maxlength="20000" description="Jika tidak ada, isi: Tidak ada catatan.">{{ $retry ? old('notes') : $journal?->notes }}</flux:textarea>
                <flux:textarea label="Refleksi singkat" name="reflection" rows="2" maxlength="20000">{{ $retry ? old('reflection') : $journal?->reflection }}</flux:textarea>
                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" name="status" value="draft">Simpan draf</flux:button>
                    <flux:button type="submit" name="status" value="completed" variant="primary">Selesaikan jurnal</flux:button>
                </div>
                </fieldset>
            </form>
            <x-gtk-journal-delete :journal="$journal" :approved="$approved" />
        @empty
            <flux:text>Tidak ada kewajiban jurnal mengajar pada tanggal ini.</flux:text>
        @endforelse
        @foreach ($exceptions as $exception)
            <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                <strong>{{ $exception->label }} — {{ $exception->substitute_gtk_id ? 'Dialihkan ke guru pengganti' : 'KBM dibatalkan' }}</strong>
                <p class="mt-2">Alasan: {{ $exception->reason }}</p>
            </div>
        @endforeach
    </div>
</x-layouts::app>
