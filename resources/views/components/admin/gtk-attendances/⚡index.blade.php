<?php

use App\Models\Gtk;
use App\Models\GtkAttendance;
use App\Models\Holiday;
use App\Services\GtkAttendancePolicyService;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Livewire\WithPagination;

new class extends \Livewire\Component {
    use WithPagination;

    public string $date;

    public string $gtkId = '';

    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasActiveRole(['admin', 'staff']), 403);
    }

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function updatedDate(): void
    {
        $this->resetPage();
    }

    public function updatedGtkId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->gtkId = '';
        $this->status = '';

        $this->resetPage();
    }

    public function render()
    {
        /*
        |--------------------------------------------------------------------------
        | Semua GTK aktif
        |--------------------------------------------------------------------------
        */

        $gtks = Gtk::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Attendance hari ini
        |--------------------------------------------------------------------------
        */

        $todayAttendances = GtkAttendance::query()
            ->with('gtk')
            ->whereDate('attendance_date', $this->date)
            ->get()
            ->keyBy('gtk_id');

        $totalGtk = $gtks->count();

        $policy = app(GtkAttendancePolicyService::class);
        $context = $policy->context(Carbon::parse($this->date));
        $records = $todayAttendances->values();
        if ($this->date < today()->toDateString() && $context['is_workday']) {
            foreach ($gtks as $gtk) {
                if (! $todayAttendances->has($gtk->id)) {
                    $records->push((new GtkAttendance(['gtk_id' => $gtk->id, 'attendance_date' => $this->date, 'status' => 'absent']))->setRelation('gtk', $gtk));
                }
            }
        }
        $classifications = $records->mapWithKeys(fn ($row) => [$row->gtk_id => $policy->evaluate(Carbon::parse($this->date), $row, $context)]);
        $presentCount = $classifications->where('status', 'present')->count();

        $lateCount = $classifications->where('status', 'late')->count();
        $absentCount = $classifications->where('status', 'absent')->count();

        $leaveCount = $todayAttendances
            ->where('status', 'leave')
            ->count();

        $sickCount = $todayAttendances
            ->where('status', 'sick')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Belum presensi
        |--------------------------------------------------------------------------
        */

        $notAttendedGtks = $gtks->filter(function ($gtk) use ($todayAttendances) {
            return !$todayAttendances->has($gtk->id);
        });

        $notAttendedCount = $notAttendedGtks->count();

        /*
        |--------------------------------------------------------------------------
        | Data tabel
        |--------------------------------------------------------------------------
        */

        $filtered = $records->filter(function ($row) use ($classifications) {
            $classification = $classifications->get($row->gtk_id);
            return (! $this->gtkId || $row->gtk_id === (int) $this->gtkId)
                && (! $this->status || $classification['status'] === $this->status
                    || strtolower($classification['late_level'] ?? '') === $this->status
                    || strtolower($classification['early_level'] ?? '') === $this->status);
        })->sortBy(fn ($row) => ($row->check_in_at?->format('H:i:s') ?? '99:99:99').$row->gtk?->full_name)->values();
        $page = $this->getPage();
        $attendances = new LengthAwarePaginator($filtered->slice(($page - 1) * 15, 15)->values(), $filtered->count(), 15, $page,
            ['path' => route((auth()->user()->isAdmin() ? 'admin' : 'staff').'.gtk-attendances.index')]);

        /*
        |--------------------------------------------------------------------------
        | Hari libur
        |--------------------------------------------------------------------------
        */

        $holiday = Holiday::query()
            ->whereDate('date', $this->date)
            ->where('is_active', true)
            ->first();

        $isSunday = Carbon::parse($this->date)->isSunday();

        return $this->view([
            'attendances' => $attendances,
            'gtks' => $gtks,
            'notAttendedGtks' => $notAttendedGtks,

            'totalGtk' => $totalGtk,
            'presentCount' => $presentCount,
            'lateCount' => $lateCount,
            'absentCount' => $absentCount,
            'classifications' => $classifications,
            'leaveCount' => $leaveCount,
            'sickCount' => $sickCount,
            'notAttendedCount' => $notAttendedCount,

            'holiday' => $holiday,
            'isSunday' => $isSunday,
        ]);
    }

    private function duration(
        ?CarbonInterface $checkIn,
        ?CarbonInterface $checkOut
    ): ?string {
        if (!$checkIn || !$checkOut) {
            return null;
        }

        $minutes = $checkIn->diffInMinutes($checkOut);

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}j {$remainingMinutes}m";
    }
};
?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">
                Monitoring Presensi GTK
            </flux:heading>

            <flux:subheading>
                Pantau kehadiran GTK pada tanggal tertentu. Izin, cuti, dan sakit dicatat melalui pengajuan GTK setelah disetujui kepala madrasah.
            </flux:subheading>
        </div>
    </div>


    {{-- Statistik --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Sudah Presensi --}}
        <flux:card class="flex items-center gap-4">
            <flux:icon name="check-circle" variant="solid" class="size-8 text-green-600 dark:text-green-400" />

            <div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    Sudah Presensi
                </div>

                <div class="text-2xl font-semibold">
                    {{ $presentCount + $lateCount }}
                </div>

                <div class="text-xs text-zinc-500">
                    {{ $presentCount }} hadir · {{ $lateCount }} terlambat
                </div>
            </div>
        </flux:card>


        {{-- Belum Presensi --}}
        <flux:card class="flex items-center gap-4">
            <flux:icon name="clock" variant="solid" class="size-8 text-yellow-600 dark:text-yellow-400" />

            <div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    Belum Presensi
                </div>

                <div class="text-2xl font-semibold">
                    {{ $notAttendedCount }}
                </div>

                <div class="text-xs text-zinc-500">
                    dari {{ $totalGtk }} GTK aktif
                </div>
            </div>
        </flux:card>


        {{-- Izin / Cuti --}}
        <flux:card class="flex items-center gap-4">
            <flux:icon name="calendar-days" variant="solid" class="size-8 text-blue-600 dark:text-blue-400" />

            <div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    Izin / Cuti
                </div>

                <div class="text-2xl font-semibold">
                    {{ $leaveCount }}
                </div>

                <div class="text-xs text-zinc-500">
                    GTK
                </div>
            </div>
        </flux:card>


        {{-- Sakit --}}
        <flux:card class="flex items-center gap-4">
            <flux:icon name="heart" variant="solid" class="size-8 text-orange-600 dark:text-orange-400" />

            <div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    Sakit
                </div>

                <div class="text-2xl font-semibold">
                    {{ $sickCount }}
                </div>

                <div class="text-xs text-zinc-500">
                    GTK
                </div>
            </div>
        </flux:card>

    </div>


    {{-- Filter --}}
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <div class="grid gap-4 md:grid-cols-4">

            <flux:input type="date" label="Tanggal" wire:model.live="date" />

            <flux:select label="GTK" wire:model.live="gtkId">
                <flux:select.option value="">
                    Semua GTK
                </flux:select.option>

                @foreach ($gtks as $gtk)
                    <flux:select.option value="{{ $gtk->id }}">
                        {{ $gtk->full_name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Status" wire:model.live="status">
                <flux:select.option value="">
                    Semua Status
                </flux:select.option>

                <flux:select.option value="present">
                    Hadir
                </flux:select.option>

                <flux:select.option value="late">
                    Terlambat
                </flux:select.option>

                <flux:select.option value="absent">
                    Alpa
                </flux:select.option>
                @foreach (['t1' => 'T1', 't2' => 'T2', 't3' => 'T3', 'psw1' => 'PSW1', 'psw2' => 'PSW2', 'psw3' => 'PSW3'] as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach

                <flux:select.option value="leave">
                    Izin / Cuti
                </flux:select.option>

                <flux:select.option value="sick">
                    Sakit
                </flux:select.option>
            </flux:select>

            <div class="flex items-end">
                <flux:button variant="ghost" icon="arrow-path" wire:click="resetFilters">
                    Reset
                </flux:button>
            </div>

        </div>
    </div>


    {{-- Informasi hari libur --}}
    <flux:callout icon="clock">
        Presensi dihitung otomatis tanpa approval. T1 / PSW1: sampai 30 menit; T2 / PSW2: lebih dari 30 sampai 60 menit; T3 / PSW3: lebih dari 60 menit. Lupa pulang: PSW3. Alpa: {{ $absentCount }} GTK.
        <div class="mt-2 flex flex-wrap gap-3 text-sm">
            @foreach (['T1', 'T2', 'T3'] as $level)<span>{{ $level }}: {{ $classifications->where('late_level', $level)->count() }}</span>@endforeach
            @foreach (['PSW1', 'PSW2', 'PSW3'] as $level)<span>{{ $level }}: {{ $classifications->where('early_level', $level)->count() }}</span>@endforeach
        </div>
    </flux:callout>
    @if ($holiday || $isSunday)
        <flux:callout variant="warning" icon="calendar-days">
            @if ($holiday)
                <strong>{{ $holiday->name }}</strong>
                <span>
                    — tanggal ini ditetapkan sebagai hari libur.
                </span>
            @elseif ($isSunday)
                Hari Minggu merupakan hari libur.
            @endif
        </flux:callout>
    @endif


    {{-- Belum Presensi --}}
    @if ($notAttendedCount > 0 && !$holiday && !$isSunday)
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-5 dark:border-yellow-900/50 dark:bg-yellow-900/10">

            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">
                        {{ $date < today()->toDateString() ? 'Alpa / Tanpa Presensi' : 'Belum Presensi' }}
                    </flux:heading>

                    <flux:subheading>
                        {{ $notAttendedCount }} GTK belum melakukan presensi pada tanggal ini.
                    </flux:subheading>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

                @foreach ($notAttendedGtks as $gtk)
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-yellow-200 bg-white p-3 dark:border-yellow-900/50 dark:bg-zinc-800">

                        <div class="min-w-0">
                            <div class="truncate font-medium">
                                {{ $gtk->full_name }}
                            </div>

                            @if ($gtk->position)
                                <div class="truncate text-xs text-zinc-500">
                                    {{ $gtk->position }}
                                </div>
                            @endif
                        </div>

                    </div>
                @endforeach

            </div>
        </div>
    @endif


    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            GTK
                        </th>

                        <th class="px-4 py-3 text-left">
                            Check In
                        </th>

                        <th class="px-4 py-3 text-left">
                            Check Out
                        </th>

                        <th class="px-4 py-3 text-left">
                            Jumlah Jam
                        </th>

                        <th class="px-4 py-3 text-center">
                            Status
                        </th>

                        <th class="px-4 py-3 text-left">
                            Catatan
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">

                    @forelse ($attendances as $attendance)

                        @php
                            $duration = $this->duration(
                                $attendance->check_in_at,
                                $attendance->check_out_at
                            );

                            $classification = $classifications->get($attendance->gtk_id);
                            $statusColor = match ($classification['status']) {
                                'present' => 'green',
                                'late' => 'yellow',
                                'absent' => 'red',
                                'leave' => 'blue',
                                'sick' => 'orange',
                                default => 'zinc',
                            };

                            $statusLabel = $classification['label'];
                        @endphp

                        <tr wire:key="attendance-{{ $attendance->gtk_id }}">

                            {{-- GTK --}}
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $attendance->gtk->full_name }}
                                </div>

                                @if ($attendance->gtk->position)
                                    <div class="text-xs text-zinc-500">
                                        {{ $attendance->gtk->position }}
                                    </div>
                                @endif
                            </td>

                            {{-- Check In --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $attendance->check_in_at?->format('H:i') ?? '-' }}
                            </td>

                            {{-- Check Out --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $attendance->check_out_at?->format('H:i') ?? '-' }}
                            </td>

                            {{-- Jumlah Jam --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $duration ?? '-' }}
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3 text-center">
                                <flux:badge :color="$statusColor">
                                    {{ $statusLabel }}
                                </flux:badge>
                                <x-gtk-attendance-indicators :classification="$classification" />
                            </td>

                            {{-- Catatan --}}
                            <td class="px-4 py-3">
                                {{ $attendance->notes ?: '-' }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center">
                                <div class="text-zinc-500">
                                    Tidak ada data presensi pada tanggal ini.
                                </div>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- Pagination --}}
    {{ $attendances->links() }}


</div>
