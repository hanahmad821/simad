# Surat manual dengan QR

1. Buat surat di Word dengan tepat satu teks [[QR_CODE]] pada baris tersendiri, ukuran 10–12 pt.
2. Posisi kiri atas penanda menjadi posisi kiri atas QR (dengan padding 1 mm). Sisakan area putih 30 × 30 mm ke kanan dan bawah; jangan taruh teks, garis, gambar, atau nama penandatangan di area ini. Pemeriksaan otomatis hanya mendeteksi benturan dengan teks.
3. Ekspor ke PDF dengan teks yang dapat diseleksi. PDF scan dan PDF terkunci tidak didukung.
4. Unggah PDF melalui detail surat manual (maksimal 10 MB), lalu ajukan persetujuan.
5. Saat Kepala Madrasah menyetujui, PDF final memuat QR verifikasi pada halaman dan posisi penanda. PDF asli tetap disimpan. Penanda secara visual ditutup warna putih, bukan dihapus dari lapisan teks PDF.

Dokumen manual lama berupa DOCX atau PDF tanpa penanda harus diunggah ulang sebagai PDF dengan penanda sebelum diajukan/disetujui. Surat yang sudah terbit tidak diubah.

## Kebutuhan server

Pasang **Poppler** dengan program pdftotext yang mendukung opsi -bbox-layout dan -cropbox. Program pdftotext bawaan Git for Windows menggunakan Xpdf dan tidak mendukung opsi ini.

- Ubuntu/Debian: apt-get install poppler-utils
- Windows: gunakan paket https://github.com/oschwartz10612/poppler-windows/releases dan ekstrak seluruh paket beserta DLL-nya.
- Atur PDFTOTEXT_BINARY di .env ke lokasi executable, misalnya "C:/tools/poppler/Library/bin/pdftotext.exe". Pada Linux dengan Poppler di PATH, gunakan pdftotext.
- Jalankan php artisan config:clear setelah mengubah konfigurasi (atau bangun ulang config cache sesuai proses deploy).

Aplikasi PHP memerlukan ekstensi DOM dan GD serta izin menjalankan proses eksternal. PDF tetap diimpor menggunakan FPDI; PDF yang tidak didukung importernya perlu diekspor ulang sebagai PDF biasa.

Jalankan pengujian: php artisan test tests/Unit/PdfQrAnchorTest.php
