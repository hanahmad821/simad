# Jenis Surat

Admin membuka **Persuratan → Jenis Surat** untuk menambah atau mengubah nama,
kode klasifikasi, status, dan pilihan template surat. Jenis aktif tersedia saat
membuat surat keluar. Jenis tanpa template digunakan untuk surat manual
(booking nomor dan unggah PDF). Jenis yang sudah digunakan tidak dapat dihapus;
nonaktifkan jika tidak lagi dipakai.

Pilihan template berasal dari daftar `config/letter_templates.php`. Setiap
template menentukan view PDF, kolom isian, dan apakah membutuhkan isi surat.
Kolom ditetapkan oleh aplikasi agar selalu sesuai dengan data yang dipakai view.
Nama jenis surat tidak mengubah judul atau susunan template PDF.

Untuk menambah format baru:

1. Buat view di `resources/views/pdf/letters/`, mengikuti layout surat yang ada.
2. Daftarkan nama view, nama tampilan, penjelasan, `fields`, dan `uses_body` di
   `config/letter_templates.php`.
3. Gunakan `$letter` untuk metadata, `$school` untuk profil madrasah, `$data`
   untuk isian khusus, dan `$qrPath` untuk QR setelah persetujuan. Jika QR kosong,
   sisakan ruang tanda tangan basah. Escape seluruh data isian saat dirender.
4. Uji pratinjau PDF serta penerbitan QR dan tanda tangan basah.
5. Pilih template tersebut di menu Jenis Surat. Bila konfigurasi dicache,
   perbarui cache konfigurasi saat deployment.

Admin hanya dapat memilih view yang terdaftar dan tersedia. Path view tidak
diinput bebas. Mengubah pilihan template menyimpan definisi sebelumnya untuk
surat yang sudah dibuat. Snapshot menjaga pilihan view dan kolomnya; mengedit
kode view yang sama tidak membekukan kode lama. Gunakan nama view baru untuk
perubahan struktur yang harus mempertahankan versi lama. PDF final yang sudah
diterbitkan tetap menjadi arsipnya.

Menu, endpoint, aset, dan dependensi editor bebas sudah dihapus. Renderer
snapshot editor lama dipertahankan untuk kompatibilitas surat yang telanjur
dibuat; tidak tersedia untuk pembuatan jenis atau surat baru.
