# Presensi GTK dan approval LCKH

Presensi masuk dan pulang tidak memerlukan approval kepala madrasah, serta tidak dikunci oleh kelengkapan LCKH atau jurnal. Approval melalui checklist hanya berlaku untuk LCKH yang selesai. Perubahan presensi tidak mengubah approval LCKH; perubahan LCKH sendiri mengembalikannya ke status menunggu approval.

## Acuan waktu

Jam wajib GTK mengikuti pola KBM setiap hari: mulai kegiatan pagi sampai akhir JP terakhir. Jam acuan disimpan pada catatan presensi saat melakukan presensi supaya perubahan pola KBM berikutnya tidak mengubah perhitungan catatan tersebut. Catatan lama tanpa acuan menggunakan pola KBM yang tersedia.

Keterlambatan dan pulang sebelum waktunya dihitung terpisah dan dapat muncul bersamaan:

| Selisih waktu | Terlambat | Pulang sebelum waktunya |
| --- | --- | --- |
| Tepat waktu / tidak melanggar | - | - |
| Lebih dari 0 sampai 30 menit | T1 | PSW1 |
| Lebih dari 30 sampai 60 menit | T2 | PSW2 |
| Lebih dari 60 menit | T3 | PSW3 |

Detik diperhitungkan: selisih 30 menit 1 detik masuk tingkat 2. Selisih menit pada tampilan dibulatkan ke atas. Lupa presensi pulang pada hari yang sudah lewat selalu PSW3; durasinya tidak direkayasa karena jam pulang tidak tercatat.

## Riwayat dan lupa pulang

Riwayat menampilkan seluruh tanggal dalam bulan tanpa pagination, berikut jam wajib, kategori terlambat, kategori pulang awal, hari libur, dan catatan. GTK yang sudah presensi masuk tetapi lupa pulang dapat menekan **Lupa Presensi Pulang** untuk tanggal yang sudah lewat lalu mengisi alasannya. Pelaporan hanya menambah alasan dan waktu pelaporan, tanpa membuat jam pulang pengganti atau mengubah jam masuk. Tidak ada approval untuk laporan tersebut.

Hari kerja yang sudah lewat tanpa presensi dihitung **alpa** secara otomatis. Tidak dibuat baris presensi palsu di database, sehingga pengajuan absensi yang sah masih dapat diproses kemudian. Hari Minggu, hari libur aktif, hari yang sedang berjalan, dan tanggal mendatang tidak otomatis dianggap alpa. Pengajuan izin/cuti/sakit yang disetujui mencatat status sesuai pengajuannya dan menggantikan alpa otomatis. Alur pengajuan dan persetujuan izin/cuti/sakit tetap berlaku.

Riwayat tanpa presensi masuk tidak menyediakan pengisian jam manual. Jika tidak hadir karena izin/cuti/sakit, gunakan Pengajuan Absensi. Catatan ketidakhadiran yang sudah disetujui tetap terlindungi dari perubahan GPS maupun laporan lupa pulang.

Monitoring admin dan PDF presensi menggunakan perhitungan yang sama. PDF presensi tidak memiliki kolom approval. Riwayat audit tetap disimpan di belakang layar dan tidak ditampilkan pada halaman approval LCKH. Pengecualian checkout lama tidak berlaku.
