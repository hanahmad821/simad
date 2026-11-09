# Spesifikasi Alur dan Aturan Pengelolaan Siswa

Status: spesifikasi untuk implementasi berikutnya, belum diterapkan ke aplikasi.
Dokumen ini menggantikan rancangan tiga kartu pada menu Pengelolaan Siswa.
Acuan utama: satu pekerjaan memiliki satu tempat pengelolaan; halaman lain menyediakan pintasan dengan konteks terisi.

## 1. Pembagian menu

| Menu | Pemilik pekerjaan | Di luar tanggung jawab |
|---|---|---|
| Siswa | Biodata, pencarian, kartu siswa, status aktif/alumni, pindah sekolah/keluar | Keputusan kenaikan kelas massal |
| Kelas & Rombel | Rombel per tahun, wali kelas, ruang, kapasitas, penempatan anggota, pindah rombel | Editor jadwal, pengampu, kelulusan |
| Proses Akhir Tahun | Naik kelas, tinggal kelas, kelulusan, keputusan ditunda, kemajuan per kelas | Pindah sekolah/keluar di tengah tahun |
| Jadwal Pelajaran | Pengampu, hari, jam, ruang pembelajaran | Mengubah keanggotaan siswa |
| Tahun Pelajaran | Membuat periode dan mengaktifkan tahun | Menentukan hasil siswa |

Tahun Pelajaran ditempatkan di Pengaturan. Proses Akhir Tahun menyediakan pintasan ke halaman itu.
Halaman kelas menyediakan "Lihat Jadwal" dan "Proses Akhir Tahun" dengan tahun/kelas sudah terisi.
Biodata siswa menampilkan kelas sebagai informasi; perubahan penempatan dilakukan lewat Kelas & Rombel.
Pindah kelas = antarrombel di sekolah pada tahun yang sama. Pindah sekolah = status keluar dari sekolah.

## 2. Hak akses

- Admin: mengelola siswa, rombel, proses akhir tahun, periode, serta koreksi.
- Staff: hak persuratan dan presensi yang sudah tersedia tetap berlaku; tidak otomatis mendapat pengelolaan status siswa.
- Guru: akses pembelajaran dan presensi sesuai penugasan.
- Kepala Madrasah: akses pengawasan yang sudah tersedia; tidak ditambahkan tahap persetujuan baru untuk proses ini.
- Seluruh pemeriksaan izin berlaku di server, termasuk aksi langsung dan proses massal.

## 3. Status dan istilah

Pisahkan tiga hal berikut:

1. **Status siswa**: Aktif, Lulus, Pindah Sekolah, Keluar. Status Tidak Aktif lama tetap dikenali sebagai data lama yang belum memiliki alasan keluar; jangan diasumsikan lulus.
2. **Keanggotaan rombel**: penempatan siswa pada rombel dan tahun tertentu, dengan tanggal mulai/selesai.
3. **Keputusan akhir tahun**: Naik, Tinggal, Lulus, atau Ditunda.

Naik dan Tinggal tidak mengubah status siswa menjadi nonaktif.
Ditunda merupakan keputusan yang belum final, bukan status keluar.
Belum diproses berbeda dari Ditunda: Ditunda menyimpan alasan serta operator agar bisa ditindaklanjuti.
Draft berarti belum disimpan sebagai hasil; selesai berarti keputusan final sudah tersimpan.
Siswa yang pindah/keluar sebelum penyelesaian tahun tidak dihitung sebagai calon kenaikan aktif, tetapi tetap tercatat pada riwayat kelas.

## 4. Siswa → Ubah Status

### Alur
Cari siswa → Ubah Status → Pindah Sekolah / Keluar → Isi detail → Tinjau → Simpan.

### Isian
- Identitas dan kelas saat ini hanya ditampilkan sebagai referensi.
- Pilihan status baru.
- Tanggal efektif wajib.
- Alasan wajib.
- Sekolah tujuan wajib untuk Pindah Sekolah; tidak muncul untuk Keluar.
- Tidak menampilkan kelas tujuan internal atau tahun pelajaran berikutnya.

### Aturan
- Hanya siswa aktif yang bisa diproses melalui alur biasa.
- Tanggal tidak boleh melebihi hari ini atau mendahului awal keanggotaan yang sedang ditutup.
- Jika ada presensi setelah tanggal keluar yang diminta, blokir dan tampilkan tanggal yang bermasalah; jangan menghapusnya.
- Tutup keanggotaan pada tanggal efektif dan nonaktifkan siswa untuk presensi berjalan.
- Jika siswa sudah memiliki penempatan tahun berikutnya, tampilkan penempatan tersebut dalam ringkasan. Boleh membatalkannya dalam transaksi yang sama hanya jika belum ada aktivitas/keputusan lanjutan; jika ada, wajib memakai alur koreksi.
- Jangan menghapus identitas, kode QR, presensi, atau riwayat tahun sebelumnya.
- Status Lulus hanya ditetapkan melalui Proses Akhir Tahun kelas XII.
- Kembali menjadi Aktif dilakukan melalui Koreksi Status dengan alasan, bukan checkbox aktif.
- Perubahan biodata alumni tetap dapat dilakukan secara terpisah; perubahan biodata tidak boleh mengaktifkan siswa atau membuat riwayat kelas baru.

## 5. Kelas & Rombel

### Halaman daftar
Tahun pelajaran aktif menjadi pilihan awal. Tampilkan kelas, tingkat, wali kelas, ruang, anggota/kapasitas, dan status rombel.
Aksi: Tambah Rombel, Buka, Ubah Pengaturan, Arsipkan.
Tampilkan indikator wali kelas belum ditentukan atau kapasitas penuh.

### Detail rombel
Ringkasan identitas dan daftar anggota menjadi isi utama.
Aksi: Tambah Anggota, Pindahkan Kelas, Ubah Pengaturan.
Jadwal dan proses akhir tahun diakses lewat pintasan, bukan form duplikat.

### Tambah anggota
Pilih siswa aktif yang belum memiliki penempatan pada tahun tersebut → Tinjau → Simpan.
Boleh memilih beberapa siswa sekaligus.
Untuk tahun tujuan setelah siswa memiliki kelas tahun sebelumnya, arahkan siswa lama melalui Proses Akhir Tahun agar keputusan tidak dilewati. Penempatan awal siswa baru tetap melalui Tambah Anggota.

### Pindah rombel
Pilih anggota → Pilih kelas tujuan → Tanggal perpindahan dan alasan → Tinjau → Simpan.

Aturan:
- Asal dan tujuan berada pada tahun yang sama dan tingkat yang sama.
- Tujuan aktif, berbeda dari asal, dan kapasitas mencukupi.
- Tidak mengubah school_class_id pada catatan keanggotaan lama.
- Tutup interval asal, lalu buat interval baru; interval keanggotaan tidak boleh tumpang tindih.
- Tanggal tujuan adalah hari pertama mengikuti rombel baru; tanggal akhir asal sehari sebelumnya.
- Jika sudah ada aktivitas yang bertentangan pada/ setelah tanggal perpindahan, arahkan ke koreksi; jangan memindahkan presensi otomatis.
- Presensi memilih anggota berdasarkan tanggal sesi, bukan hanya kelas saat ini.
- Jika keputusan akhir tahun siswa sudah final, pindah rombel asal harus melalui koreksi keputusan terkait.

### Identitas rombel tahunan
- Nama unik dalam satu tahun pelajaran; X-A dapat dipakai kembali pada tahun berikutnya.
- Tingkat, wali kelas, ruang, kapasitas, dan status mengikuti rombel tahunan.
- Mengubah wali kelas tahun baru tidak mengubah data tahun lama.
- Tingkat rombel yang sudah memiliki anggota/aktivitas tidak boleh diubah langsung.
- Kapasitas baru tidak boleh lebih kecil daripada jumlah penempatan yang berlaku.
- Penempatan tahun berikutnya yang sudah dikonfirmasi ikut menghabiskan kapasitas.
- Rombel dengan anggota aktif tidak bisa diarsipkan sebelum penempatan diselesaikan.
- Rombel yang memiliki riwayat tidak dihapus permanen.

## 6. Proses Akhir Tahun

### Halaman pembuka
Pilih tahun asal. Sistem menampilkan kelas dengan:
- jumlah siswa yang perlu keputusan;
- selesai;
- ditunda;
- belum diproses;
- tombol Lanjutkan.

Kelas X/XI membuka Kenaikan Kelas. Kelas XII membuka Kelulusan.
Siswa yang sudah diproses tampil pada kelompok Selesai; tidak menghilang tanpa penjelasan.

### Langkah 1 — Konteks
- Tahun asal, kelas asal.
- Tahun tujuan hanya diminta jika terdapat kenaikan atau tinggal kelas.
- Tahun tujuan harus periode berikutnya dalam urutan tahun pelajaran dan tidak tumpang tindih dengan tahun asal.
- Kelas tujuan hanya berasal dari tahun tujuan.
- Tahun baru yang belum tersedia memiliki tautan Buat Tahun Pelajaran.

### Langkah 2 — Hasil siswa
- Tidak ada keputusan otomatis yang langsung disimpan.
- Tombol Terapkan ke Semua mengisi pilihan nyata pada baris siswa belum final.
- Checkbox digunakan untuk menerapkan perubahan ke siswa pilihan; hasil akhir tetap terlihat pada setiap baris.
- Kelas X/XI: Naik, Tinggal, Ditunda.
- Kelas XII: Lulus, Tinggal Kelas XII, Ditunda.
- Naik: tujuan satu tingkat di atas asal.
- Tinggal: tujuan tingkat yang sama pada tahun berikutnya.
- Lulus: hanya kelas XII, tanpa kelas tujuan.
- Ditunda: alasan wajib, tidak membuat keanggotaan tujuan.
- Dropdown tujuan difilter sesuai hasil per siswa.
- Menukar hasil membersihkan tujuan yang tidak lagi valid.
- Tidak menggunakan "Ikuti proses utama" atau tujuan tersembunyi.
- Ada pencarian nama dan filter hasil.
- Ringkasan jumlah diperbarui langsung.

### Langkah 3 — Tinjau
- Kelompokkan per hasil dan kelas tujuan.
- Tampilkan nama siswa, kelas/tahun asal, kelas/tahun tujuan, dan tanggal kelulusan jika relevan.
- Bedakan hasil final, ditunda, dan siswa yang belum tersentuh.
- Tombol kembali mempertahankan seluruh pilihan.
- Tombol simpan menyebut jumlah hasil final dan jumlah ditunda.
- Validasi gagal menunjuk siswa serta alasan, tanpa menghapus pilihan lain.

### Langkah 4 — Selesai
- Tampilkan jumlah yang berhasil disimpan dan jumlah yang masih memerlukan keputusan.
- Sediakan Proses Kelas Berikutnya, Lihat yang Ditunda, dan Riwayat Batch.
- Kelas dinyatakan selesai hanya jika tidak ada keputusan Ditunda/Belum Diproses.
- Naik/Tinggal menambah penempatan di tahun berikutnya; keanggotaan tahun asal tetap dapat ditelusuri.
- Lulus menyimpan tanggal serta tahun kelulusan dan mengarsipkan siswa.
- Siswa ditunda tetap dapat diproses pada sesi berikutnya, termasuk setelah tahun baru aktif.

## 7. Tanggal dan aktivasi tahun

- Tahun pelajaran memiliki tanggal mulai/akhir yang valid; pembuatan periode baru tidak mengaktifkannya.
- Kenaikan/tinggal boleh disiapkan sebelum tahun asal selesai. Penempatan tujuan mulai berlaku pada awal tahun tujuan.
- Tanggal kelulusan tidak di masa depan dan tidak mendahului awal tahun asal.
- Jika terdapat presensi setelah tanggal kelulusan, blokir sampai tanggal/aktivitas tersebut diperiksa.
- Jangan menutup keanggotaan aktif tahun asal hanya karena penempatan tahun berikutnya sudah dibuat.
- Aktivasi tahun dilakukan secara terpisah setelah ringkasan kesiapan.
- Tepat satu tahun aktif untuk operasional saat proses aktivasi selesai.
- Tahun tujuan harus memiliki rombel aktif.
- Tampilkan jumlah siswa belum diproses, ditunda, belum ditempatkan, serta kelas tanpa jadwal.
- Jika masih ada kekurangan, aktivasi membutuhkan konfirmasi eksplisit dan alasan yang dicatat. Tidak melakukan keputusan siswa secara otomatis.
- Siswa yang belum ditempatkan pada tahun aktif tampil sebagai Belum Ditempatkan; tidak dimasukkan ke kelas atau presensi secara otomatis.
- Pilihan tahun operasional tidak menghapus atau menyembunyikan akses arsip tahun lain.

## 8. Konsistensi dan perlindungan data

- Satu siswa hanya memiliki satu penempatan yang berlaku pada tanggal tertentu dalam satu tahun.
- Riwayat perpindahan berurutan di dalam tahun yang sama boleh lebih dari satu.
- Satu siswa memiliki maksimal satu keputusan akhir tahun final yang berlaku untuk satu tahun asal.
- Identitas keputusan adalah siswa + tahun asal, bukan hanya ID riwayat kelas, agar pindah rombel tidak membuka jalan proses ganda.
- Satu permintaan penyimpanan memiliki kunci idempotensi untuk menangani klik/kirim ulang.
- Saat menyimpan, cek ulang izin, versi data, keanggotaan, keputusan sebelumnya, tahun, tingkat, dan kapasitas.
- Kunci baris kelas/siswa yang terkait dalam urutan konsisten ketika menulis data.
- Satu batch berhasil seluruhnya atau gagal seluruhnya.
- Data yang berubah setelah ringkasan menyebabkan pesan konflik dan permintaan peninjauan ulang.
- Penyimpanan biodata tidak membuat keanggotaan baru.
- Presensi lama tetap terkait sesi, siswa, kelas, dan tahun asalnya.

## 9. Riwayat dan koreksi

Setiap batch menyimpan operator, waktu, jenis, tahun/kelas asal, jumlah siswa, dan detail hasil.
Setiap detail menyimpan kondisi sebelum/sesudah, tujuan, tanggal, serta alasan bila diwajibkan.
Riwayat memiliki pencarian dan pagination.

Koreksi:
1. Pilih hasil siswa pada riwayat.
2. Isi alasan.
3. Sistem memeriksa aktivitas dan keputusan sesudah hasil tersebut.
4. Tampilkan dampak koreksi.
5. Konfirmasi dan simpan catatan koreksi baru.

Aturan:
- Catatan keputusan awal tidak dihapus atau ditimpa.
- Jika penempatan tujuan belum memiliki aktivitas atau keputusan lanjutan, pembatalan dapat menonaktifkan penempatan itu dan membuka kembali keputusan tahun asal.
- Jika sudah ada aktivitas, pembatalan otomatis diblokir dan dependensinya ditampilkan. Gunakan koreksi penempatan efektif yang menjaga presensi lama.
- Koreksi status alumni/pindah/keluar harus memeriksa penempatan terbaru sebelum pemulihan ke Aktif.
- Jangan mengaktifkan kembali siswa tanpa menentukan tahun serta penempatan yang berlaku, atau status Belum Ditempatkan yang eksplisit.
- Koreksi per siswa tidak membatalkan siswa lain dalam batch.
- Tidak tersedia tombol pembatalan massal tanpa pemeriksaan per siswa.

## 10. Rencana perubahan kode dan data

- StudentProgressionService dipersempit menjadi keputusan akhir tahun. Pindah/keluar dipindah ke layanan perubahan status individu.
- Keanggotaan rombel dan pemindahannya ditangani oleh satu layanan agar form siswa, kelas, dan akhir tahun tidak menulis riwayat dengan aturan berbeda.
- Jadwal tetap menjadi tempat mengelola pengampu dan sesi; halaman kelas hanya memberi tautan dengan filter.
- Tambahkan identitas rombel tahunan, interval keanggotaan, batch proses, keputusan (draft/ditunda/final/dikoreksi), serta kejadian perubahan status.
- Hubungkan pengampu, jadwal, penempatan, dan laporan dengan rombel tahunan yang sesuai.
- Ubah pembatas unik source_history_id yang sekarang ada menjadi aturan keputusan final siswa/tahun dengan dukungan revisi dan audit.
- Daftar calon presensi harus memperhitungkan interval penempatan dan status pada tanggal yang diminta.
- Perhitungan kapasitas harus memperhitungkan penempatan tujuan yang sudah dikonfirmasi.

Migrasi:
- Inventarisasi duplikasi keanggotaan, lintas tahun, dan referensi jadwal sebelum perubahan skema.
- Susun rombel tahunan dari riwayat dan pengampu yang memang tersedia.
- Jangan mengarang wali kelas/ruang historis yang tidak tersimpan; tandai sebagai tidak diketahui.
- Petakan keputusan lama menjadi hasil final dengan audit asal yang tetap.
- Keputusan lama tidak boleh diproses ulang setelah migrasi.
- Jika data lama ambigu, hasilkan daftar masalah untuk diperbaiki; jangan memilih kelas secara diam-diam.
- Migrasi dan pengujian dilakukan sebelum mengaktifkan alur baru.

## 11. Tahapan implementasi

1. Fondasi rombel tahunan, aturan keanggotaan, dan migrasi data.
2. Sederhanakan Kelas & Rombel; pindahkan editor pengampu/jadwal ke Jadwal Pelajaran.
3. Siswa → Ubah Status individual beserta alasan, tanggal, sekolah tujuan, dan audit.
4. Wizard Proses Akhir Tahun, keputusan Ditunda, dan kemajuan per kelas.
5. Riwayat batch, koreksi aman, serta pemeriksaan kesiapan aktivasi tahun.
6. Verifikasi migrasi, hak akses, presensi historis, dan alur operator sebelum rilis.

## 12. Skenario penerimaan

- R01: 30 siswa naik melalui satu pilihan tujuan; 2 siswa dapat diganti menjadi Tinggal tanpa mengubah 28 lainnya.
- R02: kelas XII dapat menyimpan 28 Lulus, 1 Tinggal XII, dan 1 Ditunda; satu yang ditunda masih terlihat belum selesai.
- R03: siswa pindah sekolah diproses dari Siswa dengan tanggal, alasan, dan sekolah tujuan tanpa form kelas tujuan.
- R04: pindah rombel tidak mengubah kelas pada presensi sebelum tanggal pindah.
- R05: mengubah wali kelas tahun baru tidak mengubah informasi tahun lama.
- R06: penempatan tidak boleh melampaui kapasitas, termasuk dua operator yang menyimpan bersamaan.
- R07: klik simpan dua kali tidak menggandakan hasil, keanggotaan, atau audit.
- R08: kembali dari ringkasan dan gagal validasi mempertahankan pilihan.
- R09: proses yang berubah setelah ringkasan ditampilkan ditolak dengan penjelasan.
- R10: keputusan tidak dapat diulang melalui riwayat kelas lain pada tahun yang sama.
- R11: koreksi tanpa aktivitas tujuan dapat diproses; koreksi dengan ketergantungan menampilkan alasan penolakan.
- R12: arsip kelas, presensi, identitas, dan kode QR siswa tetap tersedia setelah naik/lulus/pindah.
- R13: pengeditan biodata tidak mengubah kelas atau menggandakan riwayat.
- R14: perubahan status bertanggal mundur yang bertentangan dengan presensi ditolak.
- R15: migrasi mempertahankan keputusan lama dan menandai data ambigu.
- R16: Staff/Guru/Kepala Madrasah tidak dapat melakukan mutasi melalui endpoint Admin.
- R17: aktivasi tahun dengan siswa belum selesai menampilkan dampak dan mencatat alasan konfirmasi.
