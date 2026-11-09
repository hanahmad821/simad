<?php
require __DIR__.'/../../vendor/autoload.php';
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

Settings::setOutputEscapingEnabled(true);
$doc = new PhpWord();
$doc->setDefaultFontName('Calibri');
$doc->setDefaultFontSize(11);
$doc->setDefaultParagraphStyle(['spaceAfter'=>100,'lineHeight'=>1.12]);
$doc->addTitleStyle(0,['name'=>'Calibri','size'=>28,'bold'=>true,'color'=>'000000'],['spaceAfter'=>240]);
$doc->addTitleStyle(1,['name'=>'Calibri','size'=>19,'bold'=>true,'color'=>'000000'],['spaceAfter'=>160,'keepNext'=>true]);
$doc->addTitleStyle(2,['name'=>'Calibri','size'=>12,'bold'=>true,'color'=>'000000'],['spaceBefore'=>120,'spaceAfter'=>70,'keepNext'=>true]);
$doc->getDocInfo()->setTitle('SOP dan Panduan Penggunaan MADIKA untuk Guru')->setSubject('Panduan operasional berdasarkan role guru')->setCreator('MADIKA')->setDescription('Panduan penggunaan dengan screenshot akun dan data contoh');
$section=$doc->addSection(['paperSize'=>'A4','marginTop'=>1000,'marginBottom'=>950,'marginLeft'=>1000,'marginRight'=>1000,'footerHeight'=>400]);
$footer=$section->addFooter();
$footer->addPreserveText('MADIKA  |  Panduan Guru                                      Halaman {PAGE} dari {NUMPAGES}',['size'=>9,'color'=>'595959'],['spaceAfter'=>0]);
$pages=[];
function page($title,$purpose,$steps=[],$images=[],$note='') {global $pages; $pages[]=compact('title','purpose','steps','images','note');}

page('Identitas dan penggunaan dokumen',
 'Panduan ini membantu guru menggunakan MADIKA untuk mencatat kehadiran, menjalankan administrasi pembelajaran, menindaklanjuti disposisi, serta memperbarui profil dan pendidikan pribadi.',[
 'Pengguna sasaran: akun dengan role guru yang sudah terhubung ke data GTK aktif.',
 'Kode dokumen: MADIKA SOP GR 001. Versi: 1.0. Tanggal penyusunan: 29 Oktober 2026. Status: untuk peninjauan.',
 'Pelaksana utama adalah guru. Admin menyiapkan akun, hubungan akun dengan GTK, tahun ajaran, semester, kelas, siswa, dan jadwal. Kepala madrasah memeriksa presensi dan LCKH serta memberikan catatan perbaikan.',
 'Nama Guru Contoh MADIKA, kelas X Panduan, siswa berakhiran Contoh, dan surat DEMO adalah data latihan. Gunakan data sebenarnya pada kegiatan operasional.',
 'Alamat simad.test pada screenshot adalah alamat pengembangan di komputer. Dalam penggunaan sehari-hari, buka alamat HTTPS resmi yang diberikan admin.',
 'Baca alur kerja harian lebih dahulu. Ikuti urutan langkah di setiap modul dan cocokkan hasilnya dengan indikator berhasil. Label tombol mengikuti tampilan aplikasi, termasuk label berbahasa Inggris.'
 ],[], 'Nama penyusun, pemeriksa, pejabat pengesah, nomor revisi resmi, dan tanggal berlaku dapat ditetapkan madrasah setelah peninjauan. Dokumen ini tidak menetapkan jam kerja atau kebijakan kepegawaian baru.');

page('Persiapan dan batas akses guru',
 'Sebelum digunakan, akun dan data akademik harus tersedia agar menu guru menampilkan data yang tepat.',[
 'Terima email akun dan password awal dari admin. Tidak ada pendaftaran akun mandiri atau menu Sign up.',
 'Pastikan akun sudah terhubung dengan GTK milik sendiri. Jika muncul pesan akun belum terhubung dengan GTK, minta admin memperbaiki hubungan akun tersebut.',
 'Gunakan browser yang mendukung kamera dan lokasi. Pada HP, buka alamat HTTPS yang valid, aktifkan lokasi perangkat, dan berikan izin kamera atau lokasi ketika fitur terkait digunakan.',
 'Sebelum presensi siswa, pastikan kelas, siswa aktif, penugasan mengajar, dan jadwal pada semester aktif sudah diatur admin.',
 'Guru mengelola presensi dan jurnal sesuai sesi yang tersedia untuknya, LCKH pribadi, disposisi yang diterima, profil GTK sendiri, serta pendidikan sendiri.',
 'Guru tidak membuat atau menghapus akun, mengganti role, mengubah status aktif akun atau GTK, mengatur master jadwal, atau memberikan approval untuk pekerjaan sendiri.',
 'Perubahan kolom Jabatan pada profil memperbarui data GTK; perubahan itu tidak mengubah role atau hak akses aplikasi.'
 ],[], 'Login menggunakan email dan password. Passkey dan 2FA sudah dinonaktifkan. Menu hapus akun mandiri tidak tersedia.');

page('SOP kerja harian guru',
 'Urutan berikut menjadi acuan kerja harian. Jalankan setiap pencatatan sesuai kejadian yang sebenarnya.',[
 'Saat datang: login, periksa nama akun, buka Presensi Saya, lalu lakukan Check In di dalam area presensi madrasah. Pastikan jam masuk tercatat.',
 'Sebelum mengajar: buka Jadwal Mengajar dan periksa hari, kelas, mata pelajaran, jam pelajaran, serta ruang.',
 'Pada setiap sesi pembelajaran: buka Presensi Siswa, pilih tanggal dan Sesi KBM yang benar, lalu catat status semua siswa melalui QR atau input manual. Simpan perubahan manual.',
 'Setelah sesi: pastikan presensi seluruh siswa sudah tersimpan, lalu isi Uraian materi, Catatan, dan Refleksi singkat pada Jurnal Mengajar. Tekan Selesaikan jurnal.',
 'Setiap hari kerja: isi kegiatan dan hasil pekerjaan pada LCKH. Tekan Selesaikan LCKH. Simpan draf hanya untuk pekerjaan yang belum selesai.',
 'Secara berkala: buka Disposisi Saya, baca instruksi dan tenggat, mulai proses, lakukan tindak lanjut, kemudian catat hasil dan tandai selesai.',
 'Sebelum pulang: pastikan syarat checkout lengkap, lakukan Check Out dari area madrasah, lalu periksa jam pulang pada Riwayat Presensi.',
 'Setelah pencatatan: periksa riwayat dan catatan kepala madrasah. Perbaiki data yang diminta, simpan kembali, dan keluar dari aplikasi pada perangkat bersama.'
 ],[], 'Presensi siswa lengkap membuka jurnal sesi. LCKH lengkap dan seluruh jurnal sesi selesai membuka persyaratan checkout. Status Selesai pada LCKH berbeda dengan status Disetujui oleh kepala madrasah.');

page('Masuk ke aplikasi',
 'Gunakan akun guru yang diberikan admin. Screenshot menunjukkan halaman login tanpa pendaftaran mandiri.',[
 'Buka alamat MADIKA yang diberikan admin. Jika berada di beranda, tekan Masuk atau Masuk ke MADIKA.',
 'Isi Email address dan Password. Gunakan ikon mata hanya bila perlu memeriksa ketikan pada perangkat pribadi.',
 'Remember me bersifat opsional. Hindari mengaktifkannya pada komputer atau HP yang dipakai bersama.',
 'Tekan Log in dan tunggu halaman pengguna terbuka. Periksa bahwa nama akun milik sendiri dan menu pembelajaran guru tersedia.',
 'Jika kredensial ditolak, periksa email, huruf besar kecil password, dan tombol Caps Lock. Gunakan Forgot your password bila layanan email reset sudah dikonfigurasi; jika tidak, hubungi admin.'
 ],[['01-login','Form login MADIKA', [530,220,460,470],330]],'Indikator berhasil: pengguna masuk ke aplikasi dengan identitas guru yang benar. Jangan membagikan password atau memakai akun guru lain.');

page('Membaca dashboard guru',
 'Dashboard merangkum aktivitas dan menyediakan pintasan ke modul yang paling sering digunakan.',[
 'Pilih Dashboard pada sidebar. Periksa nama pengguna dan tanggal yang tampil.',
 'Baca ringkasan jadwal hari ini, disposisi aktif, belum dibaca, lewat tenggat, dan selesai.',
 'Gunakan Akses Cepat untuk membuka Presensi Siswa, Jadwal Mengajar, Presensi Saya, Riwayat Presensi, Disposisi Saya, atau Riwayat Presensi Siswa.',
 'Periksa kartu Presensi Saya Hari Ini untuk memastikan jam masuk dan pulang. Periksa pula daftar disposisi yang perlu ditindaklanjuti.',
 'Bila ringkasan atau jadwal tidak sesuai, periksa dahulu tanggal dan role akun, kemudian konfirmasikan penugasan kepada admin.'
 ],[['02-dashboard','Dashboard dan pintasan untuk role guru',[0,87,1518,727],490]],'Beberapa JP yang berurutan dapat tampil sebagai satu sesi pada modul jadwal atau jurnal. Jangan menyamakan jumlah JP dengan jumlah jurnal yang harus diisi.');

page('Melakukan presensi masuk guru',
 'Presensi Saya mencatat waktu datang dan pulang menggunakan lokasi GPS perangkat.',[
 'Datang ke area presensi madrasah. Pastikan koneksi internet, lokasi perangkat, dan izin lokasi browser aktif.',
 'Pilih Presensi Saya pada sidebar. Pada kartu Check In, tekan Check In.',
 'Jika browser meminta izin lokasi, pilih izin yang sesuai agar lokasi dapat diperoleh. Tunggu proses selesai.',
 'Periksa pemberitahuan keberhasilan dan jam Check In pada detail presensi hari ini. Jika masih belum tercatat, jangan menganggap presensi sudah berhasil.',
 'Jika berada di luar radius atau GPS gagal, perbaiki kondisi lokasi dan coba lagi. Jika koordinat madrasah belum dikonfigurasi, hubungi admin.'
 ],[['03-presensi-guru','Tombol Check In dan syarat checkout yang belum lengkap',[280,115,1210,490],490]],'Indikator berhasil: jam masuk tercatat untuk tanggal hari ini. Pesan Belum lengkap pada syarat checkout tidak menghalangi Check In; pesan itu menunjukkan kewajiban yang perlu diselesaikan sebelum pulang.');

page('Melakukan presensi pulang guru',
 'Check Out dilakukan setelah Check In dan setelah kewajiban jurnal hari itu lengkap.',[
 'Pastikan presensi seluruh siswa pada setiap sesi sudah disimpan dan jurnal mengajar setiap sesi berstatus Selesai.',
 'Pastikan LCKH hari ini sudah diselesaikan. Buka kembali Presensi Saya.',
 'Periksa panel Persyaratan checkout. Jika belum lengkap, gunakan Isi Jurnal Mengajar atau Isi LCKH untuk melengkapi data yang disebutkan.',
 'Saat pulang, tetap berada di dalam area presensi, tekan Check Out, dan tunggu pengambilan lokasi serta penyimpanan selesai.',
 'Periksa jam pulang di detail hari ini atau Riwayat Presensi. Jika lupa Check In, sistem tidak dapat langsung melakukan Check Out; laporkan kepada admin atau kepala madrasah sesuai prosedur madrasah.'
 ],[['30-checkout-siap','Contoh syarat lengkap dan tombol Check Out aktif',[280,115,1210,660],490]],'Jam masuk 07.00 pada gambar adalah data dummy. Screenshot memperlihatkan kesiapan tombol, bukan bukti pengujian lokasi fisik. Presensi operasional harus dilakukan dari lokasi sebenarnya.');

page('Memeriksa riwayat presensi pribadi',
 'Riwayat Presensi digunakan untuk mengecek kembali catatan kehadiran sendiri menurut bulan.',[
 'Pilih Riwayat Presensi di bawah kelompok Presensi.',
 'Pilih bulan yang akan diperiksa. Tunggu daftar diperbarui.',
 'Periksa Tanggal, Masuk, Pulang, Status, dan Catatan. Tanda kosong pada Pulang menunjukkan jam pulang belum tercatat pada data tersebut.',
 'Cocokkan catatan dengan aktivitas sebenarnya. Jika data salah atau terlupa, laporkan tanggal dan kronologi kepada admin atau kepala madrasah; guru tidak mengoreksi jam presensi melalui halaman riwayat.'
 ],[['04-riwayat-presensi','Riwayat presensi bulanan milik akun guru',[280,115,1210,250],490]],'Indikator berhasil: bulan sesuai dan catatan hari yang dicari dapat ditemukan. Contoh ini sengaja menampilkan data masuk tanpa data pulang.');

page('Melihat jadwal mengajar',
 'Jadwal Mengajar menampilkan jadwal mingguan guru pada tahun ajaran dan semester aktif.',[
 'Pilih Jadwal Mengajar pada kelompok Pembelajaran.',
 'Baca ringkasan sesi hari ini, sesi per minggu, JP per minggu, dan kelas yang diajar.',
 'Gunakan Tampilkan hari, Hari ini, atau Semua hari untuk menyaring kartu jadwal.',
 'Periksa mata pelajaran, kelas, rentang waktu, jumlah JP, ruang, tahun ajaran, dan semester.',
 'Gunakan Buka Presensi Siswa untuk pencatatan kehadiran. Pada sesi hari ini, Jurnal Sesi Pembelajaran membuka jurnal sesi tersebut.',
 'Jika jadwal salah atau kosong, hubungi admin. Guru tidak membuat jadwal baru dari halaman ini.'
 ],[['05-jadwal','Jadwal hari ini dengan dua JP dalam satu sesi',[280,115,1210,670],490]],'Dalam contoh, JP 1 dan 2 Bahasa Indonesia kelas X Panduan dikelompokkan menjadi satu sesi pukul 07.30 sampai 08.40 dan memerlukan satu jurnal sesi.');

page('Memilih sesi presensi siswa',
 'Tanggal dan sesi menentukan kelas serta daftar siswa yang akan dicatat kehadirannya.',[
 'Pilih Presensi Siswa pada kelompok Pembelajaran, atau gunakan pintasan dari jurnal.',
 'Pilih Tanggal kegiatan yang benar, kemudian pilih Sesi KBM. Jika tanggal diubah, pilih kembali sesi karena pilihan sebelumnya direset.',
 'Periksa ringkasan Kelas, Mata Pelajaran, Guru, Jam, dan Ruangan sebelum mencatat siswa.',
 'Cocokkan daftar siswa dengan kelas yang sedang diajar. Jika daftar salah atau kosong, minta admin memeriksa anggota kelas dan semester aktif.',
 'Untuk beberapa JP berurutan yang menjadi satu sesi, lakukan presensi satu kali pada sesi tersebut.'
 ],[['07-presensi-siswa','Tanggal dan sesi menentukan daftar siswa',[280,115,1210,570],490]],'Indikator siap: kelas dan sesi sesuai, serta seluruh nama siswa yang seharusnya aktif tersedia. Guru hanya menggunakan sesi yang tersedia untuk akunnya.');

page('Mencatat presensi siswa secara manual',
 'Input manual digunakan bila kartu tidak dibawa, atau untuk mencatat kondisi selain hadir.',[
 'Pada baris setiap siswa, pilih status yang sesuai: Hadir, Terlambat, Izin, Sakit, atau Alpa.',
 'Isi Catatan bila diperlukan, misalnya keterangan izin atau alasan keterlambatan. Kolom catatan maksimal 1.000 karakter.',
 'Periksa semua siswa. Jangan menyisakan status Belum dipresensi. Siswa yang belum dicatat tidak otomatis dianggap Alpa.',
 'Tekan Simpan Presensi dan tunggu proses selesai serta pemberitahuan keberhasilan.',
 'Buka Riwayat Presensi Siswa untuk memeriksa hasil. Untuk mengoreksi pencatatan, buka kembali tanggal dan sesi yang sama, ubah status atau catatan, lalu simpan kembali.',
 'Setelah lengkap dan tersimpan, tekan Lanjut ke Jurnal Mengajar.'
 ],[['09-presensi-tersimpan','Contoh dua siswa hadir dan satu siswa sakit',[300,335,1165,450],490]],'Indikator berhasil: tidak ada siswa yang Belum dipresensi, penyimpanan berhasil, dan data muncul pada riwayat. Perubahan manual harus disimpan walaupun sebelumnya sudah pernah melakukan scan QR.');

page('Mencatat presensi siswa melalui QR',
 'Pemindaian kartu siswa mempercepat pencatatan Hadir pada sesi yang sudah dipilih.',[
 'Buka tanggal dan Sesi KBM yang benar, lalu tekan Scan QR code.',
 'Jika diminta, izinkan akses kamera pada browser. Gunakan alamat HTTPS yang valid, terutama pada HP.',
 'Arahkan QR code kartu siswa ke kamera hingga terbaca. Pastikan nama yang ditampilkan sesuai siswa dan muncul pesan berhasil disimpan.',
 'Scan yang berhasil langsung menyimpan status Hadir dan waktu pindai. Scan ulang pada kartu yang sudah dipindai menampilkan informasi bahwa siswa sudah dipindai dan tidak mengubah status.',
 'Tekan Tutup Scanner setelah selesai. Lengkapi status siswa yang belum dipindai secara manual, termasuk Sakit, Izin, Terlambat, atau Alpa, lalu Simpan Presensi.',
 'Jika QR tidak terdaftar untuk kelas aktif, periksa kartu, tanggal, sesi, dan keanggotaan kelas. Gunakan pencatatan manual sesuai kondisi sambil mengonfirmasi data kepada admin.'
 ],[['08-scanner-qr','Panel kamera aktif dan tombol Tutup Scanner',[325,90,1120,450],490]],'Gambar menunjukkan panel kamera aktif tanpa kartu. Keberhasilan membaca kartu fisik tidak disimulasikan pada gambar ini. Scan menandai Hadir; keterlambatan perlu dicatat sesuai kondisi siswa.');

page('Menelusuri riwayat presensi siswa',
 'Riwayat menampilkan catatan yang sudah tersimpan pada sesi yang dapat diakses guru.',[
 'Pilih Riwayat Presensi Siswa pada sidebar.',
 'Atur Tanggal awal dan Tanggal akhir. Bila perlu, pilih Kelas, Mata pelajaran, Status, atau ketik Nama, NIS, atau NISN pada Cari siswa.',
 'Baca ringkasan Sesi, Hadir, Terlambat, Izin, Sakit, dan Alpa. Angka serta daftar mengikuti filter yang dipilih.',
 'Gulir ke kartu sesi lalu tekan Detail sesi untuk melihat daftar nama, status, catatan, informasi pindai kartu, dan pencatat. Geser tabel ke samping bila kolom tidak seluruhnya terlihat.',
 'Tekan Tutup untuk kembali. Gunakan Reset filter bila pencarian tidak menampilkan catatan yang diharapkan.'
 ],[['16-riwayat-siswa','Filter dan ringkasan riwayat presensi siswa',[280,115,1210,560],490],['17-detail-presensi-siswa','Detail sesi menampilkan tiga catatan siswa',[465,163,580,575],290]],'Detail sesi menampilkan seluruh catatan tersimpan pada sesi itu, termasuk status di luar filter daftar. Catatan siswa yang belum disimpan tidak muncul sebagai hasil pencatatan.');

page('Memahami jurnal mengajar yang terkunci',
 'Jurnal mengajar hanya dapat diisi setelah presensi seluruh siswa dalam sesi dan tanggal yang sama lengkap serta tersimpan.',[
 'Buka Jurnal Mengajar dan pilih tanggal, lalu tekan Tampilkan jika tanggal diubah.',
 'Periksa daftar sesi yang wajib diisi dan angka Presensi siswa pada sesi tersebut.',
 'Jika muncul Jurnal terkunci atau angka tercatat belum sama dengan total siswa, tekan Isi Presensi Siswa.',
 'Lengkapi dan simpan semua status siswa, kemudian kembali ke jurnal sesi yang sama.',
 'Jika total siswa nol, hubungi admin untuk melengkapi anggota kelas sebelum melakukan presensi dan mengisi jurnal.'
 ],[['06-jurnal-terkunci','Pesan pengunci ketika baru nol dari tiga siswa tercatat',[280,115,1210,620],490]],'Indikator siap: jumlah siswa tercatat sama dengan total siswa dan kolom Uraian materi, Catatan, serta Refleksi singkat dapat diisi.');

page('Mengisi dan menyelesaikan jurnal mengajar',
 'Setiap sesi pembelajaran yang menjadi kewajiban guru harus memiliki jurnal lengkap.',[
 'Periksa tanggal, mata pelajaran, kelas, dan JP pada judul sesi.',
 'Isi Uraian materi dengan materi dan kegiatan pembelajaran yang benar-benar dilaksanakan.',
 'Isi Catatan dengan kondisi penting selama pembelajaran. Jika tidak ada hal khusus, tulis Tidak ada catatan.',
 'Isi Refleksi singkat dengan hasil pembelajaran dan rencana perbaikan atau tindak lanjut.',
 'Gunakan Simpan draf bila belum selesai. Untuk memenuhi kewajiban jurnal, pastikan ketiga kolom terisi lalu tekan Selesaikan jurnal.',
 'Ulangi untuk setiap sesi hari itu. Periksa ringkasan hingga semua sesi selesai.'
 ],[['10-isi-jurnal','Contoh materi catatan dan refleksi yang sudah terisi',[300,180,1170,605],490]],'Indikator berhasil: muncul Jurnal berhasil disimpan, badge sesi menjadi Selesai, dan ringkasan jurnal hari itu sudah lengkap. Draf belum memenuhi syarat checkout.');

page('Memeriksa hasil dan riwayat jurnal',
 'Gunakan riwayat untuk memeriksa jurnal sesi yang sudah tercatat pada bulan tertentu.',[
 'Setelah menyelesaikan jurnal, periksa ringkasan jumlah sesi yang sudah selesai.',
 'Pilih Riwayat Jurnal Mengajar. Pilih Bulan dan Status jurnal, lalu tekan Tampilkan.',
 'Baca ringkasan sesi tercatat, jurnal selesai, serta total JP. Beberapa JP berurutan dalam satu sesi dihitung sebagai satu jurnal.',
 'Tekan Lihat isi jurnal untuk membuka materi, catatan, dan refleksi. Tekan Buka sesi untuk meninjau atau memperbarui isinya.',
 'Jika memperbarui jurnal pada hari yang sudah checkout, pertahankan seluruh isian lengkap dan status Selesai.'
 ],[['11-jurnal-selesai','Pesan berhasil dan ringkasan satu dari satu sesi selesai',[280,280,1210,300],490],['12-riwayat-jurnal','Filter dan daftar riwayat jurnal',[280,115,1210,385],490]],'Indikator berhasil: tanggal, kelas, mata pelajaran, JP, dan isi jurnal sesuai dengan kegiatan sebenarnya.');

page('Mengisi LCKH harian',
 'LCKH mencatat kegiatan dan hasil pekerjaan harian guru, termasuk kegiatan di luar sesi mengajar.',[
 'Pilih LCKH pada sidebar. Pastikan Tanggal sesuai. Untuk tanggal lain yang sudah lewat, pilih tanggal lalu tekan Tampilkan.',
 'Isi Uraian kegiatan harian dan hasilnya. Tuliskan kegiatan serta hasil yang diperoleh, bukan hanya daftar rencana.',
 'Contoh: mengajar dan hasil latihan siswa, memeriksa tugas, menyusun bahan ajar, serta koordinasi kegiatan madrasah.',
 'Tekan Simpan draf bila catatan masih akan dilengkapi. Jika pekerjaan hari itu telah dicatat dengan lengkap, tekan Selesaikan LCKH.',
 'Pastikan pesan LCKH berhasil disimpan dan badge Selesai tampil. LCKH wajib lengkap sebelum checkout.'
 ],[['13-isi-lckh','Contoh uraian kegiatan dan pilihan penyimpanan LCKH',[280,115,1210,510],490]],'Status Selesai menunjukkan isian LCKH lengkap. Status tersebut belum berarti kepala madrasah sudah memberikan approval.');

page('Membaca approval dan memperbaiki LCKH',
 'Riwayat LCKH memperlihatkan kegiatan yang sudah disimpan dan tindak lanjut pemeriksaan kepala madrasah.',[
 'Pilih Riwayat LCKH. Atur Bulan dan Filter status, lalu tekan Tampilkan.',
 'Periksa kolom Status LCKH, Approval harian, dan Catatan kepala sekolah.',
 'Menunggu approval berarti data belum disetujui. Perlu perbaikan berarti catatan pemeriksa harus ditindaklanjuti. Disetujui berarti approval harian sudah diberikan.',
 'Jika perlu koreksi, tekan Lihat / Ubah atau Isi LCKH pada tanggal terkait. Baca catatan pemeriksa, perbarui uraian, lalu selesaikan LCKH kembali.',
 'Perubahan data dapat mengembalikan approval ke status menunggu. Periksa lagi riwayat setelah menyimpan dan setelah kepala madrasah melakukan pemeriksaan.',
 'Hari kerja tanpa LCKH juga ditampilkan agar dapat dilengkapi. Angka ringkasan berlaku untuk seluruh bulan yang dipilih.'
 ],[['15-riwayat-lckh','LCKH selesai yang masih menunggu approval harian',[280,115,1210,535],490]],'Persetujuan dan permintaan revisi dilakukan kepala madrasah. Guru hanya melihat hasil pemeriksaan dan memperbaiki pekerjaan sendiri.');

page('Membaca disposisi yang diterima',
 'Disposisi Saya berisi instruksi surat masuk yang ditugaskan kepada akun guru tersebut.',[
 'Pilih Disposisi Saya. Gunakan filter Status untuk menampilkan Menunggu, Dibaca, Diproses, atau Selesai.',
 'Periksa nomor surat, perihal, pemberi tugas, instruksi, dan Deadline.',
 'Tekan Lihat pada baris disposisi. Saat pertama kali dibuka, status Menunggu berubah menjadi Dibaca.',
 'Baca Informasi Surat dan Instruksi Disposisi sampai selesai. Bila tersedia lampiran, tekan Lihat Surat untuk membacanya.',
 'Jika instruksi belum jelas atau tidak dapat diselesaikan sesuai tenggat, koordinasikan dengan pemberi tugas sebelum menandainya selesai.'
 ],[['18-daftar-disposisi','Daftar disposisi yang ditujukan kepada guru',[280,115,1210,350],490],['19-detail-disposisi','Detail dan tombol Mulai Diproses',[280,115,1210,470],490]],'Urutan status: Menunggu, Dibaca, Diproses, lalu Selesai. Guru tidak membuat atau meneruskan disposisi dari halaman ini.');

page('Menindaklanjuti dan menyelesaikan disposisi',
 'Ubah status sesuai perkembangan pekerjaan dan catat hasil tindak lanjut yang sudah dilakukan.',[
 'Pada disposisi berstatus Dibaca, tekan Mulai Diproses. Pastikan status berubah menjadi Diproses.',
 'Laksanakan instruksi pemberi tugas sesuai tenggat yang tercantum.',
 'Gulir ke Catatan Penyelesaian, lalu isi Catatan hasil tindak lanjut dengan hasil kegiatan secara ringkas dan jelas.',
 'Setelah pekerjaan benar-benar selesai, tekan Tandai Selesai. Tunggu pemberitahuan berhasil dan periksa status Selesai.',
 'Setelah selesai, catatan pada halaman ini menjadi tidak dapat diedit. Jika ada koreksi penting, koordinasikan dengan pemberi tugas.'
 ],[['20-catatan-disposisi','Contoh hasil tindak lanjut sebelum Tandai Selesai',[280,250,1210,535],490],['21-disposisi-selesai','Pemberitahuan berhasil dan status Selesai',[280,115,1210,280],490]],'Indikator berhasil: muncul Disposisi berhasil diselesaikan dan status menjadi Selesai. Mengubah status harus mengikuti pekerjaan sebenarnya.');

page('Memperbarui profil GTK sendiri',
 'Data pribadi guru dapat diperbarui langsung melalui Settings tanpa meminta admin mengedit setiap kolom.',[
 'Tekan nama akun pada bagian bawah sidebar, lalu pilih Settings. Pada HP, buka menu akun melalui ikon profil.',
 'Pilih Profile. Periksa Nama lengkap dan Email. Email ini juga digunakan untuk login.',
 'Perbarui identitas dan data pribadi sesuai dokumen sebenarnya: NIP, NUPTK, jenis kelamin, tempat dan tanggal lahir.',
 'Perbarui status kepegawaian, nomor pegawai, jabatan, telepon, serta alamat bila diperlukan.',
 'Gulir ke tombol Save lalu tekan. Tunggu pemberitahuan profil berhasil diperbarui.',
 'Jika mengubah email, gunakan email baru untuk login berikutnya. Nama dan email disinkronkan ke akun dan data GTK.'
 ],[['23-profil-gtk','Identitas dan data GTK di halaman Profile',[285,115,800,680],430]],'NIP dan NUPTK tidak boleh sama dengan data GTK lain. Nama maksimal 150 karakter dan email maksimal 150 karakter untuk akun terhubung GTK. Role dan status aktif tetap dikelola admin.');

page('Menyimpan kontak dan memeriksa profil',
 'Tombol Save menyimpan perubahan profil utama. Penyimpanan pendidikan dilakukan terpisah pada formulir pendidikan.',[
 'Periksa kembali nomor pegawai, jabatan, nomor telepon, dan alamat sebelum menekan Save.',
 'Jika muncul pesan validasi, perbaiki kolom yang disebutkan, lalu tekan Save kembali.',
 'Sesudah berhasil, buka ulang Profile untuk memastikan nilai yang baru tetap tampil.',
 'Jangan mengosongkan data wajib seperti nama, email, jenis kelamin, atau status kepegawaian.',
 'Jika profil tidak menampilkan kolom GTK atau riwayat pendidikan, minta admin memeriksa hubungan akun dengan GTK.'
 ],[['24-kontak-pendidikan','Bagian kontak tombol Save dan daftar pendidikan',[530,135,550,650],330]],'Menu Delete account tidak tersedia. Pembuatan, pengelolaan akses, dan penghapusan akun ditangani admin. Tombol Hapus pada bagian pendidikan hanya menghapus satu catatan pendidikan, bukan akun.');

page('Mengelola riwayat pendidikan sendiri',
 'Riwayat pendidikan pada Profile hanya menampilkan pendidikan milik GTK yang terhubung ke akun guru.',[
 'Buka Settings lalu Profile dan gulir ke Riwayat pendidikan.',
 'Untuk menambah, tekan Tambah pendidikan. Untuk mengubah, tekan Edit pada catatan yang sesuai.',
 'Pilih Jenjang Pendidikan dari dropdown: SD / MI, SMP / MTs, SMA / MA, D1, D2, D3, D4, S1, S2, atau S3.',
 'Isi Nama institusi. Lengkapi Jurusan, Tahun masuk, Tahun lulus, dan Gelar sesuai data pendidikan. Kosongkan Tahun lulus bila masih belajar.',
 'Tekan Simpan pendidikan dan pastikan catatan muncul pada daftar. Tekan Batal untuk menutup formulir tanpa menyimpan perubahan.',
 'Untuk menghapus catatan yang salah atau duplikat, tekan Hapus pada catatan itu, baca konfirmasinya, lalu lanjutkan hanya jika benar. Periksa kembali daftar setelahnya.'
 ],[['25-form-pendidikan','Form pendidikan dengan jenjang dropdown dan tombol simpan',[540,195,530,595],315]],'Jenjang dan nama institusi wajib diisi. Tahun yang diisi berada antara 1900 dan tahun berjalan; tahun lulus tidak boleh lebih awal daripada tahun masuk. Hapus pendidikan meminta konfirmasi.');

page('Mengganti password akun',
 'Menu Security menyediakan perubahan password. Konfirmasi password saat membuka menu bukan permintaan membuat password baru.',[
 'Buka menu akun, Settings, lalu Security.',
 'Jika muncul Confirm password, masukkan password yang sedang digunakan dan tekan Confirm.',
 'Pada Update password, isi Current password dengan password lama, New password dengan password baru, dan Confirm password dengan pengulangan password baru.',
 'Tekan Save dan tunggu pemberitahuan Password updated. Simpan password secara aman dan gunakan password baru pada login berikutnya.',
 'Ikuti aturan password yang ditampilkan aplikasi. Pada lingkungan produksi, gunakan sedikitnya 12 karakter dengan huruf besar kecil, angka, dan simbol, serta hindari password yang pernah bocor.',
 'Jika lupa password lama, gunakan Forgot your password pada login bila layanan reset tersedia, atau hubungi admin.'
 ],[['27-konfirmasi-password','Konfirmasi password saat membuka Security',[550,280,425,340],290],['28-security','Form perubahan password pada Security',[540,225,540,400],345]],'Passkey dan 2FA tidak tersedia pada konfigurasi saat ini. Screenshot formulir sengaja tidak berisi password. Jangan menuliskan password pada dokumen atau catatan pekerjaan.');

page('Mengatur tampilan dan keluar aplikasi',
 'Appearance mengubah tema tampilan. Log out mengakhiri sesi penggunaan akun pada browser.',[
 'Buka Settings lalu Appearance. Pilih Light untuk tema terang, Dark untuk tema gelap, atau System untuk mengikuti pengaturan perangkat.',
 'Tema berubah melalui pilihan tersebut tanpa tombol Save pada halaman Appearance.',
 'Sebelum keluar, pastikan seluruh perubahan presensi, jurnal, LCKH, atau profil sudah disimpan.',
 'Tekan nama akun di sidebar lalu pilih Log out. Pada HP, gunakan menu profil.',
 'Pastikan kembali ke halaman publik atau login. Menutup tab saja tidak selalu mengakhiri sesi login.'
 ],[['29-tampilan','Pilihan tema tampilan aplikasi',[285,225,800,145],475],['22-menu-akun','Menu akun untuk Settings dan Log out',[10,600,270,205],250]],'Selalu keluar dari akun pada perangkat bersama. Jangan menyimpan password pada browser perangkat yang digunakan banyak orang.');

page('Mengatasi kendala presensi dan pembelajaran',
 'Gunakan petunjuk berikut sebelum meminta bantuan. Sertakan tanggal dan sesi saat melaporkan kendala.',[
 'Kamera tidak muncul: periksa HTTPS, izin kamera browser, pilihan kamera perangkat, dan apakah kamera sedang digunakan aplikasi lain. Tutup scanner lalu buka kembali. Gunakan input manual bila diperlukan.',
 'QR tidak dikenali: pastikan kartu sesuai siswa aktif pada kelas dan sesi terpilih. Periksa pencahayaan dan posisi kartu. QR milik kelas lain tidak dapat dipakai pada sesi tersebut.',
 'GPS ditolak atau waktu habis: aktifkan lokasi perangkat dan izin browser, gunakan HTTPS, cari lokasi dengan sinyal lebih baik, lalu coba lagi. Jangan mengirim koordinat palsu.',
 'Di luar area presensi: datang ke dalam radius madrasah yang ditetapkan. Jika sudah berada di lokasi tetapi tetap ditolak, laporkan kepada admin untuk memeriksa konfigurasi lokasi.',
 'Jadwal atau siswa kosong: periksa tanggal dan hari. Minta admin memeriksa semester aktif, jadwal, penugasan, dan anggota kelas.',
 'Simpan Presensi ditolak: pastikan tidak ada siswa berstatus Belum dipresensi dan panjang catatan tidak melebihi batas. Lengkapi lalu simpan kembali.',
 'Jurnal terkunci: simpan status semua siswa pada tanggal dan sesi yang sama. Jika daftar siswa kosong, minta admin memperbaikinya terlebih dahulu.',
 'Checkout ditolak: pastikan Check In sudah ada, LCKH selesai, semua jurnal sesi selesai, serta lokasi berada dalam area. Baca daftar kewajiban pada panel Persyaratan checkout.'
 ],[], 'Saat melapor, kirim nama akun, tanggal kejadian, menu atau sesi, pesan error, dan screenshot yang relevan. Jangan sertakan password.');

page('Mengatasi kendala akun dan pencatatan',
 'Beberapa kendala memerlukan perbaikan data oleh admin atau tindak lanjut kepala madrasah.',[
 'Tidak bisa login: pastikan email benar dan gunakan password terakhir. Setelah email diubah di Profile, login memakai email baru.',
 'Akun belum terhubung dengan GTK: minta admin menghubungkan akun ke GTK yang benar. Jangan memakai akun rekan guru untuk sementara.',
 'Profil gagal disimpan: baca pesan validasi. Periksa data wajib, format email, panjang nilai, dan kemungkinan NIP atau NUPTK sudah dipakai GTK lain.',
 'Pendidikan gagal disimpan: isi jenjang dan nama institusi; periksa tahun masuk dan tahun lulus. Jangan memasukkan tahun yang melebihi tahun berjalan.',
 'Riwayat tidak ditemukan: periksa bulan atau rentang tanggal serta semua filter. Gunakan Reset filter bila tersedia.',
 'LCKH masih Menunggu approval walaupun Selesai: isian telah lengkap, tetapi approval kepala madrasah belum diberikan. Periksa kembali kemudian.',
 'LCKH Perlu perbaikan: baca catatan kepala madrasah, ubah data pada tanggal terkait, selesaikan kembali, lalu tunggu pemeriksaan ulang.',
 'Disposisi tidak bisa langsung diselesaikan: pastikan sudah dibaca dan tekan Mulai Diproses lebih dahulu. Untuk koreksi setelah Selesai, koordinasikan dengan pemberi tugas.'
 ],[], 'Jika aplikasi menampilkan gangguan server, catat waktu dan pesan error, muat ulang sekali setelah koneksi stabil, lalu laporkan kepada admin jika berulang.');

page('Daftar periksa sebelum mengakhiri hari',
 'Gunakan daftar ini untuk memastikan pekerjaan harian dan pencatatan telah lengkap.',[
 'Identitas akun guru benar dan Check In hari ini tercatat.',
 'Seluruh sesi mengajar yang menjadi kewajiban hari ini sudah diperiksa.',
 'Presensi setiap siswa sudah sesuai kondisi dan tersimpan pada sesi serta tanggal yang benar.',
 'Setiap jurnal mengajar memuat materi, catatan, dan refleksi, serta berstatus Selesai.',
 'LCKH sudah memuat kegiatan beserta hasilnya dan berstatus Selesai.',
 'Disposisi yang jatuh tempo telah ditindaklanjuti dan statusnya sesuai perkembangan pekerjaan.',
 'Persyaratan checkout lengkap, Check Out dilakukan dari lokasi sebenarnya, dan jam pulang sudah diperiksa pada riwayat.',
 'Catatan perbaikan kepala madrasah sudah dibaca dan ditindaklanjuti bila ada.',
 'Perubahan profil dan pendidikan yang dilakukan sudah tersimpan.',
 'Akun sudah Log out pada perangkat bersama.'
 ],[], 'Bahan peninjauan madrasah: pastikan alamat akses resmi, penanggung jawab bantuan, kebijakan koreksi presensi, alur izin dan sakit, serta waktu operasional ditetapkan sesuai kebijakan yang berlaku.');

file_put_contents(__DIR__.'/guide-pages.json',json_encode($pages,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
if(in_array('--manifest',$argv,true)) {echo count($pages)+2; exit;}
// Cover and a stable page index make the document easy to review without field updates.
$section->addTextBreak(2);
$section->addTitle('SOP dan Panduan Penggunaan MADIKA',0);
$section->addText('Untuk Akun Guru',['size'=>20,'bold'=>true,'color'=>'000000'],['spaceAfter'=>200]);
$section->addText('Madrasah Digital Kita',['size'=>14,'color'=>'000000'],['spaceAfter'=>450]);
$section->addText('Petunjuk operasional setiap modul dengan screenshot tahapan dan contoh pencatatan.',['size'=>12],['spaceAfter'=>300]);
$section->addText('Versi 1.0  |  29 Oktober 2026',['size'=>11],['spaceAfter'=>100]);
$section->addText('Untuk peninjauan dan penyesuaian madrasah',['size'=>11],['spaceAfter'=>500]);
$section->addText('Cakupan',['bold'=>true,'size'=>12]);
$section->addText('Login dan dashboard, presensi guru, jadwal mengajar, presensi siswa, jurnal mengajar, LCKH, disposisi, profil GTK, riwayat pendidikan, keamanan akun, dan tampilan.');
$section->addText('Screenshot menggunakan akun Guru Contoh MADIKA dan data latihan. Alamat serta tanggal pada gambar mengikuti lingkungan contoh.');
$section->addPageBreak();
$section->addTitle('Daftar isi',1);
foreach($pages as $i=>$p){$run=$section->addTextRun(['spaceAfter'=>45,'tabs'=>[new \PhpOffice\PhpWord\Style\Tab('right',9800,'dot')]]);$run->addText(($i+1).'  '.$p['title'],['size'=>10.5]);$run->addText("\t".($i+3),['size'=>10.5]);}

function img($section,$item,$number){
 [$name,$caption,$rect,$width]=$item;
 $path=__DIR__.'/assets/crop-'.$name.'.png';
 if(!file_exists($path))throw new RuntimeException('Missing cropped screenshot '.$name);
 [$w,$h]=getimagesize($path);
 // Limit a single screenshot to fit beside explanatory steps on an A4 page.
 $maxH= count($GLOBALS['currentImages'])>1 ? 195 : 330;
 $scale=min($width/$w,$maxH/$h);
 $section->addImage($path,['width'=>$w*$scale,'height'=>$h*$scale,'alignment'=>'center','wrappingStyle'=>'inline'],false);
 $section->addText('Gambar '.$number.'  '.$caption,['size'=>9,'italic'=>true,'color'=>'404040'],['alignment'=>'center','spaceAfter'=>100]);
}
$figure=0;
foreach($pages as $i=>$p){
 $section->addPageBreak();
 $section->addTitle(($i+1).' '.$p['title'],1);
 $section->addText($p['purpose']);
 foreach($p['steps'] as $n=>$step){$section->addText(($n+1).'. '.$step,[],['spaceAfter'=>75,'lineHeight'=>1.06]);}
 $GLOBALS['currentImages']=$p['images'];
 foreach($p['images'] as $im){img($section,$im,++$figure);}
 if($p['note']){$section->addText($p['note'],['size'=>10],['spaceBefore'=>70,'spaceAfter'=>0]);}
}
file_put_contents(__DIR__.'/guide-pages.json',json_encode($pages,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
IOFactory::createWriter($doc,'Word2007')->save(__DIR__.'/SOP_dan_Panduan_MADIKA_Guru.docx');
echo 'Dokumen disimpan. Halaman direncanakan: '.(count($pages)+2).'. Gambar: '.$figure."\n";
