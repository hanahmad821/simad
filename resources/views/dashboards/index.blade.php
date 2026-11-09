<x-layouts::app :title="$title">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ $title }}</flux:heading>
                <flux:text class="mt-1">Selamat datang, {{ auth()->user()->name }}. Berikut ringkasan aktivitas Anda.</flux:text>
            </div>
            <flux:badge color="blue">{{ today()->locale('id')->translatedFormat('l, d F Y') }}</flux:badge>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($stats as $stat)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ $stat['label'] }}</flux:text>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">{{ number_format($stat['value'], 0, ',', '.') }}</div>
                </div>
            @endforeach
        </div>

        <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">Akses Cepat</flux:heading>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ($links as $link)
                    <flux:button :href="route($link['route'])" wire:navigate>{{ $link['label'] }}</flux:button>
                @endforeach
            </div>
        </section>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Presensi Saya Hari Ini</flux:heading>
                @if (! auth()->user()->gtk_id)
                    <flux:text class="mt-3">Akun Anda belum terhubung dengan data GTK. Hubungi administrator untuk menghubungkannya.</flux:text>
                @else
                    @php $classification = app(\App\Services\GtkAttendancePolicyService::class)->evaluate(today(), $attendance); @endphp
                    <div class="mt-3">
                        <flux:badge :color="$attendance?->check_in_at ? 'green' : 'yellow'">
                            {{ $classification['label'] }}
                        </flux:badge>
                    </div>
                    <x-gtk-attendance-indicators :classification="$classification" />
                    <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-zinc-500">Jam masuk</dt><dd class="mt-1 font-medium">{{ $attendance?->check_in_at?->format('H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Jam pulang</dt><dd class="mt-1 font-medium">{{ $attendance?->check_out_at?->format('H:i') ?? '—' }}</dd></div>
                    </dl>
                    <flux:button class="mt-4" :href="route('gtk.attendance.index')" wire:navigate>Buka Presensi</flux:button>
                @endif
            </section>

            <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">Disposisi yang Perlu Ditindaklanjuti</flux:heading>
                    <flux:link :href="route('gtk.dispositions.index')" wire:navigate>Semua</flux:link>
                </div>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($dispositions as $disposition)
                        <a href="{{ route('gtk.dispositions.show', $disposition) }}" wire:navigate class="block rounded p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div class="font-medium">{{ $disposition->incomingLetter?->perihal ?? 'Surat tidak tersedia' }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ match ($disposition->status) { 'pending' => 'Belum dibaca', 'read' => 'Sudah dibaca', 'in_progress' => 'Sedang diproses', default => $disposition->status } }}</div>
                            @if ($disposition->deadline)
                                <div class="mt-1 text-sm {{ $disposition->deadline->lt(today()) ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">Tenggat: {{ $disposition->deadline->format('d/m/Y') }}{{ $disposition->deadline->lt(today()) ? ' · Terlambat' : '' }}</div>
                            @endif
                        </a>
                    @empty
                        <flux:text class="py-4">Tidak ada disposisi yang perlu ditindaklanjuti.</flux:text>
                    @endforelse
                </div>
            </section>
        </div>

        @if (in_array($role, ['admin', 'headmaster']))
            <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Surat Menunggu Persetujuan</flux:heading>
                <flux:text class="mt-1">Surat terlama ditampilkan terlebih dahulu.</flux:text>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($letters as $letter)
                        <a href="{{ route($role.'.outgoing-letters.show', $letter) }}" wire:navigate class="flex flex-wrap items-center justify-between gap-2 rounded p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div><div class="font-medium">{{ $letter->perihal }}</div><div class="mt-1 text-sm text-zinc-500">{{ $letter->nomor_surat ?: 'Belum bernomor' }} · {{ $letter->tujuan }}</div></div>
                            <flux:badge color="yellow">Menunggu persetujuan</flux:badge>
                        </a>
                    @empty
                        <flux:text class="py-4">Tidak ada surat yang menunggu persetujuan.</flux:text>
                    @endforelse
                </div>
            </section>
        @endif

        @if (auth()->user()->canTeach())
            <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Jadwal Mengajar Hari Ini</flux:heading>
                <flux:text class="mt-1">Jadwal pada tahun pelajaran aktif.</flux:text>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($schedules as $schedule)
                        <div class="flex flex-wrap justify-between gap-3 py-4">
                            <div><div class="font-medium">{{ $schedule->teachingAssignment?->subject?->name ?? 'Mata pelajaran tidak tersedia' }}</div><div class="mt-1 text-sm text-zinc-500">{{ $schedule->teachingAssignment?->schoolClass?->name ?? '—' }} · {{ $schedule->room?->name ?? 'Ruang belum ditentukan' }}</div></div>
                            <flux:badge>{{ substr($schedule->lessonPeriod?->start_time ?? '', 0, 5) ?: '—' }} – {{ substr($schedule->lessonPeriod?->end_time ?? '', 0, 5) ?: '—' }}</flux:badge>
                        </div>
                    @empty
                        <flux:text class="py-4">{{ auth()->user()->gtk_id ? 'Tidak ada jadwal mengajar hari ini.' : 'Jadwal akan tampil setelah akun terhubung dengan data GTK.' }}</flux:text>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
