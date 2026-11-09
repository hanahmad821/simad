@extends('pdf.letters.layouts.letter')

@section('content')
    @php
                $dateValue = (string) data_get($data, 'hari_tanggal', '-');
                $isIsoDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $dateValue, $dateParts);
                $activityDate = $isIsoDate && checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])
                    ? \Carbon\Carbon::parse($dateValue)->locale('id')->translatedFormat('l, d F Y')
                    : $dateValue;
    @endphp

    <style>
        .dispensasi { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.25; }
        .dispensasi p { margin: 0 0 12pt; }
        .dispensasi .meta { width: 100%; margin-bottom: 20pt; border-collapse: collapse; }
        .dispensasi .meta td { padding: 0; vertical-align: top; }
        .dispensasi .meta-label { width: 55pt; }
        .dispensasi .tanggal { text-align: right; white-space: nowrap; }
        .dispensasi .jadwal { width: 100%; border-collapse: collapse; margin: 0 0 12pt; }
        .dispensasi .jadwal td { padding: 0 0 2pt; vertical-align: top; }
        .dispensasi .jadwal-label { width: 80pt; }
        .dispensasi .titik-dua { width: 10pt; }
        .dispensasi .isi { text-align: justify; }
        .dispensasi .signature { margin-top: 18pt; margin-left: 57%; width: 43%; text-align: left; page-break-inside: avoid; }
    </style>

    <div class="dispensasi">
        <table class="meta">
            <tr>
                <td class="meta-label">Nomor</td>
                <td>: {{ $letter->nomor_surat }}</td>
                <td class="tanggal">Kemranjen, {{ $letter->tanggal_surat?->locale('id')->translatedFormat('d F Y') }}</td>
            </tr>
            <tr><td>Lampiran</td><td colspan="2">: -</td></tr>
            <tr><td>Perihal</td><td colspan="2">: {{ $letter->perihal ?: 'Permohonan Dispensasi' }}</td></tr>
        </table>

        <p>Kepada Yth.<br>{!! nl2br(e($letter->tujuan)) !!}<br>Di<br>&nbsp;&nbsp;&nbsp;&nbsp;Tempat</p>

        <p><strong><i>Assalamu'alaikum Warahmatullahi Wabarakatuh<br>Bismillahirrahmanirrahim</i></strong></p>

        <p class="isi">Salam sejahtera kami sampaikan semoga kita selalu dalam lindungan Allah SWT. Shalawat dan salam semoga tercurah kepada Nabi kita Muhammad SAW, keluarga, sahabat, dan pengikutnya hingga akhir zaman. Aamiin.</p>

        <p class="isi">Sehubungan dengan akan diadakannya kegiatan {{ data_get($data, 'acara', '-') }} {{ $school->school_name }}, yang akan dilaksanakan dengan ketentuan sebagai berikut:</p>

        <table class="jadwal">
            <tr><td class="jadwal-label">Hari/tanggal</td><td class="titik-dua">:</td><td>{{ $activityDate }}</td></tr>
            <tr><td>Waktu</td><td>:</td><td>{{ data_get($data, 'waktu', '-') }}</td></tr>
            <tr><td>Tempat</td><td>:</td><td>{{ data_get($data, 'tempat', '-') }}</td></tr>
            <tr><td>Acara</td><td>:</td><td>{!! nl2br(e(data_get($data, 'acara', '-'))) !!}</td></tr>
        </table>

        <p class="isi">Maka kami mohon kepada Bapak/Ibu untuk memberikan dispensasi kepada {{ data_get($data, 'nama', '-') }} untuk mengikuti acara tersebut.</p>

        <p class="isi">Demikian surat permohonan dispensasi ini kami sampaikan. Atas kerja sama Bapak/Ibu kami sampaikan terima kasih.</p>

        <p><strong><i>Wallahul Muwaffiq Ila Aqwamith-thariq<br>Wassalamu'alaikum Warahmatullahi Wabarakatuh</i></strong></p>

        <div class="signature">
            <div>Kepala Madrasah</div>
            @if (! empty($qrPath))
                <div style="margin: 5px 0;"><img src="{{ $qrPath }}" style="width: 25mm; height: 25mm;"></div>
            @else
                <div style="height: 25mm;"></div>
            @endif
            <strong>{{ $school->headmaster_name }}</strong>
            @if ($school->headmaster_nip)<br>NIP. {{ $school->headmaster_nip }}@endif
        </div>
    </div>
@endsection
