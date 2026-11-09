# Manajemen ruangan dan ruang kelas bawaan

Admin mengelola ruangan melalui **Master Data → Ruangan**. Data meliputi kode unik, nama, gedung, kapasitas, dan status aktif. Daftar dapat dicari berdasarkan kode, nama, atau gedung serta difilter menurut status.

Tentukan ruangan masing-masing kelas melalui **Kelas → Kelola → Ubah Kelas / Wali Kelas → Ruang kelas bawaan**. Pemetaan ini digunakan saat membuat jadwal dari tabel jadwal, dari halaman kelas, atau setelah memilih penugasan mengajar.

Ruang bawaan dipilih otomatis apabila masih aktif. Admin dapat memilih ruangan lain untuk sesi tertentu, misalnya laboratorium. Jika ruang kelas belum ditentukan atau sudah nonaktif, admin perlu memilih ruangan aktif. Pemeriksaan bentrok ruangan tetap berlaku.

Perubahan ruang bawaan kelas berlaku untuk jadwal yang dibuat selanjutnya. Jadwal yang sudah ada mempertahankan ruangnya saat dibuka untuk diedit.

Ruangan hanya dapat dihapus jika belum dipakai kelas maupun jadwal, termasuk jadwal nonaktif. Gunakan status nonaktif untuk ruangan yang sudah pernah digunakan agar riwayat tetap tersimpan.

Referensi ruang bawaan ditambahkan melalui migrasi `2026_11_02_000003_add_room_to_school_classes.php`. Migrasi hanya menambahkan kolom opsional `room_id` dan foreign key ke `rooms`; tidak menetapkan atau mengubah ruangan pada kelas dan jadwal lama.

## Seeder ruang kelas

Jalankan `php artisan db:seed --class=ClassroomSeeder` untuk membuat satu ruangan per kelas yang ada dan menetapkannya sebagai ruang bawaan kelas. Nama ruangan sama persis dengan nama kelas; kode baru menggunakan `KLS-{id kelas}`. Kapasitas dan status ruangan baru mengikuti kelas.

Seeder menggunakan kembali ruangan dengan kode tersebut atau nama kelas yang sama apabila belum dipakai kelas lain. Pengulangan tidak menggandakan ruangan; perubahan nama kelas juga memperbarui nama ruangan yang dibuat dengan kode kelas. Kapasitas dan status ruangan yang sudah ada tetap dipertahankan. Jadwal yang sudah tersimpan tidak diubah.

`RoomSeeder` juga memanggil seeder ini setelah menyiapkan laboratorium IPA dan komputer. Ruangan lama tidak dihapus.
