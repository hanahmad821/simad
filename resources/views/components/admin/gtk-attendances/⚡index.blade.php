<?php

use App\Models\Gtk;
use App\Models\GtkAttendance;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Livewire\WithPagination;

new class extends \Livewire\Component {
    use WithPagination;

    public string $date;

    public string $gtkId = '';

    public string $status = '';

    // Modal update status
    public bool $showStatusModal = false;

    public string $statusGtkId = '';

    public string $statusValue = 'leave';

    public string $statusNotes = '';

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

    /*
    |--------------------------------------------------------------------------
    | Update Status
    |--------------------------------------------------------------------------
    */

    public function openStatusModal(int $gtkId, string $status = 'leave'): void
    {
        $this->statusGtkId = (string) $gtkId;
        $this->statusValue = $status;
        $this->statusNotes = '';
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
        $this->statusGtkId = '';
        $this->statusValue = 'leave';
        $this->statusNotes = '';
    }

    public function updateStatus(): void
    {
        $this->validate([
            'statusGtkId' => ['required', 'exists:gtks,id'],
            'statusValue' => ['required', 'in:leave,sick'],
            'statusNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $gtk = Gtk::query()
            ->where('is_active', true)
            ->findOrFail($this->statusGtkId);

        $attendance = GtkAttendance::firstOrNew([
            'gtk_id' => $gtk->id,
            'attendance_date' => $this->date,
        ]);

        $attendance->status = $this->statusValue;
        $attendance->notes = $this->statusNotes ?: null;

        $attendance->save();

        $this->closeStatusModal();

        session()->flash(
            'success',
            "Status {$gtk->full_name} berhasil diperbarui."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Remove Status
    |--------------------------------------------------------------------------
    */

    public function resetAttendanceStatus(int $gtkId): void
    {
        $attendance = GtkAttendance::query()
            ->where('gtk_id', $gtkId)
            ->whereDate('attendance_date', $this->date)
            ->first();

        if (!$attendance) {
            return;
        }

        $attendance->delete();

        session()->flash(
            'success',
            'Status presensi berhasil dihapus.'
        );
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
            ->whereDate('attendance_date', $this->date)
            ->get()
            ->keyBy('gtk_id');

        $totalGtk = $gtks->count();

        $presentCount = $todayAttendances
            ->where('status', 'present')
            ->count();

        $lateCount = $todayAttendances
            ->where('status', 'late')
            ->count();

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

        $attendances = GtkAttendance::query()
            ->with('gtk')
            ->whereDate('attendance_date', $this->date)
            ->when(
                $this->gtkId,
                fn($query) => $query->where('gtk_id', $this->gtkId)
            )
            ->when(
                $this->status,
                fn($query) => $query->where('status', $this->status)
            )
            ->orderByRaw("
                CASE
                    WHEN check_in_at IS NULL THEN 1
                    ELSE 0
                END
            ")
            ->orderBy('check_in_at')
            ->paginate(15);

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
                Pantau kehadiran GTK pada tanggal tertentu.
            </flux:subheading>
        </div>
    </div>


    {{-- Flash message --}}
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle" dismissible>
            {{ session('success') }}
        </flux:callout>
    @endif


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
                    Tidak Hadir
                </flux:select.option>

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
                        Belum Presensi
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

                        <flux:button size="sm" variant="ghost" icon="calendar"
                            wire:click="openStatusModal({{ $gtk->id }}, 'leave')">
                            Status
                        </flux:button>

                    </div>
                @endforeach

            </div>
        </div>
    @endif


    {{-- Tombol update status --}}
    @if (!$holiday && !$isSunday)
        <div class="flex justify-end">
            <flux:button variant="primary" icon="calendar" wire:click="openStatusModal(0, 'leave')">
                Update Izin / Cuti / Sakit
            </flux:button>
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

                            $statusColor = match ($attendance->status) {
                                'present' => 'green',
                                'late' => 'yellow',
                                'absent' => 'red',
                                'leave' => 'blue',
                                'sick' => 'orange',
                                default => 'zinc',
                            };

                            $statusLabel = match ($attendance->status) {
                                'present' => 'Hadir',
                                'late' => 'Terlambat',
                                'absent' => 'Tidak Hadir',
                                'leave' => 'Izin / Cuti',
                                'sick' => 'Sakit',
                                default => ucfirst($attendance->status),
                            };
                        @endphp

                        <tr wire:key="attendance-{{ $attendance->id }}">

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


    {{-- Modal Update Status --}}
    <flux:modal wire:model="showStatusModal" class="md:w-[500px]">
        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    Update Status Presensi
                </flux:heading>

                <flux:subheading>
                    Atur status izin, cuti, atau sakit untuk GTK.
                </flux:subheading>
            </div>


            <flux:select label="GTK" wire:model="statusGtkId">
                <flux:select.option value="">
                    Pilih GTK
                </flux:select.option>

                @foreach ($gtks as $gtk)
                    <flux:select.option value="{{ $gtk->id }}">
                        {{ $gtk->full_name }}
                    </flux:select.option>
                @endforeach
            </flux:select>


            <flux:select label="Status" wire:model="statusValue">
                <flux:select.option value="leave">
                    Izin / Cuti
                </flux:select.option>

                <flux:select.option value="sick">
                    Sakit
                </flux:select.option>
            </flux:select>


            <flux:textarea label="Catatan" wire:model="statusNotes" placeholder="Contoh: Izin keperluan keluarga..."
                rows="3" />


            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeStatusModal">
                    Batal
                </flux:button>

                <flux:button variant="primary" wire:click="updateStatus" wire:loading.attr="disabled">
                    Simpan
                </flux:button>
            </div>

        </div>
    </flux:modal>

</div>
