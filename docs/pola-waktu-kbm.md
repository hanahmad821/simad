# Pola waktu KBM per hari

Aturan pelajaran adalah **1 JP = 35 menit**. Kegiatan pagi dan istirahat menggunakan rentang waktu sendiri dan tidak dihitung sebagai JP. Nomor JP dimulai dari 1 setiap hari; urutan kegiatan disimpan terpisah agar istirahat tidak menggeser nomor JP.

## Pola awal

| Hari | Kegiatan pagi | Istirahat 1 | Istirahat 2 | Jumlah JP | Selesai |
| --- | --- | --- | --- | --- | --- |
| Senin–Rabu | 07:00–07:30 | 09:50–10:25 | 12:10–12:45 | 9 | 13:55 |
| Kamis | 07:00–07:50 | 10:10–10:30 | 12:15–12:50 | 9 | 14:00 |
| Jumat | 07:00–08:00 | 09:45–10:05 | — | 5 | 11:15 |
| Sabtu | 07:00–07:30 | 09:50–10:25 | 12:10–12:45 | 9 | 13:55 |

Sabtu diawali dengan pola Senin–Rabu karena belum ditentukan terpisah. Jumlah JP dan seluruh rentang kegiatan dapat diubah admin.

## Pengaturan

Buka **Master Data → Waktu KBM** atau tombol **Pengaturan Waktu KBM** di halaman Jadwal. Pilih hari, atur kegiatan pagi, jumlah JP, dan istirahat. Pilih beberapa hari pada **Terapkan ke hari** untuk menyalin pola yang sama, misalnya Senin, Selasa, dan Rabu.

Sistem menyusun pelajaran otomatis dalam kelipatan 35 menit. Istirahat yang bertumpang tindih, memotong satu JP, atau berada di luar jumlah JP akan ditolak. Satu sesi boleh melewati istirahat: pelajaran dijeda lalu dilanjutkan pada JP berikutnya. Istirahat tidak dihitung sebagai JP, sedangkan presensi dan jurnal tetap satu kali untuk seluruh sesi. Contohnya Jumat JP 3–4 berlangsung 09:10–09:45 dan 10:05–10:40 (70 menit mengajar, jeda 20 menit). Tabel jadwal menampilkan bagian pelajaran sebelum dan sesudah baris istirahat sebagai satu sesi. Pengurangan JP ditolak apabila masih ada jadwal yang memakai JP yang akan dihapus.

Perubahan pola berlaku langsung pada jadwal yang ada, sesuai penggunaan aplikasi saat ini untuk pengujian. Tampilan jadwal admin, guru, dashboard, presensi siswa, dan kewajiban jurnal menggunakan slot harian yang sama. Tidak ada pembatasan Jumat yang ditulis tetap di logika penjadwalan; jumlah JP Jumat mengikuti pengaturan.

## Penerapan data uji lama

Migrasi `2026_11_02_000004_add_daily_kbm_time_patterns.php` menambahkan tabel pola dan penanda hari serta urutan pada slot pelajaran. Jam global lama tetap disimpan sebagai data nonaktif setelah konversi.

```powershell
php artisan migrate --path=database/migrations/2026_11_02_000004_add_daily_kbm_time_patterns.php
php artisan migrate --path=database/migrations/2026_11_02_000005_normalize_lesson_period_sort_order.php
php artisan kbm:apply-time-patterns --reset-test-data
```

Opsi `--reset-test-data` menghapus presensi siswa, jurnal mengajar, dan pengecualian KBM untuk pengujian. Jadwal lama dipetakan berdasarkan nomor JP sebenarnya, dengan ID jadwal dipertahankan jika masih sesuai. JP yang tidak tersedia pada hari tersebut atau bertabrakan dengan baris yang sudah dikonversi dihapus. Data GTK, siswa, kelas, ruangan, penugasan, presensi GTK, dan LCKH tidak dibersihkan.

Tanpa opsi tersebut, perintah tidak menghapus data pengujian dan berhenti jika menemukan jadwal yang tidak bisa dipetakan. `KbmTimeSeeder` dan `LessonPeriodSeeder` dapat diulang; keduanya mempertahankan pola yang sudah diatur admin.
