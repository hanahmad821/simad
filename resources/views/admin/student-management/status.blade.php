<x-layouts::app title="Ubah Status Siswa"><div class="mx-auto max-w-3xl space-y-6">
<div class="flex justify-between gap-3"><div><flux:heading size="xl">Ubah Status Siswa</flux:heading><flux:text>{{ $student->full_name }} · NIS {{ $student->nis ?: '—' }}</flux:text></div><flux:button :href="route('admin.students.index')">Daftar Siswa</flux:button></div>
@include('admin.classes.messages')
<flux:card class="space-y-3"><flux:badge>{{ $student->statusLabel() }}</flux:badge>
@foreach($histories as $history)<flux:text>{{ $history->schoolClass->name }} · {{ $history->academicYear->name }} · {{ $history->starts_on?->format('d/m/Y') ?: 'Tanggal belum pasti' }} – {{ $history->ends_on?->format('d/m/Y') ?: '—' }}</flux:text>@endforeach</flux:card>
@if($student->is_active)
<form method="POST" action="{{ route('admin.student-management.preview') }}" x-data="{action: @js($draft['action'] ?? 'transfer')}" class="space-y-4">@csrf
<input type="hidden" name="kind" value="status"><input type="hidden" name="student_ids[]" value="{{ $student->id }}">
<flux:card class="space-y-4"><flux:select name="action" label="Status baru" x-model="action"><option value="transfer">Pindah sekolah</option><option value="withdraw">Keluar</option></flux:select>
<flux:input name="date" type="date" label="Tanggal efektif" :value="$draft['date'] ?? today()->toDateString()" required />
<div x-show="action==='transfer'"><flux:input name="destination_school" label="Sekolah tujuan" :value="$draft['destination_school'] ?? ''" x-bind:disabled="action!=='transfer'" /></div>
<flux:textarea name="reason" label="Alasan" required>{{ $draft['reason'] ?? '' }}</flux:textarea>
<flux:text>Penempatan tahun berikutnya yang belum memiliki aktivitas akan ditampilkan untuk dibatalkan dalam ringkasan.</flux:text>
<flux:button variant="primary" type="submit">Tinjau Perubahan Status</flux:button></flux:card></form>
@else<flux:callout>Siswa sudah tidak aktif. Untuk memperbaiki status, buka <a class="underline" href="{{ route('admin.student-management.history', ['q' => $student->full_name]) }}">riwayat dan koreksi proses</a>.</flux:callout>@endif
</div></x-layouts::app>