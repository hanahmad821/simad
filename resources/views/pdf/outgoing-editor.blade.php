<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>{{ $template['name'] }}</title>
<style>
    @page { margin: 18mm 18mm 20mm; }
    body { font-family: "DejaVu Serif", serif; font-size: 11pt; line-height: 1.45; color: #111; }
    .kop { width: 100%; border-collapse: collapse; }
    .kop td { vertical-align: middle; padding: 0; }
    .kop-logo { width: 19%; text-align: center; }
    .kop-logo img { width: 80px !important; height: auto; }
    .kop-info { text-align: center; color: #087443; }
    .kop-foundation { font-size: 8pt; font-weight: bold; }
    .kop-title { font-size: 15pt; line-height: 1.2; font-weight: bold; }
    .kop-accreditation { font-size: 9pt; }
    .kop-address { font-size: 8pt; }
    .kop-line { border-top: 3px solid #087443; border-bottom: 1px solid #087443; height: 2px; margin: 8px 0 18px; }
    h1 { font-size: 12pt; text-align: center; text-transform: uppercase; margin: 0 0 15px; }
    .metadata { border-collapse: collapse; margin-bottom: 15px; width: 100%; }
    .metadata td { padding: 1px 0; vertical-align: top; }
    .letter-body p { margin: 0 0 10px; }
    .letter-body h2 { font-size: 13pt; } .letter-body h3 { font-size: 12pt; }
    .letter-body table { width: 100%; border-collapse: collapse; margin: 10px 0; table-layout: fixed; }
    .letter-body td, .letter-body th { border: 1px solid #555; padding: 5px; vertical-align: top; word-wrap: break-word; }
    .letter-body tr { page-break-inside: avoid; }
    .signature { width: 45%; margin: 22px 0 0 auto; text-align: center; page-break-inside: avoid; }
    .signature-space { height: 75px; } .qr { width: 75px; height: 75px; }
    .sample { border: 1px dashed #999; padding: 5px; margin-bottom: 15px; text-align: center; font-size: 9pt; color: #666; }
</style></head>
<body>
    @include('pdf.partials.madrasah-letterhead')
    @if ($preview)<div class="sample">PRATINJAU / DRAF — belum merupakan surat yang diterbitkan</div>@endif
    <h1>{{ $template['name'] }}</h1>
    <table class="metadata">
        <tr><td style="width: 80px">Nomor</td><td style="width: 12px">:</td><td>{{ $letter->nomor_surat }}</td></tr>
        <tr><td>Tanggal</td><td>:</td><td>{{ $letter->tanggal_surat?->locale('id')->translatedFormat('d F Y') }}</td></tr>
        <tr><td>Perihal</td><td>:</td><td>{{ $letter->perihal }}</td></tr>
    </table>
    <p>Yth. {{ $letter->tujuan }}</p>
    <div class="letter-body">{!! $body !!}</div>
    <div class="signature">
        <div>Kepala Madrasah,</div>
        @if (! $preview && $qrPath)<div><img class="qr" src="{{ $qrPath }}" alt="QR verifikasi"></div>@else<div class="signature-space"></div>@endif
        <strong>{{ ($letter->template_snapshot['values']['nama_kepala_madrasah'] ?? $school->headmaster_name) ?: '[Nama kepala madrasah]' }}</strong><br>
        @if ($nip = $letter->template_snapshot['values']['nip_kepala_madrasah'] ?? $school->headmaster_nip) NIP. {{ $nip }} @endif
    </div>
</body></html>
