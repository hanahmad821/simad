<x-layouts::app title="Tinjau Perubahan Siswa"><div class="mx-auto max-w-5xl space-y-6">
<div><flux:heading size="xl">Tinjau Perubahan</flux:heading><flux:text>Periksa hasil nyata setiap siswa sebelum menyimpan.</flux:text></div>
@include('admin.classes.messages')
<flux:card class="space-y-4"><div class="flex flex-wrap gap-2">@foreach(collect($plan['items'])->countBy('action') as $action => $count)<flux:badge>{{ \App\Services\StudentLifecycleService::LABELS[$action] ?? $action }}: {{ $count }}</flux:badge>@endforeach</div>
<flux:text>Tanggal keputusan: {{ $plan['data']['date'] }}. Penempatan tahun berikutnya berlaku mulai awal tahun tujuan.</flux:text>
@foreach(collect($plan['items'])->groupBy(fn($item) => $item['target']) as $target => $items)
<div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><flux:heading>{{ $target }} · {{ $items->count() }} siswa</flux:heading>
<ul class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">@foreach($items as $item)<li class="py-3"><div class="font-medium">{{ $item['name'] }}</div><flux:text>{{ $item['source'] }} → {{ \App\Services\StudentLifecycleService::LABELS[$item['action']] }}</flux:text>@if($item['reason'])<flux:text>Alasan: {{ $item['reason'] }}</flux:text>@endif</li>@endforeach</ul></div>
@endforeach
<flux:callout>Hasil Ditunda tetap memerlukan keputusan. Riwayat lama tersimpan dan tahun aktif tidak berubah otomatis.</flux:callout>
<div class="flex flex-wrap gap-3"><flux:button :href="$back">Kembali & Ubah Pilihan</flux:button><form method="POST" action="{{ route('admin.student-management.commit', $draftId) }}">@csrf<flux:button variant="primary" type="submit">Simpan {{ count($plan['items']) }} Hasil Siswa</flux:button></form></div></flux:card>
</div></x-layouts::app>