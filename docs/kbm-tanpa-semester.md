# KBM berdasarkan tahun pelajaran

Penugasan mengajar, jadwal, anggota kelas, presensi siswa, dan jurnal mengajar memakai tahun pelajaran sebagai periode. Pengisian dan pemilihan semester tidak diperlukan.

- Jadwal guru dan dashboard memakai tahun pelajaran aktif serta status aktif penugasan dan jadwal.
- Jumlah JP penugasan dihitung dari jadwal mengajar aktif per minggu. Penugasan tidak memerlukan input beban mengajar dan tidak membatasi jumlah JP berdasarkan nilai manual lama.
- Presensi siswa mengambil anggota kelas aktif pada tahun pelajaran penugasan.
- Kewajiban jurnal mengajar mengikuti rentang tanggal tahun pelajaran. Pergantian semester tidak menghentikan jadwal atau kewajiban jurnal.
- Pemeriksaan bentrok guru, kelas, dan ruangan berlaku untuk seluruh tahun pelajaran, termasuk penugasan lama yang memiliki referensi semester berbeda.
- Penugasan dan anggota kelas baru dapat disimpan tanpa `semester_id`. Kolom dan relasi semester lama dipertahankan sebagai metadata historis.

## Migrasi data lama

Migrasi `2026_11_02_000002_make_kbm_independent_of_semesters.php` mengubah `semester_id` menjadi nullable pada `teaching_assignments` dan `student_class_histories`.

Jika terdapat beberapa versi aktif pada tahun pelajaran yang sama, migrasi mempertahankan satu versi aktif:

- Penugasan dengan guru, mata pelajaran, kelas, dan tahun pelajaran yang sama.
- Keanggotaan siswa pada tahun pelajaran yang sama.

Versi dari semester yang sebelumnya aktif diprioritaskan, kemudian semester dengan tanggal mulai terbaru dan ID baris terbaru. Versi lain dinonaktifkan. Baris, referensi semester lama, jadwal, presensi siswa, dan snapshot jurnal tidak dihapus atau dipindahkan.

Setelah meninjau data dan membuat cadangan, jalankan migrasi khusus ini:

```powershell
php artisan migrate --path=database/migrations/2026_11_02_000002_make_kbm_independent_of_semesters.php
```

Migrasi tidak memiliki rollback otomatis karena data baru tidak lagi mempunyai semester dan status aktif antarsemester sudah disatukan. Gunakan cadangan sebelum migrasi jika harus kembali ke pembagian semester lama.
