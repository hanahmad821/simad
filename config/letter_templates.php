<?php

return [
    'pdf.letters.siswa-aktif' => [
        'name' => 'Surat Keterangan Siswa Aktif',
        'description' => 'Keterangan status siswa aktif dengan identitas siswa dan tahun pelajaran.',
        'fields' => [
            ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'student', 'required' => true],
            ['name' => 'tahun_pelajaran', 'label' => 'Tahun Pelajaran', 'type' => 'text', 'required' => true],
            ['name' => 'keperluan', 'label' => 'Keperluan', 'type' => 'textarea', 'required' => true],
        ],
        'uses_body' => false,
    ],
    'pdf.letters.pemberitahuan' => [
        'name' => 'Surat Pemberitahuan',
        'description' => 'Pemberitahuan dengan isi bebas, paragraf, penomoran, dan subpoin sesuai kebutuhan.',
        'fields' => [
            ['name' => 'lampiran', 'label' => 'Keterangan Lampiran (opsional)', 'type' => 'text', 'required' => false],
        ],
        'uses_body' => true,
        'default_body' => '<p>Salam silaturahim kami sampaikan semoga kita selalu dalam lindungan Allah SWT. Shalawat dan salam semoga tercurah kepada Nabi Muhammad SAW, keluarga, sahabat, dan pengikutnya hingga akhir zaman. Aamiin.</p><p>Sehubungan dengan [konteks pemberitahuan], kami sampaikan hal-hal sebagai berikut:</p><ol><li><p>[Informasi pertama]</p><ol><li><p>[Rincian informasi]</p></li></ol></li><li><p>[Informasi berikutnya]</p></li><li><p>[Imbauan atau tindak lanjut]</p></li></ol><p>Demikian surat pemberitahuan ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>',
    ],
    'pdf.letters.undangan' => [
        'name' => 'Surat Undangan',
        'description' => 'Surat kepada penerima dengan pembuka, isi undangan, dan penutup baku.',
        'fields' => [],
        'uses_body' => true,
    ],
    'pdf.letters.tugas' => [
        'name' => 'Surat Tugas',
        'description' => 'Penugasan satu atau beberapa GTK dengan identitas kepala, uraian tugas, tanggal, waktu, dan tempat.',
        'fields' => [
            ['name' => 'penerima_tugas', 'label' => 'Penerima Tugas', 'type' => 'gtk_assignments', 'required' => true],
            ['name' => 'tugas', 'label' => 'Uraian Tugas', 'type' => 'textarea', 'required' => true],
            ['name' => 'tanggal_tugas', 'label' => 'Tanggal Pelaksanaan', 'type' => 'date', 'required' => true],
            ['name' => 'waktu', 'label' => 'Waktu/Jam', 'type' => 'text', 'required' => true],
            ['name' => 'tempat', 'label' => 'Tempat Pelaksanaan', 'type' => 'textarea', 'required' => true],
        ],
        'uses_body' => false,
    ],
    'pdf.letters.dispensasi' => [
        'name' => 'Surat Dispensasi',
        'description' => 'Permohonan dispensasi kepada instansi asal untuk mengikuti kegiatan madrasah, sesuai contoh surat.',
        'fields' => [
            ['name' => 'nama', 'label' => 'Nama yang Diberi Dispensasi', 'type' => 'text', 'required' => true],
            ['name' => 'hari_tanggal', 'label' => 'Hari/Tanggal Kegiatan', 'type' => 'date', 'required' => true],
            ['name' => 'waktu', 'label' => 'Waktu Kegiatan', 'type' => 'text', 'required' => true],
            ['name' => 'tempat', 'label' => 'Tempat Kegiatan', 'type' => 'text', 'required' => true],
            ['name' => 'acara', 'label' => 'Kegiatan', 'type' => 'textarea', 'required' => true],
        ],
        'uses_body' => false,
    ],
];
