<?php
require_once __DIR__ . '/koneksi.php';

// Pagination: 1 featured + 6 grid di halaman 1, 6 grid di halaman selanjutnya
$grid_per_page = 6;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

// Total berita
$total_row = $conn->query("SELECT COUNT(*) as total FROM berita")->fetch_assoc();
$total_berita = (int)($total_row['total'] ?? 0);

// Hitung total halaman:
// Halaman 1: ambil 7 (1 featured + 6 grid)
// Halaman 2+: ambil 6 per halaman, offset mulai dari 7
if ($total_berita <= 1) {
    $total_halaman = 1;
} else {
    // Sisa setelah halaman pertama (7 berita) dibagi 6
    $sisa = max(0, $total_berita - 7);
    $total_halaman = 1 + max(0, (int)ceil($sisa / $grid_per_page));
}

// Hitung limit dan offset
if ($halaman === 1) {
    $limit_query = 7; // 1 featured + 6 grid
    $offset = 0;
} else {
    $limit_query = $grid_per_page;
    $offset = 7 + ($halaman - 2) * $grid_per_page;
}

// Ambil berita untuk halaman ini
$stmt = $conn->prepare("SELECT * FROM berita ORDER BY tanggal_dibuat DESC, id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit_query, $offset);
$stmt->execute();
$result_berita = $stmt->get_result();
$stmt->close();

// Alias per_page untuk kompatibilitas tampilan info
$per_page = ($halaman === 1) ? 7 : $grid_per_page;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Berita & Publikasi — Rutan Kelas IV Tanjung</title>
  <meta name="description" content="Berita, kabar, dan publikasi terkini seputar kegiatan, pembinaan, dan pelayanan Rutan Kelas IV Tanjung Kemenimipas RI." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>" />
  <link rel="icon" type="image/png" href="banner/LOGO RUTAN png.png" />
  <style>
    /* ===== HALAMAN BERITA — STYLES UNIK ===== */
    .berita-page-hero {
      position: relative;
      background: url('gambar atau poster/header rutan.jpg') center center / cover no-repeat;
      padding: 130px 0 70px;
      overflow: hidden;
    }

    .berita-page-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        linear-gradient(135deg, rgba(6, 21, 64, 0.88) 0%, rgba(10, 36, 99, 0.78) 50%, rgba(6, 21, 64, 0.90) 100%),
        radial-gradient(ellipse 60% 80% at 80% 50%, rgba(201, 168, 76, 0.15) 0%, transparent 70%);
      pointer-events: none;
    }

    /* Pola grid abstrak */
    .berita-page-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events: none;
    }

    .berita-hero-content {
      position: relative;
      z-index: 1;
      text-align: center;
    }

    .berita-hero-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(201, 168, 76, 0.18);
      border: 1px solid rgba(201, 168, 76, 0.4);
      color: var(--gold-light);
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      padding: 7px 18px;
      border-radius: 30px;
      margin-bottom: 20px;
    }

    .berita-hero-title {
      font-size: clamp(2rem, 5vw, 3.2rem);
      font-weight: 800;
      color: var(--white);
      line-height: 1.2;
      margin-bottom: 16px;
      letter-spacing: -1px;
    }

    .berita-hero-title span {
      color: var(--gold-light);
    }

    .berita-hero-desc {
      font-size: 1.1rem;
      color: rgba(255,255,255,0.72);
      max-width: 560px;
      margin: 0 auto;
      line-height: 1.65;
    }



    /* ===== FILTER & SEARCH BAR ===== */
    .berita-filter-section {
      background: var(--white);
      border-bottom: 1px solid var(--gray-100);
      padding: 20px 0;
      position: sticky;
      top: 80px;
      z-index: 50;
      box-shadow: 0 2px 10px rgba(10,36,99,0.06);
    }

    .filter-container {
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .search-berita-wrap {
      position: relative;
      flex: 1;
      max-width: 420px;
      min-width: 220px;
    }

    .search-berita-wrap input {
      width: 100%;
      padding: 11px 16px 11px 44px;
      border: 1.5px solid var(--gray-200);
      border-radius: 50px;
      font-size: 0.92rem;
      background: var(--gray-50);
      outline: none;
      transition: all 0.25s;
      color: var(--gray-800);
    }

    .search-berita-wrap input:focus {
      border-color: var(--primary);
      background: var(--white);
      box-shadow: 0 0 0 3px rgba(10,36,99,0.1);
    }

    .search-berita-wrap .search-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray-400);
      pointer-events: none;
    }

    .total-berita-info {
      font-size: 0.88rem;
      color: var(--gray-600);
      flex-shrink: 0;
    }

    .total-berita-info strong {
      color: var(--primary);
    }

    /* ===== GRID BERITA — DESAIN EDITORIAL ===== */
    .berita-halaman-section {
      padding: 60px 0 80px;
      background: var(--off-white);
    }

    /* Berita utama (artikel pertama besar) */
    .berita-featured {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 0;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 8px 40px rgba(10,36,99,0.14);
      margin-bottom: 48px;
      border: 1px solid var(--gray-100);
      background: var(--white);
      text-decoration: none;
      color: inherit;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .berita-featured:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 50px rgba(10,36,99,0.2);
    }

    .featured-img-wrap {
      position: relative;
      overflow: hidden;
      min-height: 380px;
    }

    .featured-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 0.5s ease;
    }

    .berita-featured:hover .featured-img {
      transform: scale(1.05);
    }

    .featured-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to right, rgba(6,21,64,0.3) 0%, transparent 70%);
    }

    .featured-badge {
      position: absolute;
      top: 20px;
      left: 20px;
      background: var(--gold);
      color: var(--primary-dark);
      font-size: 0.75rem;
      font-weight: 800;
      padding: 5px 14px;
      border-radius: 20px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .featured-body {
      padding: 40px 36px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .featured-category {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 14px;
    }

    .featured-title {
      font-size: 1.75rem;
      font-weight: 800;
      line-height: 1.3;
      color: var(--primary-dark);
      margin-bottom: 16px;
    }

    .featured-excerpt {
      font-size: 1rem;
      line-height: 1.7;
      color: var(--gray-600);
      margin-bottom: 24px;
      flex-grow: 1;
    }

    .featured-meta {
      display: flex;
      align-items: center;
      gap: 16px;
      font-size: 0.84rem;
      color: var(--gray-400);
      flex-wrap: wrap;
    }

    .featured-meta-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .btn-baca-featured {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--primary);
      color: var(--white);
      padding: 12px 22px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 0.9rem;
      transition: all 0.25s;
      margin-top: 20px;
      align-self: flex-start;
      text-decoration: none;
    }

    .btn-baca-featured:hover {
      background: var(--gold-dark);
      transform: translateX(4px);
    }

    /* Grid berita reguler (3 kolom) */
    .berita-cards-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }

    @media (max-width: 1024px) {
      .berita-featured { grid-template-columns: 1fr; }
      .featured-img-wrap { min-height: 260px; }
      .berita-cards-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
    }

    @media (max-width: 768px) {
      .berita-page-hero { padding: 100px 0 50px; }
      .berita-filter-section { padding: 14px 0; top: 75px; }
      .filter-container { flex-direction: column; align-items: stretch; gap: 10px; }
      .search-berita-wrap { max-width: 100%; width: 100%; }
      .search-berita-wrap input { font-size: 16px; }
      .total-berita-info { text-align: center; }
      .berita-halaman-section { padding: 36px 0 56px; }
      .berita-cards-grid { grid-template-columns: 1fr; gap: 20px; }
      .featured-body { padding: 22px 18px; }
      .featured-title { font-size: 1.35rem; }
      .featured-img-wrap { min-height: 200px; max-height: 260px; }
    }

    /* Card reguler — desain bersih ala portal berita */
    .news-card {
      background: var(--white);
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid var(--gray-100);
      box-shadow: 0 2px 12px rgba(10,36,99,0.06);
      display: flex;
      flex-direction: column;
      transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
      text-decoration: none;
      color: inherit;
    }

    .news-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 36px rgba(10,36,99,0.14);
      border-color: rgba(201,168,76,0.4);
    }

    .news-card-img-wrap {
      position: relative;
      height: 200px;
      overflow: hidden;
      background: var(--gray-100);
    }

    .news-card-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .news-card:hover .news-card-img {
      transform: scale(1.07);
    }

    .news-card-date {
      position: absolute;
      bottom: 12px;
      left: 12px;
      background: rgba(6,21,64,0.88);
      color: var(--gold-light);
      font-size: 0.75rem;
      font-weight: 600;
      padding: 4px 11px;
      border-radius: 20px;
    }

    .news-card-body {
      padding: 20px 22px 22px;
      display: flex;
      flex-direction: column;
      flex-grow: 1;
    }

    .news-card-author {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 10px;
    }

    .news-card-title {
      font-size: 1.05rem;
      font-weight: 700;
      line-height: 1.4;
      color: var(--primary-dark);
      margin-bottom: 10px;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .news-card-excerpt {
      font-size: 0.87rem;
      color: var(--gray-600);
      line-height: 1.6;
      flex-grow: 1;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      margin-bottom: 16px;
    }

    .news-card-footer {
      padding-top: 14px;
      border-top: 1px solid var(--gray-100);
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: auto;
    }

    .news-card-read {
      font-size: 0.84rem;
      font-weight: 700;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 5px;
      transition: color 0.2s, gap 0.2s;
    }

    .news-card:hover .news-card-read {
      color: var(--gold-dark);
      gap: 9px;
    }

    /* ===== EMPTY STATE ===== */
    .berita-empty-state {
      text-align: center;
      padding: 80px 20px;
      background: var(--white);
      border-radius: 20px;
      border: 2px dashed var(--gray-200);
    }

    .berita-empty-state svg {
      margin: 0 auto 20px;
      color: var(--gray-200);
    }

    .berita-empty-state h3 {
      font-size: 1.4rem;
      color: var(--primary-dark);
      margin-bottom: 8px;
    }

    /* ===== PAGINATION ===== */
    .pagination-wrap {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      margin-top: 52px;
      flex-wrap: wrap;
    }

    .page-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 10px;
      font-size: 0.92rem;
      font-weight: 700;
      text-decoration: none;
      border: 1.5px solid var(--gray-200);
      background: var(--white);
      color: var(--gray-800);
      transition: all 0.2s;
    }

    .page-btn:hover {
      background: var(--primary);
      border-color: var(--primary);
      color: var(--white);
      transform: translateY(-2px);
    }

    .page-btn.active {
      background: var(--primary);
      border-color: var(--primary);
      color: var(--white);
      box-shadow: 0 4px 14px rgba(10,36,99,0.3);
    }

    .page-btn.disabled {
      opacity: 0.4;
      pointer-events: none;
    }
  </style>
</head>
<body>

  <!-- ===== NAVBAR ===== -->
  <header class="navbar" id="navbar">
    <div class="container navbar-inner">
      <a href="index.php" class="logo-wrap">
        <div class="logo-emblem">
          <img src="banner/LOGO RUTAN png.png" alt="Logo Rutan Tanjung" width="62" height="62" style="object-fit:contain;display:block;" />
        </div>
        <div class="logo-text">
          <span class="logo-title">RUTAN KELAS IV</span>
          <span class="logo-sub">Tanjung</span>
        </div>
      </a>
      <nav class="nav-links" id="navLinks">
        <a href="index.php#tentang" class="nav-link">Tentang</a>
        <a href="index.php#visi-misi" class="nav-link">Visi &amp; Misi</a>
        <a href="index.php#pejabat" class="nav-link">Pejabat Struktural</a>
        <a href="index.php#jadwal" class="nav-link">Jadwal</a>
        <a href="index.php#pengaduan" class="nav-link">Pengaduan</a>
        <a href="index.php#sosmed" class="nav-link">Sosial Media Kami</a>
        <a href="index.php#kontak" class="nav-link">Kontak &amp; Lokasi</a>
        <a href="berita.php" class="btn-nav-berita-cta" title="Halaman Berita &amp; Publikasi Aktif">
          <div class="nav-berita-icon-box">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
              <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10l6 6v8a2 2 0 0 1-2 2z"></path>
              <polyline points="13 2 13 8 19 8"></polyline>
              <line x1="8" y1="13" x2="16" y2="13"></line>
              <line x1="8" y1="17" x2="13" y2="17"></line>
            </svg>
          </div>
          <div class="nav-berita-text-group">
            <span class="nav-berita-label-top">BERITA &amp; INFORMASI</span>
            <span class="nav-berita-label-sub">PUBLIKASI RESMI</span>
          </div>
          <span class="nav-berita-live-dot" title="Halaman Berita Aktif"></span>
        </a>
      </nav>
      <button class="hamburger" id="hamburger" aria-label="Toggle Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- ===== HERO HEADER HALAMAN BERITA ===== -->
  <section class="berita-page-hero">
    <div class="container berita-hero-content">
      <div class="berita-hero-tag">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10l6 6v8a2 2 0 0 1-2 2z"></path>
          <polyline points="13 2 13 8 19 8"></polyline>
        </svg>
        Kabar &amp; Informasi Resmi
      </div>
      <h1 class="berita-hero-title">
        Berita &amp; <span>Publikasi</span>
      </h1>
      <p class="berita-hero-desc">
        Informasi terbaru seputar kegiatan, program pembinaan, dan pelayanan publik di Rutan Kelas IV Tanjung.
      </p>
    </div>
  </section>

  <!-- ===== FILTER BAR ===== -->
  <div class="berita-filter-section">
    <div class="container filter-container">
      <div class="search-berita-wrap">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="searchBerita" placeholder="Cari berita atau penulis..." onkeyup="filterBerita()">
      </div>
      <div class="total-berita-info">
        Menampilkan <strong><?= number_format($result_berita->num_rows) ?></strong> dari <strong><?= number_format($total_berita) ?></strong> artikel
      </div>
    </div>
  </div>

  <!-- ===== KONTEN BERITA ===== -->
  <section class="berita-halaman-section">
    <div class="container">

      <?php
      $semua_berita = [];
      while ($row = $result_berita->fetch_assoc()) {
          $semua_berita[] = $row;
      }

      if (empty($semua_berita)):
      ?>
        <div class="berita-empty-state">
          <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <h3>Belum Ada Berita</h3>
          <p style="color:var(--gray-600);font-size:0.95rem;">Berita dan publikasi akan segera tersedia. Pantau terus laman ini.</p>
        </div>

      <?php else:
        // Ambil berita pertama untuk featured (jika halaman pertama)
        $berita_utama = ($halaman === 1) ? array_shift($semua_berita) : null;
      ?>

        <!-- Artikel Utama (Halaman 1 saja) -->
        <?php if ($berita_utama): 
          $gambar_utama = (!empty($berita_utama['gambar']) && file_exists(__DIR__ . '/uploads/berita/' . $berita_utama['gambar']))
                          ? 'uploads/berita/' . htmlspecialchars($berita_utama['gambar'])
                          : 'gambar atau poster/header rutan.jpg';
          $link_utama = 'detail-berita.php?slug=' . urlencode($berita_utama['slug']);
          $isi_utama = strip_tags($berita_utama['isi']);
          $excerpt_utama = mb_strlen($isi_utama) > 220 ? mb_substr($isi_utama, 0, 220) . '...' : $isi_utama;
        ?>
          <a href="<?= $link_utama ?>" class="berita-featured" id="beritaFeatured">
            <div class="featured-img-wrap">
              <img src="<?= $gambar_utama ?>" alt="<?= htmlspecialchars($berita_utama['judul']) ?>" class="featured-img" loading="eager">
              <div class="featured-overlay"></div>
              <span class="featured-badge">📰 Berita Terbaru</span>
            </div>
            <div class="featured-body">
              <span class="featured-category">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Seputar Rutan Tanjung
              </span>
              <h2 class="featured-title"><?= htmlspecialchars($berita_utama['judul']) ?></h2>
              <p class="featured-excerpt"><?= htmlspecialchars($excerpt_utama) ?></p>
              <div class="featured-meta">
                <span class="featured-meta-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  <?= htmlspecialchars($berita_utama['penulis']) ?>
                </span>
                <span class="featured-meta-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  <?= format_tanggal_indo($berita_utama['tanggal_dibuat']) ?>
                </span>
              </div>
              <span class="btn-baca-featured">
                Baca Selengkapnya
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
              </span>
            </div>
          </a>
        <?php endif; ?>

        <!-- Grid Berita Reguler -->
        <?php if (!empty($semua_berita)): ?>
          <div class="berita-cards-grid" id="beritaGrid">
            <?php foreach ($semua_berita as $b):
              $gambar_b = (!empty($b['gambar']) && file_exists(__DIR__ . '/uploads/berita/' . $b['gambar']))
                          ? 'uploads/berita/' . htmlspecialchars($b['gambar'])
                          : 'gambar atau poster/header rutan.jpg';
              $link_b = 'detail-berita.php?slug=' . urlencode($b['slug']);
              $cuplikan_b = strip_tags($b['isi']);
              $cuplikan_b = mb_strlen($cuplikan_b) > 100 ? mb_substr($cuplikan_b, 0, 100) . '...' : $cuplikan_b;
            ?>
              <a href="<?= $link_b ?>" class="news-card" data-judul="<?= strtolower(htmlspecialchars($b['judul'])) ?>" data-penulis="<?= strtolower(htmlspecialchars($b['penulis'])) ?>">
                <div class="news-card-img-wrap">
                  <img src="<?= $gambar_b ?>" alt="<?= htmlspecialchars($b['judul']) ?>" class="news-card-img" loading="lazy">
                  <span class="news-card-date"><?= format_tanggal_indo($b['tanggal_dibuat']) ?></span>
                </div>
                <div class="news-card-body">
                  <div class="news-card-author">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?= htmlspecialchars($b['penulis']) ?>
                  </div>
                  <h3 class="news-card-title"><?= htmlspecialchars($b['judul']) ?></h3>
                  <p class="news-card-excerpt"><?= htmlspecialchars($cuplikan_b) ?></p>
                  <div class="news-card-footer">
                    <span class="news-card-read">
                      Baca Artikel
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                      </svg>
                    </span>
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                      Baca Selengkapnya
                    </span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Pesan pencarian kosong -->
        <div id="noResult" style="display:none;text-align:center;padding:50px 20px;color:var(--gray-600);">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 14px;color:var(--gray-200);">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <p style="font-size:1rem;font-weight:600;color:var(--gray-800);">Tidak Ada Hasil</p>
          <p style="font-size:0.88rem;margin-top:4px;">Coba kata kunci yang berbeda.</p>
        </div>

        <!-- Pagination -->
        <?php if ($total_halaman > 1): ?>
          <div class="pagination-wrap">
            <?php if ($halaman > 1): ?>
              <a href="berita.php?halaman=<?= $halaman - 1 ?>" class="page-btn" title="Sebelumnya">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
              </a>
            <?php else: ?>
              <span class="page-btn disabled">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
              </span>
            <?php endif; ?>

            <?php
            $start_pg = max(1, $halaman - 2);
            $end_pg = min($total_halaman, $halaman + 2);
            if ($start_pg > 1): ?>
              <a href="berita.php?halaman=1" class="page-btn">1</a>
              <?php if ($start_pg > 2): ?><span style="padding:0 4px;color:var(--gray-400);">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($pg = $start_pg; $pg <= $end_pg; $pg++): ?>
              <a href="berita.php?halaman=<?= $pg ?>" class="page-btn <?= $pg === $halaman ? 'active' : '' ?>">
                <?= $pg ?>
              </a>
            <?php endfor; ?>

            <?php if ($end_pg < $total_halaman):
              if ($end_pg < $total_halaman - 1): ?><span style="padding:0 4px;color:var(--gray-400);">...</span><?php endif; ?>
              <a href="berita.php?halaman=<?= $total_halaman ?>" class="page-btn"><?= $total_halaman ?></a>
            <?php endif; ?>

            <?php if ($halaman < $total_halaman): ?>
              <a href="berita.php?halaman=<?= $halaman + 1 ?>" class="page-btn" title="Berikutnya">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </a>
            <?php else: ?>
              <span class="page-btn disabled">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </section>

  <!-- ===== FOOTER POLOS ===== -->
  <footer class="footer footer-polos" style="background: var(--primary-dark, #061540); padding: 28px 0; border-top: 1px solid rgba(255,255,255,0.08); text-align: center;">
    <div class="container">
      <p style="margin: 0; font-size: 0.88rem; color: rgba(255,255,255,0.65); letter-spacing: 0.2px;">
        &copy; <?= date('Y') ?> Rumah Tahanan Negara Kelas IV Tanjung &mdash; Kemenimipas RI. Hak Cipta Dilindungi.
      </p>
      <p style="margin: 8px 0 0; font-size: 0.84rem; color: rgba(255,255,255,0.6);">
        Email Resmi: rutan.tanjung@kemenimipas.go.id
      </p>
    </div>
  </footer>

  <script src="script.js"></script>
  <script>
    function filterBerita() {
      const q = document.getElementById('searchBerita').value.toLowerCase().trim();
      const featured = document.getElementById('beritaFeatured');
      const grid = document.getElementById('beritaGrid');
      const noResult = document.getElementById('noResult');

      if (!q) {
        if (featured) featured.style.display = '';
        if (grid) grid.style.display = '';
        if (noResult) noResult.style.display = 'none';
        // Reset semua card
        const cards = document.querySelectorAll('.news-card');
        cards.forEach(c => c.style.display = '');
        return;
      }

      // Sembunyikan featured saat mencari
      if (featured) featured.style.display = 'none';

      let found = 0;
      const cards = document.querySelectorAll('.news-card');
      cards.forEach(card => {
        const judul = card.dataset.judul || '';
        const penulis = card.dataset.penulis || '';
        if (judul.includes(q) || penulis.includes(q)) {
          card.style.display = '';
          found++;
        } else {
          card.style.display = 'none';
        }
      });

      if (grid) grid.style.display = '';
      if (noResult) noResult.style.display = found === 0 ? 'block' : 'none';
    }
  </script>
</body>
</html>
