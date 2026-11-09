<x-layouts::app title="Approval LCKH GTK">
    @php
        $selectedDates = collect(old('dates', []))->filter(fn ($date) => is_string($date) && in_array($date, $selectableDates, true))->unique()->values()->all();
    @endphp
    <div class="space-y-6" x-data="{ selected: @js($selectedDates), available: @js($selectableDates) }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><flux:heading size="xl">LCKH: {{ $gtk->full_name }}</flux:heading><flux:text>{{ $month->locale('id')->translatedFormat('F Y') }} - approval LCKH per hari</flux:text></div>
            <flux:button :href="route('headmaster.work-reviews.index', ['month' => $month->format('Y-m')])">Kembali</flux:button>
        </div>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input type="month" name="month" label="Bulan" :value="$month->format('Y-m')" :max="today()->format('Y-m')" required />
            <flux:button type="submit">Tampilkan</flux:button>
        </form>
        <div class="flex flex-wrap gap-3">
            <form id="approve-selected" method="POST" action="{{ route('headmaster.work-reviews.approve-selected', $gtk) }}">
                @csrf <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <flux:button type="submit" variant="primary" :loading="false" x-bind:disabled="selected.length === 0">Approve Terpilih (<span x-text="selected.length">{{ count($selectedDates) }}</span>)</flux:button>
            </form>
            <flux:button :href="route('headmaster.gtk-reports.download', [$gtk, 'lckh', 'month' => $month->format('Y-m')])">Download LCKH (PDF)</flux:button>
        </div>
        <flux:text>Centang tanggal dengan LCKH selesai yang menunggu approval. Perubahan LCKH mengembalikan status menjadi menunggu approval. Perubahan presensi tidak memengaruhi approval LCKH.</flux:text>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>
                    <th class="p-3">
                        <input type="checkbox" aria-label="Pilih semua tanggal yang tersedia" class="size-4 accent-green-700"
                            x-bind:disabled="available.length === 0"
                            x-bind:checked="available.length > 0 && selected.length === available.length"
                            x-effect="$el.indeterminate = selected.length > 0 && selected.length < available.length"
                            x-on:change="selected = $event.target.checked ? [...available] : []" />
                    </th>
                    <th class="p-3">Tanggal</th><th class="p-3">LCKH / hasil kegiatan</th><th class="p-3">Approval</th><th class="p-3">Tindakan</th>
                </tr></thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr id="day-{{ $day['date'] }}" class="border-t border-zinc-200 align-top dark:border-zinc-700">
                            <td class="p-3">
                                <input type="checkbox" name="dates[]" value="{{ $day['date'] }}" form="approve-selected"
                                    aria-label="Pilih tanggal {{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}"
                                    class="size-4 accent-green-700" x-model="selected"
                                    @checked(in_array($day['date'], $selectedDates, true))
                                    @disabled(! in_array($day['date'], $selectableDates, true)) />
                            </td>
                            <td class="p-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                            <td class="p-3 min-w-56"><span class="font-medium">{{ $day['lckh']?->isComplete() ? 'Selesai' : ($day['lckh'] ? 'Draf' : 'Belum diisi') }}</span><p class="mt-2 whitespace-pre-wrap">{{ $day['lckh']?->activities }}</p></td>
                            <td class="p-3"><flux:badge :color="$day['approval']?->status === 'approved' ? 'green' : ($day['approval']?->status === 'revision_requested' ? 'red' : 'zinc')">{{ ['pending' => 'Menunggu approval', 'approved' => 'Disetujui', 'revision_requested' => 'Perlu perbaikan'][$day['approval']?->status ?? 'pending'] }}</flux:badge><p class="mt-2 whitespace-pre-wrap">{{ $day['approval']?->notes }}</p></td>
                            <td class="p-3 min-w-56">
                                @if ($day['lckh'])
                                    <form method="POST" action="{{ route('headmaster.work-reviews.decide', $gtk) }}" class="space-y-2">
                                        @csrf <input type="hidden" name="date" value="{{ $day['date'] }}">
                                        <flux:textarea name="notes" label="Catatan perbaikan" rows="2" maxlength="10000" />
                                        <flux:button type="submit" name="status" value="approved" :loading="false" :disabled="!$day['eligible']">Approve</flux:button>
                                        <flux:button type="submit" name="status" value="revision_requested">Minta perbaikan</flux:button>
                                    </form>
                                @endif
                                @if (! $day['eligible'])<p class="mt-2 text-xs text-zinc-500">{{ $day['reason'] }}</p>@endif
                            </td>
                        </tr>
                    @empty <tr><td colspan="5" class="p-6">Belum ada hari yang dapat ditampilkan.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
