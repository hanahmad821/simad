<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>{{ $title }}</title>
<style>
    @page { margin: 18mm 13mm 17mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; line-height: 1.4; color: #171717; }
    .kop { width: 100%; border-collapse: collapse; margin: 0; }
    .kop td { vertical-align: middle; padding: 0; }
    .kop-logo { width: 19%; text-align: center; }
    .kop-logo img { width: 82px !important; height: auto; }
    .kop-info { text-align: center; color: #087443; }
    .kop-foundation { font-size: 8pt; font-weight: bold; }
    .kop-title { font-size: 14pt; font-weight: bold; line-height: 1.2; margin: 2px 0; }
    .kop-accreditation { font-size: 9pt; font-weight: bold; }
    .kop-address { font-size: 8pt; margin-top: 4px; }
    .kop-line { border-top: 3px solid #087443; border-bottom: 1px solid #087443; height: 2px; margin: 8px 0 15px; }
    h1 { text-align: center; font-size: 11pt; margin: 0 0 12px; }
    .identity { margin-bottom: 12px; width: 100%; }
    .identity td { padding: 1px 0; vertical-align: top; }
    .data { border-collapse: collapse; width: 100%; table-layout: fixed; }
    .data th, .data td { border: 1px solid #737373; padding: 5px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
    .data th { background: #edf5ee; font-size: 8pt; text-align: left; }
    .data thead { display: table-header-group; }
    .data tr { page-break-inside: avoid; }
    .text { white-space: pre-wrap; word-wrap: break-word; }
    .small { font-size: 7pt; color: #444; }
    .center { text-align: center; }
    .signature { width: 45%; margin: 22px 0 0 auto; text-align: center; page-break-inside: avoid; }
    .signature-space { height: 42px; }
    .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #666; border-top: 1px solid #ccc; padding-top: 3px; }
    .page-number:after { content: counter(page); }
</style>
</head>
<body>
    @include('pdf.partials.madrasah-letterhead')
    <h1>{{ $title }}</h1>
    <table class="identity">
        <tr><td style="width: 88px">Nama GTK</td><td>: {{ $gtk->full_name }}</td><td style="width: 60px">Periode</td><td style="width: 155px">: {{ $month->copy()->locale('id')->translatedFormat('F Y') }}</td></tr>
        <tr><td>NIP / NUPTK</td><td>: {{ $gtk->nip ?: ($gtk->nuptk ?: '-') }}</td><td>Jabatan</td><td>: {{ $gtk->position ?: '-' }}</td></tr>
    </table>
    @php
        $approvalLabels = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'revision_requested' => 'Perlu perbaikan'];
        $attendanceLabels = ['present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Izin', 'sick' => 'Sakit', 'absent' => 'Alpa'];
    @endphp
    @if ($kind === 'attendance')
        <table class="data"><thead><tr><th style="width: 4%">No</th><th style="width: 14%">Tanggal</th><th style="width: 12%">Status</th><th style="width: 9%">Masuk</th><th style="width: 9%">Pulang</th><th style="width: 11%">Terlambat</th><th style="width: 13%">Pulang awal</th><th>Catatan</th></tr></thead><tbody>
            @forelse ($printRows as $row)
                <tr><td class="center">{{ $row['number'] }}{{ $row['continued'] ? '*' : '' }}</td><td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td>{{ $row['classification']['label'] }}@if($row['holiday'])<div class="small">{{ $row['holiday'] }}</div>@endif</td><td>{{ $row['attendance']?->check_in_at?->format('H:i') ?? '-' }}</td><td>{{ $row['attendance']?->check_out_at?->format('H:i') ?? '-' }}</td><td>{{ $row['classification']['late_level'] ?? '-' }}@if($row['classification']['late_level'])<div class="small">{{ $row['classification']['late_minutes'] }} menit</div>@endif</td><td>{{ $row['classification']['early_level'] ?? '-' }}@if($row['classification']['forgotten_checkout'])<div class="small">Lupa pulang</div>@elseif($row['classification']['early_level'])<div class="small">{{ $row['classification']['early_minutes'] }} menit</div>@endif</td><td class="text">{{ $row['attendance_notes'] ?: '-' }}</td></tr>
            @empty <tr><td colspan="8">Belum ada data.</td></tr> @endforelse
        </tbody></table>
        <p class="small">Jam wajib mengikuti awal kegiatan pagi sampai akhir KBM. T1/PSW1: sampai 30 menit; T2/PSW2: lebih dari 30 sampai 60 menit; T3/PSW3: lebih dari 60 menit. Lupa pulang: PSW3. Hari kerja yang sudah lewat tanpa presensi: alpa. Presensi tidak memerlukan approval.</p>
    @elseif ($kind === 'lckh')
        <table class="data"><thead><tr><th style="width: 4%">No</th><th style="width: 16%">Tanggal</th><th>Uraian kegiatan dan hasil pekerjaan</th><th style="width: 13%">Status LCKH</th><th style="width: 17%">Approval / catatan</th></tr></thead><tbody>
            @forelse ($printRows as $row)
                <tr><td class="center">{{ $row['number'] }}{{ $row['continued'] ? '*' : '' }}</td><td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td class="text">{{ $row['activities'] ?: '-' }}</td><td>{{ $row['lckh']?->isComplete() ? 'Selesai' : ($row['lckh'] ? 'Draf' : 'Belum diisi') }}</td><td>{{ $approvalLabels[$row['approval']?->status ?? 'pending'] }}<div class="text small">{{ $row['approval']?->notes }}</div></td></tr>
            @empty <tr><td colspan="5">Belum ada data.</td></tr> @endforelse
        </tbody></table>
    @else
        <p>{{ $rows->count() }} sesi tercatat | Total {{ $rows->sum('periods') }} JP</p>
        <table class="data"><thead><tr><th style="width: 3%">No</th><th style="width: 10%">Tanggal</th><th style="width: 7%">Kelas</th><th style="width: 13%">Mata pelajaran</th><th style="width: 4%">JP</th><th style="width: 25%">Uraian materi</th><th style="width: 15%">Catatan</th><th>Refleksi</th><th style="width: 7%">Status</th></tr></thead><tbody>
            @forelse ($printRows as $row)
                <tr><td>{{ $row['number'] }}{{ $row['continued'] ? '*' : '' }}</td><td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td>{{ $row['class'] }}</td><td>{{ $row['subject'] }}</td><td class="center">{{ $row['continued'] ? '-' : $row['periods'] }}</td><td class="text">{{ $row['material'] ?: '-' }}</td><td class="text">{{ $row['notes'] ?: '-' }}</td><td class="text">{{ $row['reflection'] ?: '-' }}</td><td>{{ $row['status'] }}</td></tr>
            @empty <tr><td colspan="9">Belum ada jurnal mengajar pada periode ini.</td></tr> @endforelse
        </tbody></table>
        <p class="small">Jumlah JP dihitung dari jam pelajaran dalam setiap sesi, bukan jumlah jurnal.</p>
    @endif
    <p class="small">* Baris lanjutan untuk uraian panjang.</p><p class="small">Laporan dicetak sesuai data{{ $kind === 'lckh' ? ' dan status approval LCKH' : '' }} saat diunduh. Tanggal cetak: {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB.</p>
    <div class="signature">Mengetahui,<br>Kepala Madrasah<div class="signature-space"></div><strong>{{ $school->headmaster_name ?: 'Kepala Madrasah' }}</strong>@if ($school->headmaster_nip)<br>NIP. {{ $school->headmaster_nip }}@endif</div>
    <div class="footer">{{ $gtk->full_name }} | {{ $title }} | {{ $month->format('m/Y') }} <span style="float: right">Halaman <span class="page-number"></span></span></div>
</body></html>
