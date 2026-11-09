<x-layouts::app title="Jurnal Mengajar Guru">
    <div class="space-y-6">
        <div class="flex flex-wrap justify-between gap-3"><div><flux:heading size="xl">Jurnal Mengajar: {{ $gtk->full_name }}</flux:heading><flux:text>{{ $month->locale('id')->translatedFormat('F Y') }} - {{ $rows->count() }} sesi tercatat, {{ $rows->sum('periods') }} JP</flux:text></div><flux:button :href="route('headmaster.teaching-journals.index', ['month' => $month->format('Y-m')])">Kembali</flux:button></div>
        @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-900">{{ $errors->first() }}</div>@endif
        <form method="GET" class="flex flex-wrap items-end gap-3"><flux:input type="month" label="Bulan" name="month" :value="$month->format('Y-m')" :max="today()->format('Y-m')" required /><flux:button type="submit">Tampilkan</flux:button><flux:button :href="route('headmaster.gtk-reports.download', [$gtk, 'teaching', 'month' => $month->format('Y-m')])">Download Jurnal Mengajar (PDF)</flux:button></form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm"><thead><tr><th class="p-3">Tanggal</th><th class="p-3">Kelas</th><th class="p-3">Mata pelajaran</th><th class="p-3">JP</th><th class="p-3">Uraian materi</th><th class="p-3">Catatan</th><th class="p-3">Refleksi</th><th class="p-3">Status jurnal</th></tr></thead><tbody>
                @forelse ($rows as $row)
                    <tr class="border-t border-zinc-200 align-top dark:border-zinc-700"><td class="p-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td class="p-3">{{ $row['class'] }}</td><td class="p-3">{{ $row['subject'] }}</td><td class="p-3">{{ $row['periods'] }}</td><td class="p-3 whitespace-pre-wrap">{{ $row['material'] }}</td><td class="p-3 whitespace-pre-wrap">{{ $row['notes'] }}</td><td class="p-3 whitespace-pre-wrap">{{ $row['reflection'] }}</td><td class="p-3">{{ $row['status'] }}</td></tr>
                @empty <tr><td colspan="8" class="p-6">Belum ada jurnal mengajar pada bulan ini.</td></tr> @endforelse
            </tbody></table>
        </div>
        @if ($missing->isNotEmpty())
            <flux:heading>Sesi dengan jurnal belum lengkap</flux:heading>
            @foreach ($missing as $session)
                <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><summary class="cursor-pointer">{{ \Carbon\Carbon::parse($session['date'])->format('d/m/Y') }} - {{ $session['label'] }}</summary>
                    <form method="POST" action="{{ route('headmaster.journals.exception', $gtk) }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="date" value="{{ $session['date'] }}"><input type="hidden" name="session_key" value="{{ $session['key'] }}">
                        <flux:select name="substitute_gtk_id" label="Perubahan pelaksanaan KBM"><option value="">KBM dibatalkan</option>@foreach ($substitutes as $substitute)<option value="{{ $substitute->gtk_id }}">Digantikan: {{ $substitute->gtk?->full_name }}</option>@endforeach</flux:select>
                        <flux:textarea name="reason" label="Alasan" maxlength="5000" rows="2" required /><flux:button type="submit">Catat perubahan KBM</flux:button>
                    </form>
                </details>
            @endforeach
        @endif
        @if ($exceptions->isNotEmpty())
            <flux:heading>Perubahan pelaksanaan KBM</flux:heading>
            @foreach ($exceptions as $exception)<p class="text-sm">{{ $exception->date->format('d/m/Y') }} - {{ $exception->label }}: {{ $exception->substitute_gtk_id ? 'Guru pengganti' : 'Dibatalkan' }}. {{ $exception->reason }}</p>@endforeach
        @endif
    </div>
</x-layouts::app>
