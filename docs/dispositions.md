# Alur disposisi surat masuk

1. Admin membuka **Surat Masuk → Tambah Surat**, mengisi informasi surat, dan mengunggah dokumen jika tersedia. Surat yang disimpan langsung tersedia untuk Kepala Madrasah.
2. Kepala Madrasah membuka **Persuratan → Surat Masuk & Disposisi**. Daftar awal menampilkan surat yang menunggu disposisi. Tersedia pencarian dan filter semua surat, dalam tindak lanjut, atau selesai.
3. Pilih **Periksa & Disposisi**, buka dokumen surat, lalu **Buat Disposisi**. Pilih satu guru/staf dengan data GTK aktif, isi instruksi, dan isi batas waktu bila diperlukan. Untuk penerima tambahan, buat disposisi lain pada surat yang sama.
4. Penerima membuka **Disposisi Saya**. Membuka detail menandai disposisi sebagai dibaca. Gunakan **Mulai Diproses**, isi catatan hasil, lalu **Tandai Selesai**.
5. Admin memantau melalui **Surat Masuk → Pantau Disposisi**. Kepala Madrasah dapat membuka riwayat dan hasil pada surat yang sama. Surat ditampilkan selesai setelah seluruh disposisinya selesai.

Hanya Kepala Madrasah yang dapat membuat disposisi. Hanya penerima yang ditunjuk yang dapat mengubah status tindak lanjut. Disposisi yang selesai tidak dapat dimulai ulang. Admin tidak memiliki tindakan untuk mengirim disposisi.

Alur memakai tabel surat masuk dan disposisi yang sudah ada; tidak memerlukan migrasi tambahan.

Pengujian: php artisan test tests/Unit/DispositionWorkflowTest.php

Pengujian menggunakan SQLite dalam memori dan migrasi tabel yang terkait. Migrasi lain yang menduplikasi pembuatan tabel pada riwayat lama tidak dijalankan oleh pengujian ini.
