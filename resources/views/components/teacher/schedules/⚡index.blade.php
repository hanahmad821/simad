<?php

use App\Services\TeacherScheduleService;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    #[Url]
    public string $day = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canTeach(), 403);
    }

    public function showToday(): void
    {
        $this->day = (string) today()->isoWeekday();
    }

    public function showWeek(): void
    {
        $this->day = '';
    }

    public function render()
    {
        $sessions = app(TeacherScheduleService::class)->sessions(auth()->user());
        $days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $validDay = $this->day === '' || (ctype_digit($this->day) && isset($days[(int) $this->day]));

        return $this->view([
            'sessionsByDay' => $sessions->groupBy(fn ($session) => $session->schedules->first()->day_of_week),
            'days' => $days,
            'visibleDays' => $validDay ? ($this->day === '' ? array_slice($days, 0, 6, true) : [(int) $this->day => $days[(int) $this->day]]) : [],
            'validDay' => $validDay,
            'todayNumber' => today()->isoWeekday(),
            'summary' => [
                'today' => $sessions->filter(fn ($session) => $session->schedules->first()->day_of_week === today()->isoWeekday())->count(),
                'sessions' => $sessions->count(),
                'periods' => $sessions->sum(fn ($session) => $session->schedules->count()),
                'classes' => $sessions->pluck('teachingAssignment.school_class_id')->unique()->count(),
            ],
        ]);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Jadwal Mengajar Saya</flux:heading>
            <flux:text class="mt-1">Jadwal mingguan Anda pada tahun pelajaran aktif. Pelajaran beberapa JP berurutan tampil sebagai satu sesi.</flux:text>
        </div>
        <flux:button icon="qr-code" :href="route('teacher.student-attendances.index')" wire:navigate>Buka Presensi Siswa</flux:button>
    </div>

    @if (! auth()->user()->gtk_id)
        <flux:callout variant="warning">Akun Anda belum terhubung dengan data GTK. Hubungi administrator agar jadwal mengajar dapat ditampilkan.</flux:callout>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['today' => 'Sesi hari ini', 'sessions' => 'Sesi per minggu', 'periods' => 'JP per minggu', 'classes' => 'Kelas diajar'] as $key => $label)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ $label }}</flux:text><div class="mt-1 text-2xl font-semibold">{{ $summary[$key] }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:select label="Tampilkan hari" wire:model.live="day" class="w-full sm:w-56">
            <option value="">Semua hari (Senin–Sabtu)</option>
            @foreach ($days as $number => $name)<option value="{{ $number }}">{{ $name }}</option>@endforeach
        </flux:select>
        <flux:button icon="calendar-days" wire:click="showToday">Hari ini</flux:button>
        <flux:button variant="ghost" wire:click="showWeek">Semua hari</flux:button>
        <flux:text class="w-full">Ringkasan menunjukkan jadwal seminggu penuh; pilihan hari hanya menyaring daftar di bawah.</flux:text>
    </div>

    @if (! $validDay)
        <flux:callout variant="warning">Pilihan hari tidak valid. Pilih hari dari daftar atau tekan Semua hari.</flux:callout>
    @elseif ($summary['sessions'] === 0)
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
            <flux:heading>Belum ada jadwal mengajar aktif</flux:heading>
            <flux:text class="mt-2">Jadwal akan tampil setelah administrator mengatur penugasan dan jadwal pada tahun pelajaran aktif.</flux:text>
        </div>
    @else
        <div class="grid items-start gap-5 xl:grid-cols-2" wire:loading.class="opacity-60">
            @foreach ($visibleDays as $number => $name)
                <section wire:key="teaching-day-{{ $number }}" @class([
                    'overflow-hidden rounded-xl border',
                    'border-blue-400 dark:border-blue-600' => $number === $todayNumber,
                    'border-zinc-200 dark:border-zinc-700' => $number !== $todayNumber,
                ])>
                    <div @class([
                        'flex items-center justify-between gap-3 border-b p-4',
                        'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/40' => $number === $todayNumber,
                        'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' => $number !== $todayNumber,
                    ])>
                        <flux:heading size="lg">{{ $name }}</flux:heading>
                        @if ($number === $todayNumber)<flux:badge color="blue">Hari ini · {{ today()->format('d/m/Y') }}</flux:badge>@endif
                    </div>
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($sessionsByDay->get($number, collect()) as $session)
                            <article wire:key="teaching-session-{{ $session->id }}" class="space-y-3 p-4">
                                @if ($number === $todayNumber)
                                    <flux:button icon="book-open" :href="route('gtk.journals.index', ['date' => today()->toDateString()]).'#kbm-'.$session->id" wire:navigate>Jurnal Sesi Pembelajaran</flux:button>
                                @endif
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <div class="font-semibold">{{ $session->teachingAssignment->subject?->name ?? 'Mata pelajaran tidak tersedia' }}</div>
                                        <flux:text>{{ $session->teachingAssignment->schoolClass?->name ?? 'Kelas tidak tersedia' }}</flux:text>
                                    </div>
                                    <flux:badge color="blue">{{ $session->schedules->count() }} JP</flux:badge>
                                </div>
                                <div class="text-sm font-semibold">{{ substr($session->lesson_periods->first()->start_time, 0, 5) }} – {{ substr($session->lesson_periods->last()->end_time, 0, 5) }}</div>
                                <flux:text>{{ $session->lesson_periods->first()->name }}@if($session->schedules->count() > 1) – {{ $session->lesson_periods->last()->name }}@endif</flux:text>
                                <flux:text>Ruang: {{ $session->schedules->pluck('room.name')->filter()->unique()->join(', ') ?: 'Belum ditentukan' }}</flux:text>
                                @if ($session->segments->count() > 1)
                                    <flux:text size="sm">Pelajaran berlanjut setelah istirahat. Waktu mengajar: {{ $session->segments->map(fn ($segment) => substr($segment->schedules->first()->lessonPeriod->start_time, 0, 5).' – '.substr($segment->schedules->last()->lessonPeriod->end_time, 0, 5))->join(', ') }}.</flux:text>
                                @endif
                                <flux:text size="sm">{{ $session->teachingAssignment->academicYear?->name }}</flux:text>
                            </article>
                        @empty
                            <flux:text class="p-4">Tidak ada jadwal mengajar pada hari {{ $name }}.</flux:text>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
