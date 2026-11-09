@extends('pdf.letters.layouts.letter')

@section('content')
    <style>
        .pemberitahuan {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.25;
        }

        .pemberitahuan p {
            margin: 0 0 12pt;
        }

        .pemberitahuan .meta {
            width: 100%;
            margin-bottom: 20pt;
            border-collapse: collapse;
        }

        .pemberitahuan .meta td {
            padding: 0;
            vertical-align: top;
        }

        .pemberitahuan .meta-label {
            width: 55pt;
        }

        .pemberitahuan .tanggal {
            text-align: right;
            white-space: nowrap;
        }

        .pemberitahuan .letter-body {
            text-align: justify;
        }

        .pemberitahuan .signature {
            margin-top: 18pt;
            margin-left: 57%;
            width: 43%;
            text-align: left;
            page-break-inside: avoid;
        }
    </style>

    <div class="pemberitahuan">
        <table class="meta">
            <tr>
                <td class="meta-label">Nomor</td>
                <td>: {{ $letter->nomor_surat }}</td>
                <td class="tanggal">Kemranjen, {{ $letter->tanggal_surat?->locale('id')->translatedFormat('d F Y') }}</td>
            </tr>
            <tr>
                <td>Lampiran</td>
                <td colspan="2">: {{ $letter->attachmentDescription() }}</td>
            </tr>
            <tr>
                <td>Perihal</td>
                <td colspan="2">: {{ $letter->perihal ?: 'Pemberitahuan' }}</td>
            </tr>
        </table>

        <p>Kepada Yth.<br>{!! nl2br(e($letter->tujuan)) !!}<br>Di Tempat</p>

        <p><strong><i>Assalamu'alaikum Warahmatullahi Wabarakatuh<br>Bismillahirrahmanirrahim</i></strong></p>
        <p class="letter-body">Salam sejahtera kami sampaikan semoga kita selalu dalam lindungan Allah SWT. Shalawat dan
            salam semoga tercurah kepada Nabi kita Muhammad SAW, keluarga, sahabat, dan pengikutnya hingga akhir zaman.
            Aamiin.</p>

        <div class="letter-body">{!! app(\App\Services\LetterBodyService::class)->render($letter) !!}</div>

        <p class="letter-body">Demikian surat pemberitahuan ini kami sampaikan, atas perhatian dan kerjasama Bapak/Ibu kami
            sampaikan terimakasih.</p>

        <p><strong><i>Wallahul Muwaffiq Ila Aqwamith-thariq<br>Wassalamu'alaikum Warahmatullahi Wabarakatuh</i></strong></p>

        <div class="signature">
            <div>Kepala Madrasah</div>
            @if (!empty($qrPath))
                <div style="margin: 5px 0;"><img src="{{ $qrPath }}" style="width: 25mm; height: 25mm;"></div>
            @else
                <div style="height: 25mm;"></div>
            @endif
            <strong>{{ $school->headmaster_name }}</strong>
            @if ($school->headmaster_nip)<br>NIP. {{ $school->headmaster_nip }}@endif
        </div>
    </div>
@endsection
