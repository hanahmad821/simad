<x-layouts::app title="Periksa Riwayat Lama"><div class="mx-auto max-w-5xl space-y-6">
<flux:heading size="xl">Periksa Riwayat: {{ $student->full_name }}</flux:heading><flux:text>{{ $year->name }} · {{ $issue->description }}</flux:text>
@include('admin.classes.messages')
<flux:callout>Isi tanggal berdasarkan catatan sekolah. Tandai baris duplikat sebagai batal. Sistem memeriksa tumpang tindih, kapasitas, dan kesesuaian dengan presensi lama.</flux:callout>
<form method="POST" action="{{ route('admin.student-management.preview') }}" class="space-y-5">@csrf
<input type="hidden" name="kind" value="reconcile"><input type="hidden" name="issue_id" value="{{ $issue->id }}"><input type="hidden" name="date" value="{{ today()->toDateString() }}">
@foreach($histories as $i => $h)
@php($saved = collect($draft['intervals'] ?? [])->firstWhere('id', $h->id) ?? [])
<flux:card class="space-y-3"><flux:heading>{{ $h->schoolClass->name }} · Riwayat #{{ $h->id }}</flux:heading><input type="hidden" name="intervals[{{ $i }}][id]" value="{{ $h->id }}">
<div class="grid gap-3 sm:grid-cols-3"><flux:input type="date" label="Mulai berlaku" name="intervals[{{ $i }}][starts_on]" value="{{ $saved['starts_on'] ?? $h->starts_on?->toDateString() }}"/><flux:input type="date" label="Sampai tanggal" name="intervals[{{ $i }}][ends_on]" value="{{ $saved['ends_on'] ?? $h->ends_on?->toDateString() }}"/><label class="flex items-center gap-2"><input type="checkbox" name="intervals[{{ $i }}][void]" value="1" @checked($saved['void'] ?? $h->voided_at)> Batalkan baris duplikat</label></div></flux:card>
@endforeach
@if(str_contains($issue->description, 'Keputusan'))
<flux:select name="decision_event_id" label="Keputusan lama yang sah" required><option value="">Pilih berdasarkan catatan sekolah</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected(($draft['decision_event_id'] ?? null) == $event->id)>#{{ $event->id }} · {{ \App\Services\StudentLifecycleService::LABELS[$event->action] }} · {{ $event->created_at }}</option>@endforeach</flux:select>
@endif
<flux:textarea name="reason" label="Dasar pemeriksaan / alasan koreksi" required>{{ $draft['reason'] ?? '' }}</flux:textarea>
<flux:button type="submit" variant="primary">Tinjau Rekonsiliasi</flux:button><flux:button :href="route('admin.student-management.issues')">Kembali</flux:button>
</form></div></x-layouts::app>