# Migration SIMAD/MADIKA untuk hosting

## Perubahan yang disiapkan

- Tiga migration kerangka lama dipertahankan sebagai no-op. Tabel GTK, semester, dan mata pelajaran dibuat oleh migration lengkap berikutnya, sehingga tidak terjadi pembuatan tabel dua kali.
- Foreign key surat keluar dipasang setelah tabel jenis surat tersedia. Penambahan kolom dan indeks nomor surat memeriksa struktur yang sudah ada.
- Migration `2026_11_03_000003_complete_application_runtime_schema` melengkapi `jenis_surat`, `use_template`, `final_docx_file`, dan `final_pdf_file`, serta menyelaraskan struktur jam pelajaran dan indeks unik penugasan guru.
- Kolom yang sudah ada dipertahankan. Referensi arsip PDF/DOCX lama digunakan untuk mengisi referensi baru yang masih kosong; berkas arsip tidak dibuat ulang.

## Tentukan cara pemindahan data

**Jika data lokal ingin dibawa:** ekspor database lokal secara lengkap, termasuk tabel `migrations`, kemudian impor ke database hosting yang kosong. Unggah juga berkas pada storage yang digunakan aplikasi. Migration hanya memindahkan struktur, bukan seluruh data dan berkas lokal.

**Jika mulai dari kosong:** gunakan database hosting kosong, lalu jalankan migration. Data sekolah, akun, dan data master harus disiapkan tersendiri. Untuk jenis surat bawaan, jalankan `php artisan db:seed --class=LetterTypeSeeder --force` setelah migration. Jangan menjalankan seluruh `DatabaseSeeder` sebagai inisialisasi produksi: seeder tersebut berisi data contoh.

## Langkah admin hosting

1. Cadangkan database dan berkas storage sebelum memperbarui instalasi yang sudah berisi data.
2. Unggah proyek terbaru beserta semua file `database/migrations`; jangan mengganti nama migration lama.
3. Atur `.env` hosting, khususnya `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, dan koneksi database. Pertahankan `APP_KEY` jika membawa data instalasi lama; buat key baru hanya untuk instalasi baru yang benar-benar kosong.
4. Pasang dependency produksi dengan `composer install --no-dev --optimize-autoloader`. Pastikan hasil build frontend juga sudah tersedia.
5. Setelah konfigurasi database benar, jalankan dari direktori proyek:

   ```sh
   php artisan optimize:clear
   php artisan migrate:status
   php artisan migrate --force
   php artisan migrate:status
   php artisan optimize
   ```

6. Pastikan tidak ada migration berstatus Pending. Uji login, data master, pembuatan surat, unggah lampiran, hasil DOCX/PDF, dan akses arsip lama sebelum digunakan sehari-hari.

Jangan menjalankan `migrate:fresh`, `migrate:refresh`, atau `migrate:reset` pada database berisi data. Jangan mengimpor tabel lama tanpa riwayat `migrations` lalu menjalankan seluruh migration di atas tabel tersebut.

Jika migration melaporkan **penugasan ganda**, ada lebih dari satu guru untuk kombinasi mata pelajaran, kelas, tahun ajaran, dan semester yang sama. Tentukan penugasan yang benar melalui pemeriksaan data, lalu ulangi `php artisan migrate --force`. Migration tidak memilih atau menghapus salah satu data secara otomatis.

Migration pelengkap dan beberapa migration KBM lama tidak menyediakan penghapusan struktur otomatis saat rollback. Jika perlu mengembalikan versi database, gunakan cadangan yang dibuat sebelum pembaruan.

## Verifikasi sebelum penyerahan

Pengujian dilakukan pada PHP 8.5, MySQL 9.3, dan SQLite sementara, terpisah dari database aktif:

- 53 migration dari database kosong menghasilkan 41 tabel.
- Menjalankan `migrate` kembali tidak menambahkan migration atau membuat tabel/kolom ganda.
- Pembaruan skema lama dan skema sebagian berhasil; nomor surat, isi, token verifikasi, dan referensi arsip yang sudah terisi dipertahankan.
- Data penugasan yang berkonflik terdeteksi dan dipertahankan.
- Struktur hasil instalasi kosong dibandingkan dengan database lokal untuk tipe kolom, null/default, indeks unik, serta foreign key.
- 82 test migration, surat, dan KBM terkait lolos (611 assertions). Sebanyak 21 test pembacaan dan alur PDF juga lolos setelah runner QA menggunakan binary Poppler lokal.

Pengujian seluruh aplikasi mencakup 497 test dan belum seluruhnya lolos. Selain masalah konfigurasi PDF pada percobaan awal yang sudah diverifikasi ulang, masih ada satu test dashboard (`.dashboard` saat role akun uji kosong) dan tiga test akses data pendidikan (penanganan `ModelNotFoundException`) yang gagal. Bagian tersebut tidak diubah dalam pekerjaan migration ini.

File migration dipasang ke proyek lokal, tetapi migration baru belum dijalankan pada database aktif. Pengujian dilakukan di database sementara; struktur, riwayat migration, dan jumlah baris database aktif telah diperiksa tetap sama.

Versi database hosting belum diketahui. Admin tetap perlu menjalankan pemeriksaan akhir pada server tujuan setelah pencadangan.
