<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Kartu Siswa — {{ $school?->school_name ?? config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f0; color: #172b25; font-family: Arial, sans-serif; }
        .toolbar { max-width: 1000px; margin: 24px auto; padding: 22px; background: white; border: 1px solid #d8e2dc; border-radius: 16px; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        .toolbar p { color: #52675d; font-size: 14px; line-height: 1.6; }
        .actions, .filters { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; }
        .actions { margin-top: 18px; }
        label { display: grid; gap: 5px; font-size: 13px; }
        select, input, button, .button { padding: 10px 14px; border: 1px solid #cbd8d0; border-radius: 8px; font: inherit; background: white; color: #172b25; }
        input { max-width: 220px; }
        button, .button { cursor: pointer; text-decoration: none; font-size: 14px; }
        .primary { background: #155b43; border-color: #155b43; color: white; font-weight: bold; }
        .count { margin-left: auto; font-size: 13px; color: #52675d; }
        .sheet { width: 190mm; min-height: 277mm; margin: 20px auto; padding: 4mm 0; display: grid; grid-template-columns: repeat(3, 54mm); grid-auto-rows: 85.6mm; gap: 6mm; justify-content: center; align-content: start; background: white; }
        .card { width: 54mm; height: 85.6mm; border: .2mm solid #b9ccc1; border-radius: 3mm; overflow: hidden; background: white; position: relative; display: flex; flex-direction: column; color: #142e24; break-inside: avoid; }
        .card-header { height: 18mm; flex: 0 0 18mm; display: flex; align-items: center; gap: 2mm; padding: 2.5mm 3mm; background: #155b43; color: white; border-bottom: 1mm solid #d1b568; }
        .school-logo { display: block; width: 10mm; height: 10mm; flex: 0 0 10mm; object-fit: contain; }
        .school-heading { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 1mm; }
        .card-header .label { font-size: 5.7pt; letter-spacing: 1.5px; font-weight: bold; }
        .school-name { font-size: 8.5pt; font-weight: bold; line-height: 1.2; overflow-wrap: anywhere; }
        .identity { flex: 1; min-height: 0; padding: 2mm 4mm 1mm; display: flex; flex-direction: column; }
        .eyebrow { margin: 0 0 1.5mm; color: #527567; font-size: 5.6pt; letter-spacing: 1px; font-weight: bold; }
        .student-name { margin: 0; font-size: 11.5pt; line-height: 1.15; font-weight: 800; overflow-wrap: anywhere; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm 3mm; margin: auto 0 0; padding-top: 2mm; }
        dt { color: #63786e; font-size: 5.5pt; margin-bottom: .6mm; }
        dd { margin: 0; font-size: 7pt; font-weight: bold; overflow-wrap: anywhere; }
        .class-value { font-size: 6.8pt; }
        .qr-code { flex: 0 0 28mm; width: 48mm; align-self: center; padding: .5mm 2.5mm 1mm; text-align: center; background: white; color: black; }
        .qr-code img { display: block; width: 23mm; height: 23mm; margin: 0 auto; }
        .qr-code-value { margin-top: .5mm; font: 6.3pt Consolas, monospace; letter-spacing: .2px; overflow-wrap: anywhere; }
        .qr-code-missing { font-size: 7pt; line-height: 1.4; padding-top: 3mm; color: #9e321f; }
        .card-footer { flex: 0 0 7mm; display: flex; align-items: center; justify-content: center; background: #edf5f0; }
        .madika-logo { display: block; width: 21mm; height: 5.6mm; object-fit: contain; }
        .empty { max-width: 1000px; margin: 24px auto; padding: 24px; background: white; border-radius: 12px; }
        @media screen and (max-width: 760px) {
            .toolbar { margin: 12px; }
            .sheets { overflow-x: auto; }
            .sheet { margin: 12px; }
        }
        @page { size: A4 portrait; margin: 10mm; }
        @media print {
            html, body { background: white; margin: 0; padding: 0; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar, .empty { display: none; }
            .sheet { margin: 0; padding: 0; min-height: 0; height: auto; break-after: page; }
            .sheet:last-child { break-after: auto; }
            .card { border-color: #a6b9ad; }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <h1>Kartu Siswa</h1>
        <p>Ukuran kartu 54 × 85,6 mm, posisi tegak untuk holder lanyard. Satu lembar A4 memuat hingga 9 kartu.
            Cetak pada <strong>skala 100% / ukuran sebenarnya</strong>, matikan header dan footer browser, lalu aktifkan grafis latar belakang.</p>
        <form class="filters" method="get" action="{{ route('admin.students.cards') }}">
            @if (!empty($filters['student_id']))
                <input type="hidden" name="student_id" value="{{ $filters['student_id'] }}">
            @endif
            <label>Kelas
                <select name="school_class_id">
                    <option value="">Semua kelas</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['school_class_id'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="active" @selected(($filters['status'] ?? 'active') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Tidak aktif</option>
                    <option value="all" @selected(($filters['status'] ?? '') === 'all')>Semua</option>
                </select>
            </label>
            <label>Cari siswa<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / NIS / NISN" maxlength="150"></label>
            <button type="submit">Tampilkan</button>
        </form>
        <div class="actions">
            <button class="primary" type="button" onclick="window.print()" @disabled($students->isEmpty())>Cetak / Simpan PDF</button>
            <a class="button" href="{{ route('admin.students.index') }}">Kembali ke Data Siswa</a>
            <span class="count">{{ $students->count() }} kartu · {{ (int) ceil($students->count() / 9) }} lembar A4</span>
        </div>
        @if (!$school?->school_name)
            <p>Nama madrasah belum diisi di profil madrasah. Lengkapi sebelum kartu dicetak.</p>
        @endif
        @if ($qrCodes->contains(fn ($qrCode) => !$qrCode))
            <p>Ada siswa tanpa kode QR yang valid. Periksa data siswa tersebut sebelum mencetak kartunya.</p>
        @endif
    </header>
    <main class="sheets">
        @forelse ($students->chunk(9) as $sheet)
            <section class="sheet" aria-label="Lembar kartu {{ $loop->iteration }}">
                @foreach ($sheet as $student)
                    @php
                        $history = $student->classHistories->first();
                        $nameSize = match (true) {
                            mb_strlen($student->full_name) > 90 => '6.8pt',
                            mb_strlen($student->full_name) > 60 => '8pt',
                            mb_strlen($student->full_name) > 35 => '9.5pt',
                            default => '11.5pt',
                        };
                    @endphp
                    <article class="card" aria-label="Kartu {{ $student->full_name }}">
                        <div class="card-header">
                            <img class="school-logo" src="{{ asset('brand/school/logo-maarif.png') }}" width="1254" height="1250" alt="Logo madrasah">
                            <div class="school-heading">
                            <span class="label">KARTU PELAJAR</span>
                            <div class="school-name" @if(mb_strlen($school?->school_name ?? '') > 60) style="font-size:6pt" @endif>{{ $school?->school_name ?: 'Nama Madrasah Belum Diisi' }}</div>
                            </div>
                        </div>
                        <div class="identity">
                            <p class="eyebrow">IDENTITAS SISWA</p>
                            <h2 class="student-name" style="font-size:{{ $nameSize }}">{{ $student->full_name }}</h2>
                            <dl class="details">
                                <div><dt>NIS</dt><dd>{{ $student->nis ?: '—' }}</dd></div>
                                <div><dt>NISN</dt><dd>{{ $student->nisn ?: '—' }}</dd></div>
                                <div><dt>Kelas</dt><dd class="class-value">{{ $history?->schoolClass?->name ?: 'Belum ditempatkan' }}</dd></div>
                                <div><dt>Tahun ajaran</dt><dd>{{ $history?->academicYear?->name ?: '—' }}</dd></div>
                            </dl>
                        </div>
                        <div class="qr-code">
                            @if ($qrCodes[$student->id])
                                <img src="{{ $qrCodes[$student->id] }}" alt="QR code presensi {{ $student->full_name }}">
                                <div class="qr-code-value">{{ $student->student_code }}</div>
                            @else
                                <div class="qr-code-missing">QR code belum tersedia.<br>Periksa kode siswa.</div>
                            @endif
                        </div>
                        <div class="card-footer"><img class="madika-logo" src="{{ $madikaLogo }}" alt="MADIKA — Madrasah Digital Kita"></div>
                    </article>
                @endforeach
            </section>
        @empty
            <div class="empty">Tidak ada siswa yang sesuai dengan filter.</div>
        @endforelse
    </main>
</body>
</html>
