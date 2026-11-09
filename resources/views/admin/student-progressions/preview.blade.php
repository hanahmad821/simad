<x-layouts::app title="Ringkasan Proses Siswa">
<div class="space-y-6">
    <div><flux:heading size="xl">Tinjau Ringkasan</flux:heading><flux:text>{{ $plan['sourceClass']->name }} · {{ $plan['sourceYear']->name }} → {{ $plan['destinationYear']?->name ?: 'Arsip siswa' }}</flux:text></div>
    @include('admin.classes.messages')
    <flux:callout variant="warning">Periksa {{ count($plan['rows']) }} siswa berikut sebelum menyimpan. Riwayat kelas dan presensi lama tetap tersimpan. Tahun pelajaran aktif tidak berubah otomatis.</flux:callout>
    <flux:card class="space-y-4">
        <flux:text>Tanggal efektif: {{ $data['effective_date'] }}</flux:text>
        <div class="flex flex-wrap gap-2">@foreach(collect($plan['rows'])->countBy('action') as $action => $count)<flux:badge>{{ \App\Services\StudentProgressionService::ACTIONS[$action] }}: {{ $count }}</flux:badge>@endforeach</div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr class="border-b"><th class="p-3">Siswa</th><th class="p-3">Proses</th><th class="p-3">Tujuan / Status</th></tr></thead>
            <tbody>@foreach($plan['rows'] as $row)<tr class="border-b"><td class="p-3">{{ $row['student']->full_name }}</td><td class="p-3">{{ \App\Services\StudentProgressionService::ACTIONS[$row['action']] }}</td><td class="p-3">{{ $row['target']?->name ?: 'Diarsipkan, tidak masuk presensi berjalan' }}</td></tr>@endforeach</tbody>
        </table></div>
        <div class="flex flex-wrap gap-3">
            <flux:button onclick="history.back()">Kembali ke Pilihan</flux:button>
            <form method="POST" action="{{ route('admin.student-progressions.store') }}" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                @csrf<input type="hidden" name="token" value="{{ $token }}">
                <flux:button type="submit" variant="primary">Simpan Proses {{ count($plan['rows']) }} Siswa</flux:button>
            </form>
        </div>
    </flux:card>
</div>
</x-layouts::app>
