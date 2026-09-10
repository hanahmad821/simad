<?php

use App\Models\GtkAttendance;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Models\Holiday;
use Carbon\Carbon;

new class extends Component {
    public ?GtkAttendance $attendance = null;

    public string $locationMessage = '';
    public string $errorMessage = '';
    public array $monthDates = [];
    public array $holidays = [];

    public function mount(): void
    {
        $this->loadMonthAttendance();
        $gtk = Auth::user()->gtk;

        if (!$gtk) {
            $this->errorMessage = 'Akun Anda belum terhubung dengan data GTK.';
            return;
        }

        $this->attendance = GtkAttendance::query()
            ->where('gtk_id', $gtk->id)
            ->whereDate('attendance_date', today())
            ->first();
    }
    public function loadMonthAttendance(): void
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $this->monthDates = collect(
            Carbon::parse($start)->toPeriod($end)
        )->map(function ($date) {
            return [
                'date' => $date->format('Y-m-d'),
                'day' => $date->day,
                'day_name' => $date->translatedFormat('D'),
                'is_today' => $date->isToday(),
                'is_sunday' => $date->isSunday(),
            ];
        })->toArray();

        $this->holidays = Holiday::query()
            ->whereBetween('date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->where('is_active', true)
            ->get()
            ->keyBy(fn($holiday) => $holiday->date->format('Y-m-d'))
            ->toArray();
    }

    public function getMonthlyAttendancesProperty()
    {
        $gtk = auth()->user()->gtk;

        if (!$gtk) {
            return collect();
        }

        return \App\Models\GtkAttendance::query()
            ->where('gtk_id', $gtk->id)
            ->whereBetween('attendance_date', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->get()
            ->keyBy(fn($attendance) => $attendance->attendance_date->format('Y-m-d'));
    }

    public function checkIn(
        float $latitude,
        float $longitude,
        float $accuracy
    ): void {
        $this->saveAttendance(
            'check_in',
            $latitude,
            $longitude,
            $accuracy
        );
    }

    public function checkOut(
        float $latitude,
        float $longitude,
        float $accuracy
    ): void {
        $this->saveAttendance(
            'check_out',
            $latitude,
            $longitude,
            $accuracy
        );
    }

    private function saveAttendance(
        string $type,
        float $latitude,
        float $longitude,
        float $accuracy
    ): void {
        $gtk = Auth::user()->gtk;

        if (!$gtk) {
            $this->errorMessage = 'Akun Anda belum terhubung dengan data GTK.';
            return;
        }

        $schoolSetting = SchoolSetting::query()->first();

        if (!$schoolSetting) {
            $this->errorMessage = 'Pengaturan madrasah belum tersedia.';
            return;
        }

        $schoolLatitude = (float) $schoolSetting->latitude;
        $schoolLongitude = (float) $schoolSetting->longitude;
        $radius = (int) $schoolSetting->attendance_radius;

        if (!$schoolLatitude || !$schoolLongitude) {
            $this->errorMessage = 'Lokasi madrasah belum dikonfigurasi.';
            return;
        }

        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            $schoolLatitude,
            $schoolLongitude
        );

        if ($distance > $radius) {
            $this->errorMessage =
                'Anda berada di luar area presensi. '
                . 'Jarak Anda sekitar ' . round($distance) . ' meter '
                . '(maksimal ' . $radius . ' meter).';

            return;
        }

        $attendance = GtkAttendance::firstOrNew([
            'gtk_id' => $gtk->id,
            'attendance_date' => today(),
        ]);

        if ($type === 'check_in') {

            if ($attendance->check_in_at) {
                $this->errorMessage =
                    'Anda sudah melakukan Check In hari ini.';
                return;
            }

            $attendance->check_in_at = now();
            $attendance->check_in_latitude = $latitude;
            $attendance->check_in_longitude = $longitude;
            $attendance->check_in_accuracy = $accuracy;
            $attendance->status = 'present';

            $this->locationMessage =
                'Check In berhasil. Jarak Anda dari madrasah sekitar '
                . round($distance) . ' meter.';

        } else {

            if (!$attendance->check_in_at) {
                $this->errorMessage =
                    'Anda belum melakukan Check In.';
                return;
            }

            if ($attendance->check_out_at) {
                $this->errorMessage =
                    'Anda sudah melakukan Check Out hari ini.';
                return;
            }

            $attendance->check_out_at = now();
            $attendance->check_out_latitude = $latitude;
            $attendance->check_out_longitude = $longitude;
            $attendance->check_out_accuracy = $accuracy;

            $this->locationMessage =
                'Check Out berhasil. Jarak Anda dari madrasah sekitar '
                . round($distance) . ' meter.';
        }

        $attendance->save();

        $this->attendance = $attendance->fresh();

        $this->errorMessage = '';
    }

    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $lat1 = deg2rad($latitude1);
        $lat2 = deg2rad($latitude2);

        $deltaLat = deg2rad($latitude2 - $latitude1);
        $deltaLon = deg2rad($longitude2 - $longitude1);

        $a =
            sin($deltaLat / 2) ** 2
            + cos($lat1)
            * cos($lat2)
            * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
};
?>

<div x-data="gtkAttendance" class="space-y-6">
    <div>
        <flux:heading size="xl">
            Presensi GTK
        </flux:heading>

        <flux:text class="mt-1">
            Presensi masuk dan pulang menggunakan lokasi GPS.
        </flux:text>
    </div>

    @if ($errorMessage)
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ $errorMessage }}
        </flux:callout>
    @endif

    @if ($locationMessage)
        <flux:callout variant="success" icon="check-circle">
            {{ $locationMessage }}
        </flux:callout>
    @endif

    <div class="grid gap-4 md:grid-cols-2">

        {{-- CHECK IN --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm dark:bg-zinc-900">

            <div class="mb-4">
                <flux:heading size="lg">
                    Check In
                </flux:heading>

                <flux:text class="mt-1">
                    Catat kehadiran saat datang ke madrasah.
                </flux:text>
            </div>

            @if ($attendance?->check_in_at)

                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                    <flux:text class="text-sm">
                        Check In
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $attendance->check_in_at->format('H:i') }}
                    </flux:heading>
                </div>

            @else

                <flux:button variant="primary" class="w-full" wire:loading.attr="disabled"
                    x-on:click="getLocation('check_in')" x-bind:disabled="loading">
                    <span x-show="!loading">
                        Check In
                    </span>

                    <span x-show="loading">
                        Mendapatkan lokasi...
                    </span>
                </flux:button>

            @endif

        </div>


        {{-- CHECK OUT --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm dark:bg-zinc-900">

            <div class="mb-4">
                <flux:heading size="lg">
                    Check Out
                </flux:heading>

                <flux:text class="mt-1">
                    Catat waktu pulang dari madrasah.
                </flux:text>
            </div>

            @if ($attendance?->check_out_at)

                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                    <flux:text class="text-sm">
                        Check Out
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $attendance->check_out_at->format('H:i') }}
                    </flux:heading>
                </div>

            @elseif ($attendance?->check_in_at)

                <flux:button variant="primary" class="w-full" wire:loading.attr="disabled"
                    x-on:click="getLocation('check_out')" x-bind:disabled="loading">
                    <span x-show="!loading">
                        Check Out
                    </span>

                    <span x-show="loading">
                        Mendapatkan lokasi...
                    </span>
                </flux:button>

            @else

                <flux:button variant="ghost" class="w-full" disabled>
                    Check In terlebih dahulu
                </flux:button>

            @endif

        </div>

    </div>


    {{-- DETAIL --}}
    @if ($attendance)

        <div class="rounded-xl border bg-white p-6 shadow-sm dark:bg-zinc-900">

            <flux:heading size="lg">
                Detail Presensi Hari Ini
            </flux:heading>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">

                <div>
                    <flux:text class="text-sm">
                        Tanggal
                    </flux:text>

                    <div class="font-medium">
                        {{ $attendance->attendance_date->locale('id')->translatedFormat('l, d F Y') }}
                    </div>
                </div>

                <div>
                    <flux:text class="text-sm">
                        Status
                    </flux:text>

                    <div class="font-medium capitalize">
                        {{ $attendance->status }}
                    </div>
                </div>

                @if ($attendance->check_in_at)

                    <div>
                        <flux:text class="text-sm">
                            Check In
                        </flux:text>

                        <div class="font-medium">
                            {{ $attendance->check_in_at->format('H:i:s') }}
                        </div>
                    </div>

                @endif

                @if ($attendance->check_out_at)

                    <div>
                        <flux:text class="text-sm">
                            Check Out
                        </flux:text>

                        <div class="font-medium">
                            {{ $attendance->check_out_at->format('H:i:s') }}
                        </div>
                    </div>

                @endif

            </div>

        </div>

    @endif

    <div class="mt-8 space-y-4">
        <div>
            <flux:heading size="lg">
                Riwayat Presensi
            </flux:heading>

            <flux:subheading>
                {{ now()->locale('id')->translatedFormat('F Y') }}
            </flux:subheading>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-max w-full text-sm">
                <thead>
                    <tr>
                        @foreach ($monthDates as $date)
                            @php
                                $holiday = $holidays[$date['date']] ?? null;
                                $isSunday = $date['is_sunday'];
                                $isToday = $date['is_today'];
                            @endphp

                            <th class="
                                    min-w-24 border-b border-r px-3 py-3 text-center
                                    last:border-r-0
                                    {{ $isToday ? 'bg-primary-50 dark:bg-primary-950' : '' }}
                                    {{ $isSunday || $holiday ? 'text-red-600 dark:text-red-400' : '' }}
                                ">
                                <div class="font-medium">
                                    {{ $date['day_name'] }}
                                </div>

                                <div class="text-lg font-semibold">
                                    {{ $date['day'] }}
                                </div>

                                @if ($holiday)
                                    <div class="mt-1 text-xs font-normal">
                                        {{ $holiday['name'] }}
                                    </div>
                                @elseif ($isSunday)
                                    <div class="mt-1 text-xs font-normal">
                                        Libur
                                    </div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    {{-- Check In --}}
                    <tr>
                        @foreach ($monthDates as $date)
                            @php
                                $attendance = $this->monthlyAttendances[$date['date']] ?? null;
                                $holiday = $holidays[$date['date']] ?? null;
                                $isHoliday = $date['is_sunday'] || $holiday;
                            @endphp

                            <td class="
                                    border-b border-r px-3 py-3 text-center
                                    last:border-r-0
                                    {{ $date['is_today'] ? 'bg-primary-50/50 dark:bg-primary-950/50' : '' }}
                                    {{ $isHoliday ? 'bg-red-50/50 dark:bg-red-950/20' : '' }}
                                ">
                                <div class="text-xs text-zinc-500">
                                    Check In
                                </div>

                                <div class="mt-1 font-medium">
                                    @if ($attendance?->check_in_at)
                                        {{ $attendance->check_in_at->format('H:i') }}
                                    @elseif ($isHoliday)
                                        <span class="text-red-500">Libur</span>
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>

                    {{-- Check Out --}}
                    <tr>
                        @foreach ($monthDates as $date)
                            @php
                                $attendance = $this->monthlyAttendances[$date['date']] ?? null;
                                $holiday = $holidays[$date['date']] ?? null;
                                $isHoliday = $date['is_sunday'] || $holiday;
                            @endphp

                            <td class="
                                    border-b border-r px-3 py-3 text-center
                                    last:border-r-0
                                    {{ $date['is_today'] ? 'bg-primary-50/50 dark:bg-primary-950/50' : '' }}
                                    {{ $isHoliday ? 'bg-red-50/50 dark:bg-red-950/20' : '' }}
                                ">
                                <div class="text-xs text-zinc-500">
                                    Check Out
                                </div>

                                <div class="mt-1 font-medium">
                                    @if ($attendance?->check_out_at)
                                        {{ $attendance->check_out_at->format('H:i') }}
                                    @elseif ($isHoliday)
                                        <span class="text-red-500">Libur</span>
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>

                    {{-- Jumlah Jam --}}
                    <tr>
                        @foreach ($monthDates as $date)
                            @php
                                $attendance = $this->monthlyAttendances[$date['date']] ?? null;
                                $holiday = $holidays[$date['date']] ?? null;
                                $isHoliday = $date['is_sunday'] || $holiday;

                                $duration = null;

                                if ($attendance?->check_in_at && $attendance?->check_out_at) {
                                    $duration = $attendance->check_in_at
                                        ->diff($attendance->check_out_at);
                                }
                            @endphp

                            <td class="
                                    px-3 py-3 text-center border-r
                                    last:border-r-0
                                    {{ $date['is_today'] ? 'bg-primary-50/50 dark:bg-primary-950/50' : '' }}
                                    {{ $isHoliday ? 'bg-red-50/50 dark:bg-red-950/20' : '' }}
                                ">
                                <div class="text-xs text-zinc-500">
                                    Jml Jam
                                </div>

                                <div class="mt-1 font-medium">
                                    @if ($duration)
                                        {{ $duration->h }}j {{ $duration->i }}m
                                    @elseif ($isHoliday)
                                        <span class="text-red-500">-</span>
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>


    @script
    <script>
        Alpine.data('gtkAttendance', () => ({
            loading: false,

            getLocation(type) {

                if (!navigator.geolocation) {
                    alert('Browser Anda tidak mendukung GPS.');
                    return;
                }

                this.loading = true;

                navigator.geolocation.getCurrentPosition(

                    (position) => {

                        const latitude =
                            position.coords.latitude;

                        const longitude =
                            position.coords.longitude;

                        const accuracy =
                            position.coords.accuracy;

                        console.log('GPS:', {
                            latitude,
                            longitude,
                            accuracy,
                        });

                        if (type === 'check_in') {

                            $wire.checkIn(
                                latitude,
                                longitude,
                                accuracy
                            );

                        } else {

                            $wire.checkOut(
                                latitude,
                                longitude,
                                accuracy
                            );

                        }

                        this.loading = false;
                    },

                    (error) => {

                        this.loading = false;

                        let message =
                            'Gagal mendapatkan lokasi.';

                        if (error.code === 1) {
                            message =
                                'Izin lokasi ditolak. '
                                + 'Silakan izinkan akses lokasi pada browser.';
                        }

                        if (error.code === 2) {
                            message =
                                'Lokasi tidak dapat ditemukan.';
                        }

                        if (error.code === 3) {
                            message =
                                'Waktu mendapatkan lokasi habis. '
                                + 'Silakan coba lagi.';
                        }

                        alert(message);
                    },

                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0,
                    }
                );
            },
        }));
    </script>
    @endscript
</div>
