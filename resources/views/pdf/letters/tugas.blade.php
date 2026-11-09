@extends('pdf.letters.layouts.letter')

@section('content')
    @php($assignment = app(\App\Services\AssignmentLetterService::class)->viewData($letter, $school))
    <style>
        .surat-tugas { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.2; }
        .surat-tugas p { margin: 0 0 9pt; }
        .surat-tugas .title { text-align: center; margin: 0 0 14pt; }
        .surat-tugas .identity { width: 100%; border-collapse: collapse; margin: 0 0 10pt; }
        .surat-tugas .identity td { padding: 0 0 2pt; vertical-align: top; }
        .surat-tugas .number { width: 20pt; }
        .surat-tugas .label { width: 90pt; }
        .surat-tugas .colon { width: 10pt; }
        .surat-tugas .recipient { page-break-inside: avoid; }
        .surat-tugas .signature { margin-top: 14pt; margin-left: 56%; width: 44%; text-align: left; page-break-inside: avoid; }
    </style>
    <div class="surat-tugas">
        <div class="title"><strong style="font-size: 14pt; text-decoration: underline;">SURAT TUGAS</strong><br>Nomor: {{ $letter->nomor_surat }}</div>
        <p>Yang bertanda tangan di bawah ini:</p>
        <table class="identity">
            <tr><td class="number"></td><td class="label">Nama</td><td class="colon">:</td><td>{{ $assignment['signer']['nama'] }}</td></tr>
            <tr><td></td><td>NIP</td><td>:</td><td>{{ $assignment['signer']['nip'] }}</td></tr>
            <tr><td></td><td>Pangkat/Gol.</td><td>:</td><td>{{ $assignment['signer']['pangkat'] }}</td></tr>
            <tr><td></td><td>Jabatan</td><td>:</td><td>{{ $assignment['signer']['jabatan'] }}</td></tr>
            <tr><td></td><td>Alamat</td><td>:</td><td>{{ $assignment['signer']['alamat'] }}</td></tr>
        </table>
        <p>Dengan ini memberikan tugas kepada:</p>
        @foreach ($assignment['recipients'] as $index => $person)
            <table class="identity recipient">
                <tr><td class="number">{{ $index + 1 }}.</td><td class="label">Nama</td><td class="colon">:</td><td>{{ $person['nama'] }}</td></tr>
                <tr><td></td><td>NIP</td><td>:</td><td>{{ $person['nip'] }}</td></tr>
                <tr><td></td><td>Pangkat/Gol.</td><td>:</td><td>{{ $person['pangkat'] }}</td></tr>
                <tr><td></td><td>Jabatan</td><td>:</td><td>{{ $person['jabatan'] }}</td></tr>
            </table>
        @endforeach
        <p style="text-align: justify;">Dengan tugas: <strong>{!! nl2br(e(data_get($data, 'tugas', '-'))) !!}</strong></p>
        <p>Dengan ketentuan sebagai berikut:</p>
        <table class="identity">
            <tr><td class="number">1.</td><td class="label">Hari/tanggal</td><td class="colon">:</td><td>{{ \Carbon\Carbon::parse(data_get($data, 'tanggal_tugas'))->locale('id')->translatedFormat('l, d F Y') }}</td></tr>
            <tr><td>2.</td><td>Waktu/jam</td><td>:</td><td>{{ data_get($data, 'waktu', '-') }}</td></tr>
            <tr><td>3.</td><td>Tempat</td><td>:</td><td>{!! nl2br(e(data_get($data, 'tempat', '-'))) !!}</td></tr>
        </table>
        <p style="text-align: justify;">Demikian surat tugas ini kami buat untuk dipergunakan sebagaimana mestinya. Terima kasih.</p>
        <div class="signature">
            <div>Kemranjen, {{ $letter->tanggal_surat?->locale('id')->translatedFormat('d F Y') }}</div>
            <div>Kepala Madrasah</div>
            @if (!empty($qrPath))
                <div style="margin: 5px 0;"><img src="{{ $qrPath }}" style="width: 25mm; height: 25mm;"></div>
            @else
                <div style="height: 25mm;"></div>
            @endif
            <strong>{{ $assignment['signer']['nama'] }}</strong>
            @if ($assignment['signer']['nip'] !== '-')<br>NIP. {{ $assignment['signer']['nip'] }}@endif
        </div>
    </div>
@endsection
