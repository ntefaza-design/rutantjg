<?php
/**
 * detail-berita.php
 * Halaman Detail Berita & Publikasi Rutan Tanjung Kelas IIB
 */
require_once __DIR__ . '/koneksi.php';

$slug = trim($_GET['slug'] ?? '');
$id   = (int)($_GET['id'] ?? 0);

$berita = null;

if (!empty($slug)) {
    $stmt = $conn->prepare("SELECT * FROM berita WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $berita = $res->fetch_assoc();
    }
    $stmt->close();
}

if (!$berita && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM berita WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $berita = $res->fetch_assoc();
    }
    $stmt->close();
}

// Ambil berita terbaru lainnya untuk sidebar
$berita_lainnya = [];
if ($berita) {
    $stmt_lain = $conn->prepare("SELECT id, judul, slug, gambar, tanggal_dibuat, penulis FROM berita WHERE id != ? ORDER BY tanggal_dibuat DESC LIMIT 4");
    $stmt_lain->bind_param("i", $berita['id']);
    $stmt_lain->execute();
    $res_lain = $stmt_lain->get_result();
    while ($r = $res_lain->fetch_assoc()) {
        $berita_lainnya[] = $r;
    }
    $stmt_lain->close();
}

// URL halaman saat ini untuk share
$current_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$current_url = $current_protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $berita ? htmlspecialchars($berita['judul']) . ' — Rutan Tanjung Kelas IIB' : 'Berita Tidak Ditemukan — Rutan Tanjung Kelas IIB' ?></title>
  <meta name="description" content="<?= $berita ? htmlspecialchars(mb_substr(strip_tags($berita['isi']), 0, 160)) : 'Detail berita Rutan Tanjung Kelas IIB' ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>" />
  <link rel="icon" type="image/png" href="banner/LOGO RUTAN png.png" />
  <style>
    /* Styling Khusus Halaman Detail Berita */
    .article-page {
      padding-top: 100px;
      padding-bottom: 80px;
      background: var(--off-white);
      min-height: 80vh;
    }

    .breadcrumb-nav {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.88rem;
      color: var(--gray-600);
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .breadcrumb-nav a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .breadcrumb-nav a:hover {
      color: var(--gold-dark);
      text-decoration: underline;
    }

    .breadcrumb-nav .sep {
      color: var(--gray-400);
    }

    .article-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 36px;
      align-items: start;
    }

    @media (max-width: 1024px) {
      .article-layout {
        grid-template-columns: 1fr;
        gap: 32px;
      }
    }

    .article-card-main {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 36px;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--gray-100);
    }

    @media (max-width: 600px) {
      .article-page {
        padding-top: 90px;
        padding-bottom: 48px;
      }
      .article-card-main {
        padding: 20px 16px;
        border-radius: 14px;
      }
    }

    .article-tag {
      display: inline-block;
      background: rgba(201, 168, 76, 0.15);
      color: var(--gold-dark);
      font-weight: 700;
      font-size: 0.8rem;
      padding: 4px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .article-title {
      font-family: var(--font-body);
      font-size: clamp(1.5rem, 3.8vw, 2.1rem);
      font-weight: 800;
      line-height: 1.35;
      color: var(--primary-dark);
      margin-bottom: 16px;
    }

    @media (max-width: 600px) {
      .article-title {
        font-size: 1.4rem;
        line-height: 1.35;
      }
    }

    .article-meta-bar {
      display: flex;
      align-items: center;
      gap: 20px;
      padding-bottom: 20px;
      margin-bottom: 24px;
      border-bottom: 1px solid var(--gray-100);
      font-size: 0.88rem;
      color: var(--gray-600);
      flex-wrap: wrap;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .meta-item svg {
      color: var(--gold);
    }

    .meta-item strong {
      color: var(--primary);
    }

    .article-featured-img-wrap {
      width: 100%;
      border-radius: var(--radius-md);
      overflow: hidden;
      margin-bottom: 28px;
      box-shadow: var(--shadow-md);
      background: var(--gray-50);
    }

    .article-featured-img {
      width: 100%;
      max-height: 500px;
      object-fit: cover;
      display: block;
    }

    .article-content {
      font-size: 1.05rem;
      line-height: 1.85;
      color: #2d3748;
      word-break: break-word;
    }

    .article-content p {
      margin-bottom: 20px;
    }

    .article-content img {
      max-width: 100%;
      height: auto;
      border-radius: var(--radius-md);
      margin: 16px 0;
    }

    .article-content iframe {
      max-width: 100%;
      border-radius: var(--radius-sm);
    }

    .article-content table {
      width: 100%;
      display: block;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      margin: 16px 0;
    }

    .article-share-box {
      margin-top: 36px;
      padding-top: 24px;
      border-top: 1px solid var(--gray-100);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .share-label {
      font-weight: 700;
      color: var(--primary-dark);
      font-size: 0.92rem;
    }

    .share-buttons {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .btn-share {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
      border: none;
    }

    .btn-share-wa {
      background: #25d366;
      color: #ffffff;
    }
    .btn-share-wa:hover {
      background: #1ebc59;
      transform: translateY(-2px);
    }

    .btn-share-fb {
      background: #1877f2;
      color: #ffffff;
    }
    .btn-share-fb:hover {
      background: #166fe5;
      transform: translateY(-2px);
    }

    .btn-share-copy {
      background: var(--gray-100);
      color: var(--gray-800);
      border: 1px solid var(--gray-200);
    }
    .btn-share-copy:hover {
      background: var(--gray-200);
    }

    .btn-back-home {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 24px;
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      font-size: 0.95rem;
      transition: color 0.2s;
    }
    .btn-back-home:hover {
      color: var(--gold-dark);
      text-decoration: underline;
    }

    /* Sidebar Widgets */
    .sidebar-widget {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 24px;
      border: 1px solid var(--gray-100);
      box-shadow: var(--shadow-sm);
      margin-bottom: 24px;
    }

    .widget-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--primary-dark);
      margin-bottom: 18px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--gold);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .recent-news-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .recent-news-item {
      display: flex;
      gap: 14px;
      align-items: center;
      text-decoration: none;
      color: inherit;
      transition: transform 0.2s;
    }

    .recent-news-item:hover {
      transform: translateX(4px);
    }

    .recent-news-thumb {
      width: 70px;
      height: 60px;
      border-radius: 8px;
      object-fit: cover;
      flex-shrink: 0;
      background: var(--gray-100);
    }

    .recent-news-info {
      flex: 1;
    }

    .recent-news-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--primary-dark);
      line-height: 1.35;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      margin-bottom: 4px;
      transition: color 0.2s;
    }

    .recent-news-item:hover .recent-news-title {
      color: var(--gold-dark);
    }

    .recent-news-date {
      font-size: 0.75rem;
      color: var(--gray-400);
    }

    .cta-card-sidebar {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: var(--white);
      border-radius: var(--radius-md);
      padding: 20px;
      text-align: center;
    }
    .cta-card-sidebar h4 {
      font-size: 1.05rem;
      color: var(--gold-light);
      margin-bottom: 8px;
    }
    .cta-card-sidebar p {
      font-size: 0.82rem;
      color: var(--gray-200);
      margin-bottom: 14px;
      line-height: 1.5;
    }
    .btn-wa-sidebar {
      background: #25d366;
      color: #fff;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-wa-sidebar:hover {
      background: #1ebc59;
      transform: scale(1.03);
    }

    /* Toast Notification */
    #copyToast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: var(--primary-dark);
      color: var(--white);
      border: 1px solid var(--gold);
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.3);
      font-size: 0.88rem;
      display: none;
      z-index: 10000;
      animation: fadeIn 0.3s ease;
    }
  </style>
</head>
<body>

  <!-- ===== NAVBAR ===== -->
  <header class="navbar" id="navbar">
    <div class="container navbar-inner">
      <a href="index.php#hero" class="logo-wrap">
        <div class="logo-emblem">
          <img src="banner/LOGO RUTAN png.png" alt="Logo Rutan Tanjung" width="62" height="62" style="object-fit: contain; display: block;" />
        </div>
        <div class="logo-text">
          <span class="logo-title">RUTAN TANJUNG</span>
          <span class="logo-sub">Kelas IIB</span>
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
        <a href="berita.php" class="btn-nav-berita-cta" title="Buka Halaman Berita &amp; Publikasi">
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
          <span class="nav-berita-live-dot" title="Informasi Terkini"></span>
        </a>
      </nav>
      <button class="hamburger" id="hamburger" aria-label="Toggle Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <main class="article-page">
    <div class="container">

      <!-- Breadcrumbs -->
      <nav class="breadcrumb-nav">
        <a href="index.php">Beranda</a>
        <span class="sep">/</span>
        <a href="index.php#berita">Berita &amp; Publikasi</a>
        <span class="sep">/</span>
        <span><?= $berita ? htmlspecialchars(mb_substr($berita['judul'], 0, 45)) . '...' : 'Tidak Ditemukan' ?></span>
      </nav>

      <?php if ($berita): ?>
        <?php
          $gambar_detail = (!empty($berita['gambar']) && file_exists(__DIR__ . '/uploads/berita/' . $berita['gambar']))
                            ? 'uploads/berita/' . htmlspecialchars($berita['gambar'])
                            : 'gambar atau poster/header rutan.jpg';
        ?>
        <div class="article-layout">
          <!-- Kolom Konten Utama -->
          <article class="article-card-main">
            <span class="article-tag">Kabar Pemasyarakatan</span>
            <h1 class="article-title"><?= htmlspecialchars($berita['judul']) ?></h1>

            <div class="article-meta-bar">
              <div class="meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Penulis: <strong><?= htmlspecialchars($berita['penulis']) ?></strong></span>
              </div>
              <div class="meta-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Diterbitkan: <strong><?= format_tanggal_indo($berita['tanggal_dibuat']) ?></strong></span>
              </div>
            </div>

            <div class="article-featured-img-wrap">
              <img src="<?= $gambar_detail ?>" alt="<?= htmlspecialchars($berita['judul']) ?>" class="article-featured-img">
            </div>

            <div class="article-content">
              <?php
                // Fungsi auto-link: konversi URL plain text menjadi <a> yang bisa diklik
                function auto_link_text($text) {
                    // Escape HTML terlebih dahulu untuk keamanan
                    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                    // Konversi URL http/https menjadi link
                    $pattern = '/(https?:\/\/[^\s\,\;\"\'\<\>\(\)\[\]]+)/i';
                    $replacement = '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:var(--primary,#1e3a6e);text-decoration:underline;word-break:break-all;">$1</a>';
                    return preg_replace($pattern, $replacement, $text);
                }
                // Tampilkan paragraf dengan format rapi + link aktif
                $paragraphs = explode("\n", str_replace(["\r\n", "\r"], "\n", $berita['isi']));
                foreach ($paragraphs as $para) {
                    $para = trim($para);
                    if (!empty($para)) {
                        echo '<p>' . nl2br(auto_link_text($para)) . '</p>';
                    }
                }
              ?>
            </div>

            <!-- Share Buttons -->
            <div class="article-share-box">
              <span class="share-label">Bagikan Berita Ini:</span>
              <div class="share-buttons">
                <a href="https://api.whatsapp.com/send?text=<?= urlencode($berita['judul'] . ' - Baca selengkapnya di: ' . $current_url) ?>" target="_blank" rel="noopener" class="btn-share btn-share-wa">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.824 11.824 0 0 0 12.05 0zm0 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884z"/>
                  </svg>
                  WhatsApp
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>" target="_blank" rel="noopener" class="btn-share btn-share-fb">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                  </svg>
                  Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?= urlencode($berita['judul'] . ' - Baca selengkapnya di: ' . $current_url) ?>" target="_blank" rel="noopener" class="btn-share btn-share-x" style="background:#0f1419;color:#ffffff;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                  </svg>
                  X (Twitter)
                </a>
                <button type="button" class="btn-share btn-share-copy" onclick="copyArticleUrl()">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                  </svg>
                  Salin Link
                </button>
              </div>
            </div>

            <a href="index.php#berita" class="btn-back-home">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              Kembali ke Daftar Berita
            </a>
          </article>

          <!-- Sidebar Berita Terkait & Info -->
          <aside class="article-sidebar">
            <?php if (!empty($berita_lainnya)): ?>
              <div class="sidebar-widget">
                <h3 class="widget-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1"></path>
                    <path d="M19 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-5"></path>
                  </svg>
                  Berita Terbaru Lainnya
                </h3>
                <div class="recent-news-list">
                  <?php foreach ($berita_lainnya as $bl): 
                    $thumb_bl = (!empty($bl['gambar']) && file_exists(__DIR__ . '/uploads/berita/' . $bl['gambar']))
                                ? 'uploads/berita/' . htmlspecialchars($bl['gambar'])
                                : 'gambar atau poster/header rutan.jpg';
                  ?>
                    <a href="detail-berita.php?slug=<?= urlencode($bl['slug']) ?>" class="recent-news-item">
                      <img src="<?= $thumb_bl ?>" alt="<?= htmlspecialchars($bl['judul']) ?>" class="recent-news-thumb">
                      <div class="recent-news-info">
                        <h4 class="recent-news-title"><?= htmlspecialchars($bl['judul']) ?></h4>
                        <span class="recent-news-date"><?= format_tanggal_indo($bl['tanggal_dibuat']) ?></span>
                      </div>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="sidebar-widget cta-card-sidebar">
              <h4>Layanan Pengaduan Resmi</h4>
              <p>Melihat adanya indikasi gratifikasi atau ketidaksesuaian pelayanan? Laporkan langsung ke nomor resmi kami.</p>
              <a href="https://wa.me/6282155353594?text=Halo%20Admin%20Rutan%20Tanjung,%20saya%20ingin%20menyampaikan%20pengaduan" target="_blank" rel="noopener" class="btn-wa-sidebar">
                📲 WhatsApp Pengaduan
              </a>
            </div>
          </aside>
        </div>

      <?php else: ?>
        <!-- State jika berita tidak ditemukan -->
        <div class="article-card-main" style="text-align:center;padding:60px 20px;">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--gray-400);margin:0 auto 16px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <h2 style="font-size:1.6rem;color:var(--primary-dark);margin-bottom:8px;">Berita Tidak Ditemukan</h2>
          <p style="color:var(--gray-600);margin-bottom:24px;">Maaf, artikel atau publikasi yang Anda cari tidak tersedia atau telah dipindahkan.</p>
          <a href="index.php" class="btn-primary" style="display:inline-flex;">Kembali ke Beranda</a>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <div id="copyToast">Tautan berhasil disalin ke clipboard!</div>

  <script src="script.js"></script>
  <script>
    function copyArticleUrl() {
      navigator.clipboard.writeText(window.location.href).then(() => {
        const toast = document.getElementById('copyToast');
        toast.style.display = 'block';
        setTimeout(() => {
          toast.style.display = 'none';
        }, 2500);
      }).catch(err => {
        alert('Gagal menyalin tautan: ' + err);
      });
    }
  </script>
</body>
</html>
