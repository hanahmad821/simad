<x-layouts::app title="Pengaturan Waktu KBM">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><flux:heading size="xl">Pengaturan Waktu KBM</flux:heading><flux:text class="mt-1">1 JP = 35 menit. Atur kegiatan pagi, istirahat, dan jumlah JP untuk setiap hari.</flux:text></div>
            <flux:button :href="route('admin.schedules.index')">Buka Jadwal</flux:button>
        </div>
        @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="warning"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>@endif
        <div class="flex flex-wrap gap-2">
            @foreach ($days as $number => $label)
                <flux:button :variant="$day === $number ? 'primary' : 'outline'" :href="route('admin.kbm-times.index', ['day' => $number])">{{ $label }}</flux:button>
            @endforeach
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Pola {{ $days[$day] }}</flux:heading>
                <form method="POST" action="{{ route('admin.kbm-times.update') }}" class="space-y-5"
                    x-data="{ breaks: @js(old('morning_start') !== null ? old('breaks', []) : $values['breaks']) }">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="time" name="morning_start" label="Mulai kegiatan pagi" :value="old('morning_start', $values['morning_start'])" required />
                        <flux:input type="time" name="morning_end" label="Selesai kegiatan pagi" :value="old('morning_end', $values['morning_end'])" required />
                    </div>
                    <flux:input type="number" name="teaching_periods" label="Jumlah JP per hari" min="1" max="12" :value="old('teaching_periods', $values['teaching_periods'])" required />
                    <div class="space-y-3">
                        <flux:heading>Istirahat</flux:heading>
                        <template x-for="(item, index) in breaks" :key="index">
                            <div class="flex flex-wrap items-end gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                <label class="min-w-32 flex-1 text-sm">Mulai <span x-text="'istirahat ' + (index + 1)"></span>
                                    <input type="time" :name="'breaks[' + index + '][start]'" x-model="item.start" required class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-900" />
                                </label>
                                <label class="min-w-32 flex-1 text-sm">Selesai
                                    <input type="time" :name="'breaks[' + index + '][end]'" x-model="item.end" required class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-900" />
                                </label>
                                <flux:button type="button" variant="ghost" x-on:click="breaks.splice(index, 1)">Hapus</flux:button>
                            </div>
                        </template>
                        <flux:button type="button" x-on:click="breaks.push({ start: '', end: '' })" x-bind:disabled="breaks.length >= 5">Tambah Istirahat</flux:button>
                        <flux:text size="sm">Istirahat harus dimulai tepat setelah satu JP selesai. Kegiatan pagi dan istirahat tidak menambah jumlah JP.</flux:text>
                    </div>
                    <fieldset class="space-y-2">
                        <legend class="mb-2 font-medium">Terapkan ke hari</legend>
                        <div class="flex flex-wrap gap-4">
                            @foreach ($days as $number => $label)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="days[]" value="{{ $number }}" @checked(in_array((string) $number, array_map('strval', old('days', [$day])), true)) />{{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <flux:text size="sm">Perubahan jam berlaku pada jadwal yang sudah ada. Kurangi jumlah JP setelah memindahkan jadwal yang memakai JP tersebut.</flux:text>
                    <flux:button type="submit" variant="primary">Simpan Pola Waktu</flux:button>
                </form>
            </section>
            <section class="space-y-4">
                <flux:heading size="lg">Susunan waktu {{ $days[$day] }}</flux:heading>
                <flux:text>Menampilkan pola tersimpan. Setiap baris pelajaran berdurasi 35 menit.</flux:text>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th class="p-3">Kegiatan / JP</th><th class="p-3">Mulai</th><th class="p-3">Selesai</th></tr></thead>
                        <tbody>@foreach($slots as $slot)<tr class="border-t border-zinc-200 dark:border-zinc-700 {{ $slot['is_break'] || $slot['period_number'] === 0 ? 'bg-zinc-50 dark:bg-zinc-800' : '' }}"><td class="p-3">{{ $slot['name'] }}</td><td class="p-3">{{ $slot['start_time'] }}</td><td class="p-3">{{ $slot['end_time'] }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
