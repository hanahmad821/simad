@props(['letter'])

@if (! $letter->dispositions_count)
    <flux:badge color="yellow">Menunggu disposisi</flux:badge>
@elseif ($letter->completed_dispositions_count === $letter->dispositions_count)
    <flux:badge color="green">Selesai</flux:badge>
@else
    <flux:badge color="blue">Dalam tindak lanjut</flux:badge>
@endif
