# Panduan & Konvensi Pengembangan — Rutan Tanjung Kelas IIB

Dokumen ini berisi aturan teknis, standar responsivitas, dan konvensi proyek yang **wajib diingat dan dipatuhi** untuk seluruh sesi pengembangan website Rutan Tanjung Kelas IIB.

---

## 1. Standar Encoding & Karakter (Anti-Mojibake / Anti-Glitch)
- **Tanda Hubung / Pemisah**: Selalu gunakan karakter ASCII standar (`-`, tanda minus standar) untuk rentang waktu/hari (misalnya: `Senin - Kamis`, `08.30 - 11.30 WITA`), bukan *en-dash* (`–`), *em-dash* (`—`), atau karakter khusus yang rentan korup menjadi simbol belah ketupat tanda tanya (`\uFFFD` / ``).
- **Database MySQL**:
  - Seluruh tabel wajib menggunakan `utf8mb4` dengan collation `utf8mb4_unicode_ci`.
  - File koneksi database (`koneksi.php`) wajib mempertahankan `$conn->set_charset("utf8mb4")`.
  - File skrip database (`database.sql`) wajib disimpan dalam format UTF-8 tanpa BOM.

---

## 2. Standar Responsivitas Tiga Breakpoint
Setiap penambahan atau modifikasi halaman publik maupun panel administrasi wajib menguji dan mendukung 3 kelas layar:

### A. Desktop (> 1024px)
- Navigasi menu utama horizontal penuh dengan efek hover dan scroll spy.
- Tata letak multi-kolom penuh (3–4 kolom pada grid berita, saluran sosmed, dan footer; 2 kolom pada jadwal pelayanan dan kontak).

### B. Tablet (768px – 1024px)
- **Navbar**: Beralih otomatis ke tombol hamburger dan drawer menu geser ke bawah.
- **Tata Letak Adaptif**: Grid multi-kolom beradaptasi menjadi 2 kolom (misalnya: sorotan berita, daftar pejabat, jadwal, dan tautan footer).
- **Formulir**: Dua kolom formulir beralih menjadi 1 kolom vertikal yang rapi.

### C. Mobile / HP (< 768px & < 480px)
- **Anti-Overflow**: Wajib menjaga `html, body { overflow-x: hidden; }`, dilarang menggunakan ukuran lebar absolut (*fixed width*) yang melebihi lebar layar. Gunakan `max-width: 100%`, `%`, atau `rem`.
- **Drawer Menu**: Drawer menu memiliki `max-height: calc(100vh - 80px)` dan `overflow-y: auto` (`-webkit-overflow-scrolling: touch`) agar dapat di-scroll lancar pada HP berlayar pendek atau orientasi landscape.
- **Grid 1 Kolom**: Seluruh kartu dan section beradaptasi menjadi 1 kolom vertikal.
- **Tipografi & Jarak Proporsional**:
  - Gunakan `clamp()` untuk heading utama (misal `clamp(1.85rem, 6.5vw, 2.7rem)`).
  - Padding section diperkecil menjadi `56px 0` (< 768px) dan `44px 0` (< 480px).
- **Tabel Data (Terutama Panel Admin)**:
  - Wajib dibungkus dengan `<div class="table-responsive">` (`overflow-x: auto; -webkit-overflow-scrolling: touch;`).
  - Tabel diberikan `min-width: 650px` agar kolom data tidak tertekan/gepeng dan dapat digulir menyamping (*swipeable*) dengan nyaman.
- **Ramah Layar Sentuh (*Touch-Friendly*)**:
  - Ukuran tombol dan tautan minimal 44px x 44px dengan sela antar tombol minimal 8–12px.
  - Elemen form (`input`, `select`, `textarea`) pada layar mobile wajib memiliki `font-size: 16px` untuk mencegah auto-zoom yang mengganggu di iOS Safari.

---

## 3. Konsistensi Desain Visual (Design Identity)
- **Warna Utama**:
  - Biru Resmi: `--primary` (`#0a2463`), `--primary-dark` (`#061540`), `--primary-light` (`#1a3a7a`).
  - Emas Aksen: `--gold` (`#c9a84c`), `--gold-light` (`#e0c068`), `--gold-dark` (`#a88830`).
  - Permukaan/Netral: Putih (`#ffffff`), Abu-abu lembut (`#f4f6fa`, `#eef1f6`).
- **Integritas Desain**: Jangan pernah merusak atau menghilangkan elemen visual yang sudah ada (efek bayangan halus, badge status, foto pejabat melingkar, dan kartu bergaya modern) saat melakukan penyesuaian fungsionalitas.
