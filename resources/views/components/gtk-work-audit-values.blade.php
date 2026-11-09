@props(['values'])
@php
    $labels = [
        'journal_date' => 'Tanggal jurnal', 'attendance_date' => 'Tanggal presensi', 'date' => 'Tanggal',
        'month' => 'Bulan', 'label' => 'Kegiatan / sesi', 'status' => 'Status',
        'material' => 'Uraian materi', 'notes' => 'Catatan', 'reflection' => 'Refleksi', 'activities' => 'Uraian kegiatan',
        'completed_at' => 'Diselesaikan pada', 'reviewed_at' => 'Direview pada',
        'reason' => 'Alasan', 'substitute_gtk_id' => 'Guru pengganti',
        'check_in_at' => 'Waktu masuk', 'check_out_at' => 'Waktu pulang',
    ];
    $statuses = ['draft' => 'Draf', 'completed' => 'Selesai', 'pending' => 'Menunggu review',
        'revision_requested' => 'Perlu perbaikan', 'reviewed' => 'Selesai direview', 'approved' => 'Disetujui',
        'present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Izin', 'sick' => 'Sakit', 'absent' => 'Alpa'];
@endphp
@if (! $values)
    <p class="mt-2 text-zinc-500">Belum ada data.</p>
@else
    <dl class="mt-2 space-y-2">
        @foreach ($labels as $field => $label)
            @if (array_key_exists($field, $values))
                @php
                    $value = $values[$field];
                    if ($field === 'status') {
                        $value = $statuses[$value] ?? $value;
                    } elseif ($field === 'substitute_gtk_id') {
                        $value = $value ? \App\Models\Gtk::find($value)?->full_name : 'Tidak ada (KBM dibatalkan)';
                    } elseif ($value && in_array($field, ['journal_date', 'attendance_date', 'date', 'month', 'completed_at', 'reviewed_at', 'check_in_at', 'check_out_at'])) {
                        $value = \Carbon\Carbon::parse($value)->format($field === 'month' ? 'm/Y' : (str_ends_with($field, '_at') ? 'd/m/Y H:i:s' : 'd/m/Y'));
                    }
                @endphp
                <div><dt class="text-zinc-500">{{ $label }}</dt><dd class="whitespace-pre-wrap">{{ $value ?: '—' }}</dd></div>
            @endif
        @endforeach
    </dl>
@endif
