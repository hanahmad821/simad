@props([
    'school' => [],
    'letter' => [],
    'student' => [],
    'principal' => [],
    'qrCode' => null,
])

@php
    $schoolName = data_get($school, 'name', 'NAMA SEKOLAH');
    $schoolAddress = data_get($school, 'address', 'Alamat sekolah');
    $schoolContact = collect([
        data_get($school, 'phone'),
        data_get($school, 'email'),
        data_get($school, 'website'),
    ])->filter()->implode(' · ');

    $letterDate = data_get($letter, 'date');
    $letterDate = is_object($letterDate) && method_exists($letterDate, 'translatedFormat')
        ? $letterDate->translatedFormat('d F Y')
        : ($letterDate ?: '[Tanggal surat]');

    $birthDate = data_get($student, 'birth_date');
    $birthDate = is_object($birthDate) && method_exists($birthDate, 'translatedFormat')
        ? $birthDate->translatedFormat('d F Y')->locale('id')
        : ($birthDate ?: '[Tanggal lahir]');
@endphp

<div class="active-student-letter">
    <style>
        .active-student-letter {
            color: #111;
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.45;
        }
        .active-student-letter .letterhead {
            width: 100%;
            border-bottom: 3px double #111;
            margin-bottom: 22px;
            padding-bottom: 10px;
            text-align: center;
        }
        .active-student-letter .letterhead td { vertical-align: middle; }
        .active-student-letter .school-logo { width: 82px; max-height: 82px; object-fit: contain; }
        .active-student-letter .school-name { margin: 0; font-size: 16pt; font-weight: bold; text-transform: uppercase; }
        .active-student-letter .school-contact { margin-top: 4px; font-size: 9.5pt; }
        .active-student-letter .letter-date { margin: 0 0 14px; text-align: right; }
        .active-student-letter .meta { margin: 0 0 18px; }
        .active-student-letter .meta td { vertical-align: top; }
        .active-student-letter .meta td:first-child { width: 105px; }
        .active-student-letter .meta td:nth-child(2) { width: 14px; }
        .active-student-letter .student-data { margin: 10px 0 14px 24px; }
        .active-student-letter .student-data td { vertical-align: top; padding: 1px 0; }
        .active-student-letter .student-data td:first-child { width: 180px; }
        .active-student-letter .student-data td:nth-child(2) { width: 15px; }
        .active-student-letter .signature { width: 270px; margin: 28px 0 0 auto; text-align: center; }
        .active-student-letter .signature-space { height: 68px; }
        .active-student-letter .qr-code { display: block; width: 82px; height: 82px; object-fit: contain; margin: -58px 0 8px auto; }
    </style>

    <table class="letterhead">
        <tr>
            @if (data_get($school, 'logo_url'))
                <td style="width: 95px">
                    <img class="school-logo" src="{{ data_get($school, 'logo_url') }}" alt="Logo sekolah">
                </td>
            @endif
            <td>
                <p class="school-name">{{ $schoolName }}</p>
                <div>{{ $schoolAddress }}</div>
                @if ($schoolContact !== '')
                    <div class="school-contact">{{ $schoolContact }}</div>
                @endif
            </td>
            @if (data_get($school, 'logo_url'))
                <td style="width: 95px"></td>
            @endif
        </tr>
    </table>

    <p class="letter-date">{{ data_get($school, 'city', '[Kota]') }}, {{ $letterDate }}</p>

    <table class="meta">
        <tr><td>Nomor</td><td>:</td><td>{{ $letter->nomor_surat ?? '[Nomor surat]' }}</td></tr>
        <tr><td>Lampiran</td><td>:</td><td>{{ data_get($letter, 'attachment', '—') }}</td></tr>
        <tr><td>Perihal</td><td>:</td><td><strong>Keterangan Siswa Aktif</strong></td></tr>
    </table>

    <p style="margin: 0 0 3px">Yth. {{ data_get($letter, 'recipient', '[Nama penerima / pihak yang dituju]') }}</p>
    <p style="margin: 0 0 18px">di {{ data_get($letter, 'recipient_location', '[Tempat]') }}</p>

    <p style="margin: 0 0 12px">Dengan hormat,</p>
    <p style="margin: 0 0 8px">Yang bertanda tangan di bawah ini:</p>

    <table class="student-data" style="margin-left: 0">
        <tr><td>Nama</td><td>:</td><td>{{ data_get($principal, 'name', '[Nama kepala sekolah]') }}</td></tr>
        <tr><td>Jabatan</td><td>:</td><td>Kepala {{ $schoolName }}</td></tr>
        <tr><td>NIP / NUPTK</td><td>:</td><td>{{ data_get($principal, 'identity_number', '—') }}</td></tr>
    </table>

    <p style="margin: 14px 0 8px">dengan ini menerangkan bahwa:</p>

    <table class="student-data">
        <tr><td>Nama siswa</td><td>:</td><td>{{ data_get($student, 'name', '[Nama lengkap siswa]') }}</td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ data_get($student, 'nis', '—') }} / {{ data_get($student, 'nisn', '—') }}</td></tr>
        <tr><td>Tempat, tanggal lahir</td><td>:</td><td>{{ data_get($student, 'birth_place', '[Tempat lahir]') }}, {{ $birthDate }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ data_get($student, 'class_name', '[Kelas]') }}</td></tr>
        <tr><td>Tahun pelajaran</td><td>:</td><td>{{ data_get($student, 'academic_year', '[Tahun pelajaran]') }}</td></tr>
        <tr><td>Alamat</td><td>:</td><td>{{ data_get($student, 'address', '[Alamat siswa]') }}</td></tr>
    </table>

    <p style="text-align: justify; margin: 0 0 12px">
        adalah benar siswa aktif di {{ $schoolName }} pada tahun pelajaran
        {{ data_get($student, 'academic_year', '[Tahun pelajaran]') }}.
    </p>
    <p style="text-align: justify; margin: 0">
        Surat keterangan ini dibuat untuk keperluan
        <strong>{{ data_get($letter, 'purpose', '[Keperluan surat]') }}</strong>.
        Demikian surat ini dibuat dengan sebenarnya agar dapat dipergunakan sebagaimana mestinya.
    </p>

    <div class="signature">
        <div>{{ data_get($school, 'city', '[Kota]') }}, {{ $letterDate }}</div>
        <div>Kepala {{ $schoolName }},</div>
        <div class="signature-space"></div>
        @if ($qrCode)
            <img class="qr-code" src="{{ $qrCode }}" alt="QR tanda tangan elektronik">
        @endif
        <strong>{{ data_get($principal, 'name', '[Nama kepala sekolah]') }}</strong><br>
        NIP / NUPTK. {{ data_get($principal, 'identity_number', '—') }}
    </div>
</div>
