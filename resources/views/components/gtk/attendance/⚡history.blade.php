<?php

use App\Models\GtkAttendance;
use App\Services\GtkAttendanceHistoryService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

new class extends \Livewire\Component
{
    public string $month;
    public bool $showCorrection = false;
    #[Locked]
    public ?string $correctionDate = null;
    #[Locked]
    public ?GtkAttendance $correctionAttendance = null;
    public string $reason = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->gtk !== null, 403, 'Akun belum terhubung dengan data GTK.');
        $this->month = now()->format('Y-m');
    }

    public function updatedMonth(): void
    {
        $this->closeCorrection();
        $this->validate(['month' => GtkAttendanceHistoryService::MONTH_RULES]);
    }

    public function openCorrection(string $date): void
    {
        $this->closeCorrection();
        $this->correctionAttendance = app(GtkAttendanceHistoryService::class)->correctionDetails(Auth::user(), $date);
        $this->correctionDate = $date;
        $this->showCorrection = true;
    }

    public function closeCorrection(): void
    {
        $this->reset('showCorrection', 'correctionDate', 'correctionAttendance', 'reason');
        $this->resetValidation();
    }

    public function saveForgotten(): void
    {
        app(GtkAttendanceHistoryService::class)->saveForgotten(Auth::user(), $this->only([
            'correctionDate', 'reason',
        ]));
        $this->closeCorrection();
        session()->flash('status', 'Lupa presensi pulang dicatat sebagai PSW3 tanpa approval kepala madrasah.');
    }

    public function render()
    {
        try {
            $days = app(GtkAttendanceHistoryService::class)->days(Auth::user(), $this->month);
        } catch (ValidationException) {
            $days = collect();
        }

        return $this->view(compact('days'));
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            Riwayat Presensi
        </flux:heading>

        <flux:subheading>
            Seluruh tanggal dalam bulan terpilih. Jam wajib mengikuti awal kegiatan pagi dan akhir KBM setiap hari. T1 / PSW1: sampai 30 menit; T2 / PSW2: lebih dari 30 sampai 60 menit; T3 / PSW3: lebih dari 60 menit.
        </flux:subheading>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            type="month"
            wire:model.live="month"
            :max="today()->format('Y-m')"
            class="w-fit"
        />
        <flux:button :href="route('gtk.reports.download', ['kind' => 'attendance', 'month' => $month])">Download Presensi (PDF)</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif
    @if ($errors->any() && ! $showCorrection)
        <flux:callout variant="warning"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
    @endif
    @if ($correctionDate)
        <flux:modal name="forgotten-attendance" wire:model="showCorrection" class="md:w-xl">
        <form wire:submit="saveForgotten" class="space-y-4">
            <flux:heading>Lupa Presensi Pulang: {{ \Carbon\Carbon::parse($correctionDate)->format('d/m/Y') }}</flux:heading>
            @if ($errors->any())
                <flux:callout variant="warning"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
            @endif
            <flux:text>Masuk tercatat: {{ $correctionAttendance?->check_in_at?->format('H:i') }}</flux:text>
            <flux:textarea label="Alasan lupa presensi" wire:model="reason" rows="2" maxlength="1000" required />
            <flux:text>Lupa presensi pulang berkategori PSW3. Laporan ini menambahkan alasan tanpa membuat jam pulang pengganti dan tidak memerlukan approval.</flux:text>
            <div class="flex gap-3">
                <flux:button type="submit" variant="primary">Simpan Lupa Presensi</flux:button>
                <flux:button type="button" wire:click="closeCorrection">Batal</flux:button>
            </div>
        </form>
        </flux:modal>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-zinc-50 dark:bg-zinc-800">
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Masuk</th>
                        <th class="px-4 py-3 text-left">Pulang</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Catatan</th>
                        <th class="px-4 py-3 text-left">Terlambat / Pulang awal</th>
                        <th class="px-4 py-3 text-left">Tindakan</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse ($days as $day)
                        @php
                            $attendance = $day['attendance'];
                            $classification = $day['classification'];
                        @endphp
                        <tr wire:key="attendance-day-{{ $day['date']->toDateString() }}" id="attendance-day-{{ $day['date']->toDateString() }}">
                            <td class="px-4 py-3">
                                {{ $day['date']->locale('id')->translatedFormat('l, d F Y') }}
                                @if ($day['holiday'])<div class="text-xs text-zinc-500">{{ $day['holiday'] }}</div>@endif
                                @if ($classification['is_workday'])<div class="text-xs text-zinc-500">Wajib {{ $classification['expected_start']?->format('H:i') }} – {{ $classification['expected_end']?->format('H:i') }}</div>@endif
                            </td>

                            <td class="px-4 py-3">
                                {{ $attendance?->check_in_at?->format('H:i') ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $attendance?->check_out_at?->format('H:i') ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $classification['label'] }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="whitespace-pre-wrap">{{ $attendance?->notes ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <x-gtk-attendance-indicators :classification="$classification" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                <flux:button size="sm" icon="document-plus" :href="route('gtk.absences.create', ['date' => $day['date']->toDateString()])" wire:navigate>Input Absensi</flux:button>
                                @if ($day['can_correct'])
                                    <flux:button size="sm" wire:click="openCorrection('{{ $day['date']->toDateString() }}')">Lupa Presensi Pulang</flux:button>
                                @elseif ($day['date']->isToday() && ! $attendance?->check_out_at && ! in_array($attendance?->status, ['leave', 'sick', 'absent'], true))
                                    <flux:button size="sm" :href="route('gtk.attendance.index')">Presensi Hari Ini</flux:button>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500">
                                Pilih bulan yang valid untuk menampilkan riwayat presensi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
