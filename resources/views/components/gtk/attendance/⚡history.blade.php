<?php

use App\Models\GtkAttendance;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new class extends \Livewire\Component
{
    use WithPagination;

    public string $month;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $gtk = Auth::user()->gtk;

        $attendances = GtkAttendance::query()
            ->where('gtk_id', $gtk->id)
            ->whereYear('attendance_date', substr($this->month, 0, 4))
            ->whereMonth('attendance_date', substr($this->month, 5, 2))
            ->latest('attendance_date')
            ->paginate(10);

        return $this->view([
            'attendances' => $attendances,
        ]);
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            Riwayat Presensi
        </flux:heading>

        <flux:subheading>
            Riwayat kehadiran Anda.
        </flux:subheading>
    </div>

    <flux:input
        type="month"
        wire:model.live="month"
        class="w-fit"
    />

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
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse ($attendances as $attendance)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $attendance->attendance_date->translatedFormat('d F Y') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $attendance->check_in_at?->format('H:i') ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $attendance->check_out_at?->format('H:i') ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ ucfirst($attendance->status) }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $attendance->notes ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                Belum ada riwayat presensi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $attendances->links() }}
</div>
