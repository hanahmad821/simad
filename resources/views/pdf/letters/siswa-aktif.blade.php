@extends('pdf.letters.layouts.letter')

@section('content')

    @php
        $studentId = data_get($data, 'student_id');

        $student = $studentId
            ? \App\Models\Student::with('classHistories.schoolClass')->find($studentId)
            : null;

        $tahunPelajaran = data_get($data, 'tahun_pelajaran', '');

        $classHistory = $student?->classHistories
                ?->sortByDesc('id')
            ->first();

        $schoolClass = $classHistory?->schoolClass;
    @endphp

    <div style="text-align: center; margin-bottom: 25px;">
        <strong style="font-size: 14pt; text-decoration: underline;">
            SURAT KETERANGAN
        </strong>

        <br>

        <span>
            Nomor: {{ $letter->nomor_surat }}
        </span>
    </div>

    <p>
        Yang bertanda tangan di bawah ini:
    </p>

    <table style="width: 100%; margin-left: 20px;">
        <tr>
            <td style="width: 150px;">Nama</td>
            <td>: {{ $school->headmaster_name }}</td>
        </tr>

        <tr>
            <td>NIP</td>
            <td>: {{ $school->headmaster_nip ?: '-' }}</td>
        </tr>
        <tr>
            <td>Pangkat/Golongan</td>
            <td>: {{ $school->headmaster_rank ?: '-' }}</td>
        </tr>

        <tr>
            <td>Jabatan</td>
            <td>: {{ $school->headmaster_position }}</td>
        </tr>
    </table>

    <p style="margin-top: 20px;">
        Dengan ini menerangkan bahwa:
    </p>

    <table style="width: 100%; margin-left: 20px;">
        <tr>
            <td style="width: 150px;">Nama</td>
            <td>: <strong>{{ $student?->full_name ?? '-' }}</strong></td>
        </tr>

        <tr>
            <td>Tempat, Tanggal Lahir</td>
            <td>
                :
                {{ $student?->birth_place ?: '-' }},
                {{ $student?->birth_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}
            </td>
        </tr>

        <tr>
            <td>NISN</td>
            <td>: {{ $student?->nisn ?: '-' }}</td>
        </tr>

        <tr>
            <td>Kelas</td>
            <td>: {{ $schoolClass?->name ?: '-' }}</td>
        </tr>
    </table>

    <p style="margin-top: 20px; text-align: justify;">
        Adalah benar-benar siswa
        {{ $school->school_name }}
        dan sampai dengan surat keterangan ini dibuat masih tercatat
        sebagai siswa aktif pada Tahun Pelajaran
        <strong>{{ $tahunPelajaran ?: '-' }}</strong>.
    </p>

    @if (data_get($data, 'keperluan'))
        <p style="text-align: justify;">Surat keterangan ini dibuat untuk keperluan {{ data_get($data, 'keperluan') }}.</p>
    @endif

    <p style="text-align: justify;">
        Demikian surat keterangan ini dibuat dengan sebenarnya untuk
        dapat dipergunakan sebagaimana mestinya.
    </p>

    <table style="
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
            margin-top: 30px;
        ">
        <tr>
            <td style="
                    width: 55%;
                    border: none;
                ">
            </td>

            <td style="
                    width: 45%;
                    border: none;
                    text-align: center;
                    vertical-align: top;
                ">
                <div>
                    {{ $school->address ?? '' }}
                </div>

                <div style="margin-top: 8px;">
                    Kepala Madrasah,
                </div>

                @if (!empty($qrPath))
                    <div style="margin-top: 5px; margin-bottom: 5px;">
                        <img src="{{ $qrPath }}" style="
                                    width: 25mm;
                                    height: 25mm;
                                ">
                    </div>
                @else
                    <div style="height: 25mm;"></div>
                @endif

                <div>
                    <strong>
                        {{ $school->headmaster_name }}
                    </strong>
                </div>

                @if ($school->headmaster_nip)
                    <div style="margin-top: 3px;">
                        NIP. {{ $school->headmaster_nip }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

@endsection
