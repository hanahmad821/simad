# Kenaikan Kelas dan Kelulusan

Menu **Master Data → Kenaikan & Kelulusan** tersedia untuk Admin.

1. Buka **Kelola tahun pelajaran** untuk menambahkan tahun berikutnya bila belum ada. Tahun aktif tidak berubah saat tahun baru dibuat.
2. Pilih tahun dan kelas asal, lalu tekan **Tampilkan Siswa**.
3. Tentukan proses utama (naik kelas, tinggal kelas, lulus, pindah, atau keluar). Untuk naik/tinggal kelas, pilih tahun dan kelas tujuan.
4. Centang siswa yang diproses. Gunakan pilihan per siswa untuk pengecualian, misalnya siswa yang tinggal kelas atau masuk rombel berbeda. Siswa yang tidak dicentang tidak berubah.
5. Tekan **Tinjau Ringkasan**, periksa jumlah dan tujuan, lalu **Simpan Proses**.
6. Setelah pembagian kelas dan jadwal baru siap, aktifkan tahun baru melalui **Kelola tahun pelajaran**.

Naik kelas hanya ke tingkat berikutnya, tinggal kelas ke tingkat yang sama, dan lulus hanya dari kelas XII. Kelas tujuan harus aktif, kapasitas cukup, dan tahun tujuan setelah tahun asal. Tanggal lulus/pindah/keluar tidak boleh di masa depan. Proses yang sudah tersimpan tidak dapat dikirim ulang.

Proses disimpan sekaligus: jika satu siswa tidak valid, tidak ada perubahan pada seluruh pilihan tersebut. Sistem memeriksa ulang data saat menyimpan untuk menangani perubahan setelah ringkasan dibuat.

Kenaikan kelas menambah riwayat tahunan baru; keanggotaan tahun sebelumnya tetap tersimpan. Kelulusan/pindah/keluar menonaktifkan siswa dari presensi berjalan dan menyimpan status, tanggal, serta tahun pelajaran keluar. Identitas, kode QR, dan presensi lama tetap tersimpan.

Di **Siswa**, gunakan filter **Lulus / Alumni**, **Pindah**, atau **Keluar** untuk melihat arsip. Data arsip tidak dapat diaktifkan atau dihapus melalui form siswa biasa. Riwayat proses terbaru mencatat siswa, kelas/tahun asal, tujuan, tanggal efektif, dan operator. Untuk koreksi proses yang sudah disimpan diperlukan penanganan khusus; belum ada tombol pembatalan.

Migrasi tambahan: 2026_11_04_000001_add_student_progression.php. Tidak mengubah status siswa lama.
