-- ========================================================
-- Database: db_rutantjg
-- Proyek: Portal Resmi Rumah Tahanan Negara Tanjung Kelas IIB
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
 'Tingkatkan Keamanan dan Ketertiban, Rutan Kelas IIB Tanjung Gelar Razia Gabungan Bersama APH',
 'tingkatkan-keamanan-dan-ketertiban-rutan-kelas-iib-tanjung-gelar-razia-gabungan-bersama-aph',
 'Tanjung — Dalam rangka memperkuat deteksi dini gangguan keamanan dan ketertiban serta mewujudkan Zero Halinar (Handphone, Pungli, dan Narkoba), Rumah Tahanan Negara (Rutan) Kelas IIB Tanjung melaksanakan kegiatan razia dan penggeledahan blok hunian bersama Aparat Penegak Hukum (APH) setempat.\r\n\r\nKegiatan ini dipimpin langsung oleh Kepala Rutan Tanjung didampingi seluruh jajaran pejabat struktural dan petugas pengamanan. Razia gabungan berlangsung secara tertib, teliti, dan mengedepankan pendekatan humanis kepada para warga binaan.\r\n\r\nKepala Rutan menegaskan bahwa kegiatan razia berkala ini merupakan bukti komitmen nyata jajaran Pemasyarakatan dalam menjaga integritas dan kondusifitas lingkungan Rutan. Sinergi dengan instansi TNI-Polri dan BNNK setempat juga terus diperkuat demi menciptakan lingkungan pembinaan yang bersih, aman, dan kondusif bagi seluruh warga binaan maupun petugas.',
 'berita-kegiatan-1.jpg',
 'Humas Rutan Tanjung',
 NOW() - INTERVAL 2 DAY),

(2,
 'Program Pembinaan Kemandirian: Warga Binaan Rutan Tanjung Hasilkan Karya Kerajinan Berkualitas',
 'program-pembinaan-kemandirian-warga-binaan-rutan-tanjung-hasilkan-karya-kerajinan-berkualitas',
 'Tanjung — Rutan Kelas IIB Tanjung terus mendorong peningkatan kapasitas dan keterampilan para warga binaan melalui berbagai program pembinaan kemandirian. Program ini mencakup pelatihan kerajinan tangan, pertukangan kayu, pengelasan, hingga pertanian hidroponik dan perikanan air tawar.\r\n\r\nMelalui bimbingan para instruktur dan petugas pembina, warga binaan diarahkan untuk memiliki bekal keahlian yang nyata dan bermanfaat setelah selesai menjalani masa pidana. Hasil karya kerajinan warga binaan bahkan telah dipamerkan dalam beberapa ajang pameran UMKM daerah Tabalong dan mendapat apresiasi positif dari masyarakat luas.\r\n\r\n\"Kami ingin setiap warga binaan yang keluar dari sini membawa keahlian dan rasa percaya diri baru. Pembinaan ini membuktikan bahwa masa pembinaan adalah masa untuk berbenah dan berkarya,\" ungkap Kasubsi Pelayanan Tahanan.',
 'berita-rutan-tanjung.jpg',
 'Subsi Pelayanan Tahanan',
 NOW() - INTERVAL 1 DAY),

(3,
 'Komitmen Bersama Rutan Tanjung dalam Mewujudkan Wilayah Bebas dari Korupsi (WBK)',
 'komitmen-bersama-rutan-tanjung-dalam-mewujudkan-wilayah-bebas-dari-korupsi-wbk',
 'Tanjung — Seluruh pegawai dan staf Rumah Tahanan Negara Kelas IIB Tanjung menandatangani Piagam Komitmen Bersama Pembangunan Zona Integritas menuju Wilayah Bebas dari Korupsi (WBK) dan Wilayah Birokrasi Bersih dan Melayani (WBBM).\r\n\r\nPenandatanganan komitmen bersama ini menjadi tonggak penting dalam memperkuat tata kelola birokrasi yang transparan, akuntabel, dan bebas dari praktik korupsi, kolusi, nepotisme, serta pungutan liar. Seluruh layanan publik di Rutan Tanjung dipastikan berjalan sesuai SOP tanpa dipungut biaya apapun (GRATIS).\r\n\r\nMasyarakat juga diimbau untuk aktif memanfaatkan saluran pengaduan resmi WhatsApp apabila menemukan pelanggaran atau ketidaksesuaian dalam pelayanan kunjungan maupun penitipan barang. Keterbukaan ini mencerminkan komitmen penuh Rutan Tanjung dalam melayani masyarakat dengan PASTI.',
 'berita-kemenimipas.jpg',
 'Tim Humas & TI',
 NOW())
ON DUPLICATE KEY UPDATE `judul` = `judul`;
