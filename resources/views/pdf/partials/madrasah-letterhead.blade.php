    @php
        $letterheadLogoPath = is_file(storage_path('app/public/images/lp.jpg'))
            ? storage_path('app/public/images/lp.jpg') : public_path('brand/school/logo-maarif.png');
        $letterheadLogo = is_file($letterheadLogoPath)
            ? 'data:'.(str_ends_with($letterheadLogoPath, '.png') ? 'image/png' : 'image/jpeg').';base64,'.base64_encode(file_get_contents($letterheadLogoPath)) : null;
    @endphp
    <table class="kop">
        <tr>

            {{-- LOGO / IDENTITAS --}}
            <td class="kop-logo">
                @if ($letterheadLogo)<img src="{{ $letterheadLogo }}" style="width: 130px; height: auto;">@endif
            </td>
            {{-- INFORMASI MADRASAH --}}
            <td class="kop-info">

                <div class="kop-foundation">
                    LEMBAGA PENDIDIKAN MA'ARIF CABANG KABUPATEN BANYUMAS
                </div>

                <div class="kop-title">
                    {{ mb_strtoupper($school->school_name ?: "MA MA'ARIF NU 1 KEMRANJEN") }}
                </div>

                <div class="kop-accreditation">
                    (TERAKREDITASI B)
                </div>

                <div class="kop-address">
                    Alamat : {{ $school->address ?? 'Sirau RT 03 RW 02 Kemranjen Banyumas Kode Pos 53194' }}
                    <br>

                    @if ($school->phone)
                        HP : {{ $school->phone }}
                    @endif

                    @if ($school->phone && $school->email)
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                    @endif

                    @if ($school->email)
                        Email : {{ $school->email }}
                    @endif

                    &nbsp;&nbsp;|&nbsp;&nbsp;

                    NPSN : {{ $school->npsn ?: '20364920' }}
                </div>

            </td>

        </tr>
    </table>

    <div class="kop-line"></div>

