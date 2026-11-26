-- ========================================================
-- Database: db_rutantjg
-- Proyek: Portal Resmi Rumah Tahanan Negara Kelas IV Tanjung
-- ========================================================

CREATE DATABASE IF NOT EXISTS `db_rutantjg` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_rutantjg`;

-- --------------------------------------------------------
-- 1. Struktur Tabel `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal Akun Admin Default
-- Username: admin
-- Password: admin123 (hash password_hash bcrypt)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`) VALUES
(1, 'admin', '$2y$10$6Xyyey6Iuq3loezbcFCSJ.su4K4c36x1x/IlKyrRfh/C1zwdftLFW', 'Administrator Rutan Tanjung')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- --------------------------------------------------------
-- 2. Struktur Tabel `berita`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `berita` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `isi` TEXT NOT NULL,
  `gambar` VARCHAR(255) DEFAULT NULL,
  `penulis` VARCHAR(100) NOT NULL,
  `tanggal_dibuat` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal Berita Rutan Tanjung
-- --------------------------------------------------------
INSERT INTO `berita` (`id`, `judul`, `slug`, `isi`, `gambar`, `penulis`, `tanggal_dibuat`) VALUES
(1, 
 'Tingkatkan Keamanan dan Ketertiban, Rutan Kelas IV Tanjung Gelar Razia Gabungan Bersama APH',
 'tingkatkan-keamanan-dan-ketertiban-rutan-kelas-iv-tanjung-gelar-razia-gabungan-bersama-aph',
 'Tanjung — Dalam rangka memperkuat deteksi dini gangguan keamanan dan ketertiban serta mewujudkan Zero Halinar (Handphone, Pungli, dan Narkoba), Rumah Tahanan Negara (Rutan) Kelas IV Tanjung melaksanakan kegiatan razia dan penggeledahan blok hunian bersama Aparat Penegak Hukum (APH) setempat.\r\n\r\nKegiatan ini dipimpin langsung oleh Kepala Rutan Tanjung didampingi seluruh jajaran pejabat struktural dan petugas pengamanan. Razia gabungan berlangsung secara tertib, teliti, dan mengedepankan pendekatan humanis kepada para warga binaan.\r\n\r\nKepala Rutan menegaskan bahwa kegiatan razia berkala ini merupakan bukti komitmen nyata jajaran Pemasyarakatan dalam menjaga integritas dan kondusifitas lingkungan Rutan. Sinergi dengan instansi TNI-Polri dan BNNK setempat juga terus diperkuat demi menciptakan lingkungan pembinaan yang bersih, aman, dan kondusif bagi seluruh warga binaan maupun petugas.',
 'berita-kegiatan-1.jpg',
 'Humas Rutan Tanjung',
 NOW() - INTERVAL 2 DAY),

(2,
 'Program Pembinaan Kemandirian: Warga Binaan Rutan Tanjung Hasilkan Karya Kerajinan Berkualitas',
 'program-pembinaan-kemandirian-warga-binaan-rutan-tanjung-hasilkan-karya-kerajinan-berkualitas',
 'Tanjung — Rutan Kelas IV Tanjung terus mendorong peningkatan kapasitas dan keterampilan para warga binaan melalui berbagai program pembinaan kemandirian. Program ini mencakup pelatihan kerajinan tangan, pertukangan kayu, pengelasan, hingga pertanian hidroponik dan perikanan air tawar.\r\n\r\nMelalui bimbingan para instruktur dan petugas pembina, warga binaan diarahkan untuk memiliki bekal keahlian yang nyata dan bermanfaat setelah selesai menjalani masa pidana. Hasil karya kerajinan warga binaan bahkan telah dipamerkan dalam beberapa ajang pameran UMKM daerah Tabalong dan mendapat apresiasi positif dari masyarakat luas.\r\n\r\n\"Kami ingin setiap warga binaan yang keluar dari sini membawa keahlian dan rasa percaya diri baru. Pembinaan ini membuktikan bahwa masa pembinaan adalah masa untuk berbenah dan berkarya,\" ungkap Kasubsi Pelayanan Tahanan.',
 'berita-rutan-tanjung.jpg',
 'Subsi Pelayanan Tahanan',
 NOW() - INTERVAL 1 DAY),

(3,
 'Komitmen Bersama Rutan Tanjung dalam Mewujudkan Wilayah Bebas dari Korupsi (WBK)',
 'komitmen-bersama-rutan-tanjung-dalam-mewujudkan-wilayah-bebas-dari-korupsi-wbk',
 'Tanjung — Seluruh pegawai dan staf Rumah Tahanan Negara Kelas IV Tanjung menandatangani Piagam Komitmen Bersama Pembangunan Zona Integritas menuju Wilayah Bebas dari Korupsi (WBK) dan Wilayah Birokrasi Bersih dan Melayani (WBBM).\r\n\r\nPenandatanganan komitmen bersama ini menjadi tonggak penting dalam memperkuat tata kelola birokrasi yang transparan, akuntabel, dan bebas dari praktik korupsi, kolusi, nepotisme, serta pungutan liar. Seluruh layanan publik di Rutan Tanjung dipastikan berjalan sesuai SOP tanpa dipungut biaya apapun (GRATIS).\r\n\r\nMasyarakat juga diimbau untuk aktif memanfaatkan saluran pengaduan resmi WhatsApp apabila menemukan pelanggaran atau ketidaksesuaian dalam pelayanan kunjungan maupun penitipan barang. Keterbukaan ini mencerminkan komitmen penuh Rutan Tanjung dalam melayani masyarakat dengan PASTI.',
 'berita-kemenimipas.jpg',
 'Tim Humas & TI',
 NOW())
ON DUPLICATE KEY UPDATE `judul` = `judul`;

-- --------------------------------------------------------
-- 3. Struktur Tabel `jadwal_pelayanan`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jadwal_pelayanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori` ENUM('kunjungan', 'penitipan') NOT NULL,
  `hari` VARCHAR(100) NOT NULL,
  `waktu` VARCHAR(100) NOT NULL,
  `keterangan` VARCHAR(150) DEFAULT '',
  `is_tutup` TINYINT(1) DEFAULT 0,
  `urutan` INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal `jadwal_pelayanan`
-- --------------------------------------------------------
INSERT INTO `jadwal_pelayanan` (`id`, `kategori`, `hari`, `waktu`, `keterangan`, `is_tutup`, `urutan`) VALUES
(1, 'kunjungan', 'Senin - Kamis', '08.30 - 11.30 WITA', 'Sesi Pagi', 0, 1),
(2, 'kunjungan', 'Jum\'at - Minggu & Libur', 'Tutup', 'Tidak ada kunjungan', 1, 2),
(3, 'penitipan', 'Senin - Kamis', '08.30 - 11.30 WITA', 'Sesi Pagi', 0, 1),
(4, 'penitipan', 'Senin - Kamis', '14.00 - 16.00 WITA', 'Sesi Siang', 0, 2),
(5, 'penitipan', 'Jum\'at & Sabtu', '08.30 - 11.30 WITA', 'Sesi Pagi', 0, 3),
(6, 'penitipan', 'Minggu & Hari Libur', 'Tutup', 'Tidak ada layanan', 1, 4)
ON DUPLICATE KEY UPDATE `hari` = VALUES(`hari`), `waktu` = VALUES(`waktu`);

-- --------------------------------------------------------
-- 4. Struktur Tabel `pengaturan_jadwal`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengaturan_jadwal` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kunci` VARCHAR(100) NOT NULL UNIQUE,
  `nilai` TEXT NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal `pengaturan_jadwal`
-- --------------------------------------------------------
INSERT INTO `pengaturan_jadwal` (`id`, `kunci`, `nilai`) VALUES
(1, 'catatan_penitipan', 'Barang/makanan wajib sesuai ketentuan yang berlaku. Lihat poster syarat di bawah.'),
(2, 'catatan_kunjungan', 'Pengunjung wajib mematuhi ketentuan yang berlaku. Lihat poster syarat di bawah.')
ON DUPLICATE KEY UPDATE `nilai` = VALUES(`nilai`);

-- --------------------------------------------------------
-- 5. Struktur Tabel `popup_ads`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `popup_ads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `gambar` VARCHAR(255) NOT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `is_aktif` TINYINT(1) DEFAULT 1,
  `urutan` INT DEFAULT 1,
  `keterangan` VARCHAR(255) DEFAULT '',
  `dibuat_pada` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal `popup_ads`
-- --------------------------------------------------------
INSERT INTO `popup_ads` (`id`, `gambar`, `link_url`, `is_aktif`, `urutan`, `keterangan`) VALUES
(1, 'pop up ads/Selamat Datang di Rutan Tanjung.png', '', 1, 1, 'Selamat Datang di Rutan Tanjung'),
(2, 'pop up ads/IMG-20261113-WA0163.jpg', '', 1, 2, 'Info Rutan Tanjung 1'),
(3, 'pop up ads/IMG-20261113-WA0164.jpg', '', 1, 3, 'Info Rutan Tanjung 2'),
(4, 'pop up ads/IMG-20261113-WA0165.jpg', '', 1, 4, 'Info Rutan Tanjung 3'),
(5, 'pop up ads/IMG-20261113-WA0166.jpg', '', 1, 5, 'Info Rutan Tanjung 4'),
(6, 'pop up ads/IMG-20261113-WA0167.jpg', '', 1, 6, 'Info Rutan Tanjung 5'),
(7, 'pop up ads/gratifikasi(1).png', '', 1, 7, 'Tolak Gratifikasi Rutan Tanjung')
ON DUPLICATE KEY UPDATE `gambar` = VALUES(`gambar`);

-- --------------------------------------------------------
-- 6. Struktur Tabel `pejabat_struktural`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pejabat_struktural` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(150) NOT NULL,
  `jabatan` VARCHAR(150) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `is_kepala` TINYINT(1) DEFAULT 0,
  `is_aktif` TINYINT(1) DEFAULT 1,
  `urutan` INT DEFAULT 1,
  `dibuat_pada` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal `pejabat_struktural`
-- --------------------------------------------------------
INSERT INTO `pejabat_struktural` (`id`, `nama`, `jabatan`, `deskripsi`, `foto`, `is_kepala`, `is_aktif`, `urutan`) VALUES
(1, 'RAYMON ANDIKA GIRSANG, A.Md.IP., S.H., M.H.', 'Kepala Rutan', 'Memimpin dan mengkoordinasikan seluruh kegiatan penyelenggaraan tugas dan fungsi Rumah Tahanan Negara Kelas IV Tanjung sesuai peraturan perundang-undangan yang berlaku.', 'Foto Pejabat/raymond andika girsang.png', 1, 1, 1),
(2, 'Al Muqtadir Pasya, S.Tr.Pas.', 'Kasubsi Pelayanan Tahanan', 'Mengelola dan mengkoordinasikan pelayanan terhadap tahanan meliputi pendaftaran, perawatan, dan administrasi tahanan.', 'Foto Pejabat/AL MUQTADIR PASYA, S.Tr.Pas..png', 0, 1, 1),
(3, 'Muhammad Arief Rakhman', 'Kasubsi Kesatuan Pengamanan Rutan', 'Bertanggung jawab atas keamanan, ketertiban, dan pengawasan di lingkungan Rutan Kelas IV Tanjung.', 'Foto Pejabat/MUHAMMAD ARIEF RAKHMAN.png', 0, 1, 2),
(4, 'Masripani, S.E., M.M.', 'Kasubsi Pengelolaan', 'Mengelola urusan administrasi umum, kepegawaian, keuangan, dan tata usaha Rutan Kelas IV Tanjung.', 'Foto Pejabat/MASRIPANI, S.E., M.M..png', 0, 1, 3)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `jabatan` = VALUES(`jabatan`);
