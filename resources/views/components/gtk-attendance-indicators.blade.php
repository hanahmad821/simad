@props(['classification'])
<div class="space-y-1 text-xs">
    @if ($classification['late_level'])
        <div><flux:badge color="amber" size="sm">{{ $classification['late_level'] }}</flux:badge> Terlambat {{ $classification['late_minutes'] }} menit</div>
    @endif
    @if ($classification['early_level'])
        <div><flux:badge color="orange" size="sm">{{ $classification['early_level'] }}</flux:badge> {{ $classification['forgotten_checkout'] ? 'Lupa presensi pulang' : 'Pulang awal '.$classification['early_minutes'].' menit' }}</div>
    @endif
    @if (! $classification['late_level'] && ! $classification['early_level'])
        <span>{{ in_array($classification['status'], ['present', 'late'], true) ? 'Tidak ada pelanggaran tercatat' : '-' }}</span>
    @endif
</div>
