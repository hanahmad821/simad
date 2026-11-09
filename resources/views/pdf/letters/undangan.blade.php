@extends('pdf.letters.layouts.letter')

@section('content')

    <table class="meta">
        <tr>
            <td class="meta-label">Nomor</td>
            <td>: {{ $letter->nomor_surat }}</td>
        </tr>

        <tr>
            <td>Perihal</td>
            <td>: {{ $letter->perihal }}</td>
        </tr>
    </table>

    <p>
        Kepada Yth.<br>
        <strong>{{ $letter->tujuan }}</strong>
    </p>

    <p>
        Assalamu'alaikum warahmatullahi wabarakatuh.
    </p>

    <div class="letter-body">
        {!! app(\App\Services\LetterBodyService::class)->render($letter) !!}
    </div>

    <p>
        Demikian surat ini kami sampaikan. Atas perhatian dan
        kerjasamanya kami ucapkan terima kasih.
    </p>

    <p>
        Wassalamu'alaikum warahmatullahi wabarakatuh.
    </p>

    <div class="signature">

        <p>
            {{ $school->school_name }}
        </p>

        @if (!empty($qrPath))
            <img src="{{ $qrPath }}" width="80" height="80">
        @else
            <br><br><br>
        @endif

        <strong>
            {{ $school->headmaster_name ?? '................................' }}
        </strong>

        <br>

        Kepala Madrasah

        @if ($school->headmaster_nip)
            <br>NIP. {{ $school->headmaster_nip }}
        @endif

    </div>

@endsection
