<?php
require_once __DIR__ . '/koneksi.php';

// Ambil 1 berita terbaru untuk headline banner di layar tengah hero
$headline_q = $conn->query("SELECT id, judul, slug, tanggal_dibuat FROM berita ORDER BY tanggal_dibuat DESC, id DESC LIMIT 1");
$headline = ($headline_q && $headline_q->num_rows > 0) ? $headline_q->fetch_assoc() : null;

// Ambil 3 berita terbaru untuk section Sorotan di homepage
$sorotan_q = $conn->query("SELECT id, judul, slug, gambar, penulis, tanggal_dibuat FROM berita ORDER BY tanggal_dibuat DESC, id DESC LIMIT 3");
$sorotan_rows = ($sorotan_q && $sorotan_q->num_rows > 0) ? $sorotan_q->fetch_all(MYSQLI_ASSOC) : [];

// Ambil jadwal pelayanan dari database
$stmt_jk = $conn->prepare("SELECT * FROM jadwal_pelayanan WHERE kategori = 'kunjungan' ORDER BY urutan ASC, id ASC");
$stmt_jk->execute();
$jadwal_kunjungan_rows = $stmt_jk->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_jk->close();

$stmt_jp = $conn->prepare("SELECT * FROM jadwal_pelayanan WHERE kategori = 'penitipan' ORDER BY urutan ASC, id ASC");
$stmt_jp->execute();
$jadwal_penitipan_rows = $stmt_jp->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_jp->close();

$catatan_penitipan_txt = 'Barang/makanan wajib sesuai ketentuan yang berlaku. Lihat poster syarat di bawah.';
$catatan_kunjungan_txt = 'Pengunjung wajib mematuhi ketentuan yang berlaku. Lihat poster syarat di bawah.';

$stmt_cp = $conn->prepare("SELECT kunci, nilai FROM pengaturan_jadwal WHERE kunci IN ('catatan_penitipan', 'catatan_kunjungan')");
$stmt_cp->execute();
$res_cp = $stmt_cp->get_result();
while ($row_c = $res_cp->fetch_assoc()) {
    if ($row_c['kunci'] === 'catatan_penitipan' && !empty($row_c['nilai'])) {
        $catatan_penitipan_txt = $row_c['nilai'];
    } elseif ($row_c['kunci'] === 'catatan_kunjungan' && !empty($row_c['nilai'])) {
        $catatan_kunjungan_txt = $row_c['nilai'];
    }
}
$stmt_cp->close();

// Ambil daftar pop-up ads yang aktif
$stmt_pop = $conn->prepare("SELECT * FROM popup_ads WHERE is_aktif = 1 ORDER BY urutan ASC, id ASC");
$stmt_pop->execute();
$popup_ads_rows = $stmt_pop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pop->close();

// Ambil daftar pejabat struktural yang aktif
$pejabat_kepala_rows = [];
$pejabat_grid_rows = [];
if ($chk_pj_tbl = $conn->query("SHOW TABLES LIKE 'pejabat_struktural'")) {
    if ($chk_pj_tbl->num_rows > 0) {
        $stmt_pj = $conn->prepare("SELECT * FROM pejabat_struktural WHERE is_aktif = 1 ORDER BY is_kepala DESC, urutan ASC, id ASC");
        $stmt_pj->execute();
        $all_pejabat = $stmt_pj->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_pj->close();
        foreach ($all_pejabat as $pj_item) {
            if ((int)$pj_item['is_kepala'] === 1) {
                $pejabat_kepala_rows[] = $pj_item;
            } else {
                $pejabat_grid_rows[] = $pj_item;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rumah Tahanan Negara Tanjung Kelas IIB — Kemenimipas RI</title>
  <meta name="description"
    content="Website resmi Rumah Tahanan Negara Tanjung Kelas IIB. Layanan informasi, pengaduan, jadwal kunjungan, dan kontak resmi Rutan Tanjung." />
  <meta name="keywords" content="Rutan Tanjung, Rumah Tahanan Tanjung, Kelas IIB, Kemenimipas, Ditjenpas, Lapas" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>" />
  <link rel="icon" type="image/png" href="banner/LOGO RUTAN png.png" />
</head>

<body>

  <!-- ===== NAVBAR ===== -->
  <header class="navbar" id="navbar">
    <div class="container navbar-inner">
      <a href="#hero" class="logo-wrap">
        <div class="logo-emblem">
          <img src="banner/LOGO RUTAN png.png" alt="Logo Rutan Tanjung" width="62" height="62"
            style="object-fit: contain; display: block;" />
        </div>
        <div class="logo-text">
          <span class="logo-title">RUTAN TANJUNG</span>
          <span class="logo-sub">Kelas IIB</span>
        </div>
      </a>
      <nav class="nav-links" id="navLinks">
        <a href="#tentang" class="nav-link">Tentang</a>
        <a href="#visi-misi" class="nav-link">Visi &amp; Misi</a>
        <a href="#pejabat" class="nav-link">Pejabat Struktural</a>
        <a href="#jadwal" class="nav-link">Jadwal</a>
        <a href="#pengaduan" class="nav-link">Pengaduan</a>
        <a href="#sosmed" class="nav-link">Sosial Media Kami</a>
        <a href="#kontak" class="nav-link">Kontak &amp; Lokasi</a>
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

  <!-- ===== HERO ===== -->
  <section class="hero" id="hero">
    <div class="hero-bg-overlay"></div>
    <div class="hero-particles" id="particles"></div>
    <div class="container hero-content">
      <h1 class="hero-title">Rumah Tahanan Negara<br /><span class="hero-title-accent">Tanjung Kelas IIB</span></h1>
      <p class="hero-desc">Rutan Tanjung bekerja, melayani dengan <span
          style="color: var(--gold); font-weight: 700;">PASTI !!!</span></p>
      <div class="hero-actions">
        <a href="#jadwal" class="btn-primary">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          Jadwal Kunjungan
        </a>
        <a href="#pengaduan" class="btn-ghost">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          Pengaduan Online
        </a>
      </div>


    </div>

  </section>

  <!-- ===== SOROTAN BERITA ===== -->
  <?php if (!empty($sorotan_rows)): ?>
  <section class="section sorotan-section" id="sorotan">
    <div class="container">
      <div class="sorotan-header">
        <h2 class="sorotan-title">Sorotan Berita</h2>
        <a href="berita.php" class="sorotan-lihat-semua">Lihat Semua →</a>
      </div>
      <div class="sorotan-grid">
        <?php foreach ($sorotan_rows as $idx => $sb):
          $sb_gambar = (!empty($sb['gambar']) && file_exists(__DIR__ . '/uploads/berita/' . $sb['gambar']))
                       ? 'uploads/berita/' . htmlspecialchars($sb['gambar'])
                       : 'gambar atau poster/header rutan.jpg';
          $sb_judul  = htmlspecialchars($sb['judul']);
          $sb_slug   = htmlspecialchars($sb['slug']);
          $sb_penulis = htmlspecialchars($sb['penulis'] ?? 'Humas');
        ?>
        <a href="detail-berita.php?slug=<?= $sb_slug ?>" class="sorotan-item" title="<?= $sb_judul ?>">
          <div class="sorotan-thumb-wrap">
            <img src="<?= $sb_gambar ?>" alt="<?= $sb_judul ?>" class="sorotan-thumb" loading="<?= $idx < 3 ? 'eager' : 'lazy' ?>">
          </div>
          <h3 class="sorotan-item-judul"><?= $sb_judul ?></h3>
          <span class="sorotan-item-kategori"><?= $sb_penulis ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===== TENTANG KAMI ===== -->
  <section class="section" id="tentang">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Profil Instansi</span>
        <h2 class="section-title">Tentang Kami</h2>
        <p class="section-desc">Mengenal lebih dekat Rumah Tahanan Negara Tanjung Kelas IIB</p>
      </div>
      <div class="about-grid">
        <div class="about-visual">
          <div class="about-emblem-large">
            <img src="banner/LOGO RUTAN png.png" alt="Logo Rutan Tanjung"
              style="width: 100%; height: 100%; object-fit: contain; display: block; margin: 0 auto;" />
          </div>
          <div class="about-badge-row">
            <div class="info-badge"><span>📍</span> Tanjung, Kalimantan Selatan</div>
          </div>
        </div>
        <div class="about-content">
          <h3 class="about-subtitle">Profil Singkat</h3>
          <p class="about-text">
            Rumah Tahanan Negara (Rutan) Kelas IIB Tanjung merupakan Unit Pelaksana Teknis (UPT) di bidang pemasyarakatan yang berada di bawah naungan Direktorat Jenderal Pemasyarakatan, Kementerian Imigrasi dan Pemasyarakatan Republik Indonesia.
          </p>
          <p class="about-text">
            Berlokasi di Kabupaten Tabalong, Provinsi Kalimantan Selatan, Rutan Kelas IIB Tanjung memiliki tugas dan tanggung jawab dalam melaksanakan pelayanan terhadap tahanan, meliputi pelayanan, perawatan, pengamanan, serta pemenuhan hak-hak tahanan sesuai dengan ketentuan peraturan perundang-undangan yang berlaku.
          </p>
          <p class="about-text">
            Dalam pelaksanaan tugas dan fungsinya, Rutan Kelas IIB Tanjung senantiasa berkomitmen untuk mewujudkan penyelenggaraan pemasyarakatan yang profesional, berintegritas, humanis, dan akuntabel. Komitmen tersebut diwujudkan melalui peningkatan kualitas pelayanan publik, penguatan keamanan dan ketertiban, serta penghormatan terhadap hak asasi manusia.
          </p>
          <p class="about-text">
            Dengan mengedepankan nilai-nilai keadilan, transparansi, akuntabilitas, dan kepastian hukum, Rutan Kelas IIB Tanjung terus berupaya memberikan pelayanan terbaik serta mendukung terwujudnya sistem pemasyarakatan yang semakin maju, berorientasi pada kemanusiaan, dan memberikan manfaat bagi masyarakat.
          </p>
          <div class="about-features">
            <div class="feature-item">
              <div class="feature-icon">⚖️</div>
              <div class="feature-text">
                <strong>Berkeadilan</strong>
                <span>Pelayanan yang adil bagi semua pihak</span>
              </div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">🛡️</div>
              <div class="feature-text">
                <strong>Aman &amp; Terpercaya</strong>
                <span>Keamanan dan ketertiban selalu terjaga</span>
              </div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">📋</div>
              <div class="feature-text">
                <strong>Transparan</strong>
                <span>Informasi terbuka dan akuntabel</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== VISI MISI ===== -->
  <section class="section section-dark" id="visi-misi">
    <div class="vm-bg-pattern"></div>
    <div class="container">
      <div class="section-header light">
        <span class="section-tag gold">Arah &amp; Strategi</span>
        <h2 class="section-title">Visi, Misi &amp; Tujuan</h2>
        <p class="section-desc">Visi, Misi dan Tujuan Kementerian Imigrasi dan Pemasyarakatan RI</p>
      </div>
      <div class="vm-grid">
        <div class="vm-card visi-card">
          <div class="vm-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </div>
          <div style="margin-bottom: 16px;">
            <span class="vm-label" style="display: block; margin-bottom: 4px;">VISI 2025 - 2029</span>
            <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px;">
              Kementerian Imigrasi dan Pemasyarakatan
            </div>
          </div>
          <p class="vm-text">
            &ldquo;Terwujudnya Penegakan Hukum dan Pelayanan Keimigrasian dan Pemasyarakatan untuk Stabilitas Keamanan yang Tangguh menuju Indonesia Emas 2045&rdquo;.
          </p>
          <div class="vm-deco"></div>
        </div>
        <div class="vm-divider">
          <div class="vm-divider-line"></div>
          <div class="vm-divider-icon">⚖️</div>
          <div class="vm-divider-line"></div>
        </div>
        <div class="vm-card misi-card">
          <div class="vm-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36">
              <polyline points="9 11 12 14 22 4" />
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
          </div>
          <div style="margin-bottom: 16px;">
            <span class="vm-label" style="display: block; margin-bottom: 4px;">MISI &amp; TUJUAN</span>
            <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px;">
              Kementerian Imigrasi dan Pemasyarakatan
            </div>
          </div>
          <ol class="misi-list">
            <li>
              <div style="flex: 1; min-width: 0;">
                <div style="margin-bottom: 10px;">
                  <span style="display: inline-block; font-size: 0.78rem; font-weight: 700; color: var(--gold-light); background: rgba(201,168,76,0.15); border: 1px solid rgba(201,168,76,0.3); border-radius: 4px; padding: 2px 8px; margin-bottom: 6px; letter-spacing: 0.5px;">MISI 1</span>
                  <div style="color: rgba(255,255,255,0.92); line-height: 1.6; font-size: 0.95rem;">Mewujudkan Penegakan Hukum dan pelayanan serta jaminan pelindungan Imigrasi dan Pemasyarakatan yang transparan dan berkeadilan.</div>
                </div>
                <div style="padding-top: 10px; border-top: 1px dashed rgba(255,255,255,0.12);">
                  <span style="display: inline-block; font-size: 0.75rem; font-weight: 700; color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); border-radius: 4px; padding: 2px 8px; margin-bottom: 6px; letter-spacing: 0.5px;">TUJUAN 1</span>
                  <div style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.9rem;">Menciptakan penegakan dan pelayanan hukum untuk mendukung kedaulatan negara serta reintegrasi sosial secara transparan dan berkeadilan.</div>
                </div>
              </div>
            </li>
            <li>
              <div style="flex: 1; min-width: 0;">
                <div style="margin-bottom: 10px;">
                  <span style="display: inline-block; font-size: 0.78rem; font-weight: 700; color: var(--gold-light); background: rgba(201,168,76,0.15); border: 1px solid rgba(201,168,76,0.3); border-radius: 4px; padding: 2px 8px; margin-bottom: 6px; letter-spacing: 0.5px;">MISI 2</span>
                  <div style="color: rgba(255,255,255,0.92); line-height: 1.6; font-size: 0.95rem;">Meningkatkan kapasitas kelembagaan Imigrasi dan Pemasyarakatan yang modern, profesional, dan berintegritas.</div>
                </div>
                <div style="padding-top: 10px; border-top: 1px dashed rgba(255,255,255,0.12);">
                  <span style="display: inline-block; font-size: 0.75rem; font-weight: 700; color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); border-radius: 4px; padding: 2px 8px; margin-bottom: 6px; letter-spacing: 0.5px;">TUJUAN 2</span>
                  <div style="color: rgba(255,255,255,0.8); line-height: 1.6; font-size: 0.9rem;">Menciptakan sistem keimigrasian dan pemasyarakatan yang modern, terintegrasi dan akuntabel melalui peningkatan kompetensi dan profesionalisme sumber daya manusia yang berintegritas, responsif dan adaptif di bidang Imigrasi dan Pemasyarakatan.</div>
                </div>
              </div>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== PEJABAT STRUKTURAL ===== -->
  <section class="section" id="pejabat">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Kepemimpinan</span>
        <h2 class="section-title">Pejabat Struktural</h2>
        <p class="section-desc">Pejabat struktural yang memimpin dan mengelola Rutan Tanjung Kelas IIB</p>
      </div>

      <?php foreach ($pejabat_kepala_rows as $pk):
        $pk_has_foto = !empty($pk['foto']) && file_exists(__DIR__ . '/' . $pk['foto']);
        $pk_src = $pk_has_foto ? htmlspecialchars($pk['foto']) . '?v=' . filemtime(__DIR__ . '/' . $pk['foto']) : '';
      ?>
      <!-- Kepala Rutan (featured) -->
      <div class="pejabat-ketua-wrap">
        <div class="pejabat-ketua-card">
          <div class="pejabat-ketua-avatar">
            <div class="avatar-ring"></div>
            <div class="avatar-inner">
              <?php if ($pk_has_foto): ?>
                <img src="<?= $pk_src ?>" alt="<?= htmlspecialchars($pk['nama']) ?>"
                  onclick="openLightbox(this.src, <?= htmlspecialchars(json_encode($pk['nama'] . ' - ' . $pk['jabatan']), ENT_QUOTES, 'UTF-8') ?>)"
                  style="width:190px;height:190px;object-fit:cover;border-radius:50%;cursor:zoom-in;transition:transform .2s;"
                  onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" />
              <?php else: ?>
                <div style="width:190px;height:190px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, var(--primary-dark), var(--primary-light));color:var(--gold);">
                  <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="pejabat-ketua-info">
            <span class="jabatan-tag ketua-tag"><?= htmlspecialchars($pk['jabatan']) ?></span>
            <h3 class="pejabat-name"><?= htmlspecialchars($pk['nama']) ?></h3>
            <?php if (!empty($pk['deskripsi'])): ?>
              <p class="pejabat-desc"><?= nl2br(htmlspecialchars($pk['deskripsi'])) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Pejabat Struktural Grid -->
      <?php if (!empty($pejabat_grid_rows)): ?>
      <div class="pejabat-grid">
        <?php foreach ($pejabat_grid_rows as $idx_pg => $pg):
          $pg_has_foto = !empty($pg['foto']) && file_exists(__DIR__ . '/' . $pg['foto']);
          $pg_src = $pg_has_foto ? htmlspecialchars($pg['foto']) . '?v=' . filemtime(__DIR__ . '/' . $pg['foto']) : '';
        ?>
        <div class="pejabat-card" id="pejabat-<?= (int)$pg['id'] ?>">
          <div class="pejabat-avatar">
            <div class="avatar-bg">
              <?php if ($pg_has_foto): ?>
                <img src="<?= $pg_src ?>" alt="<?= htmlspecialchars($pg['nama']) ?>"
                  onclick="openLightbox(this.src, <?= htmlspecialchars(json_encode($pg['nama'] . ' - ' . $pg['jabatan']), ENT_QUOTES, 'UTF-8') ?>)"
                  style="width:150px;height:150px;object-fit:cover;border-radius:50%;cursor:zoom-in;transition:transform .2s;"
                  onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" />
              <?php else: ?>
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.6">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              <?php endif; ?>
            </div>
          </div>
          <div class="pejabat-info">
            <span class="jabatan-tag"><?= htmlspecialchars($pg['jabatan']) ?></span>
            <h4 class="pejabat-name-sm"><?= htmlspecialchars($pg['nama']) ?></h4>
            <?php if (!empty($pg['deskripsi'])): ?>
              <p class="pejabat-desc-sm"><?= nl2br(htmlspecialchars($pg['deskripsi'])) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== JADWAL PELAYANAN ===== -->
  <section class="section section-alt" id="jadwal">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Informasi Penting</span>
        <h2 class="section-title">Jadwal Pelayanan</h2>
        <p class="section-desc">Waktu dan ketentuan pelayanan di Rutan Tanjung Kelas IIB</p>
      </div>
      <div class="jadwal-grid">
        <!-- Jadwal Kunjungan -->
        <div class="jadwal-card" id="jadwal-kunjungan">
          <div class="jadwal-header">
            <div class="jadwal-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <div style="flex:1;">
              <h3 class="jadwal-title">Jadwal Kunjungan</h3>
              <div id="status-kunjungan" class="service-status-badge"></div>
            </div>
          </div>
          <div class="jadwal-body">
            <?php if (!empty($jadwal_kunjungan_rows)): ?>
              <?php foreach ($jadwal_kunjungan_rows as $row): 
                $is_closed = (int)$row['is_tutup'] === 1;
                $hari_lower = strtolower($row['hari']);
                $badge_class = 'senin-jumat';
                if ($is_closed || strpos($hari_lower, 'minggu') !== false || strpos($hari_lower, 'libur') !== false) {
                  $badge_class = 'libur';
                } elseif (strpos($hari_lower, 'sabtu') !== false || strpos($hari_lower, 'jum') !== false) {
                  $badge_class = 'sabtu';
                }
              ?>
                <div class="schedule-row<?= $is_closed ? ' closed' : '' ?>">
                  <span class="day-badge <?= $badge_class ?>"><?= htmlspecialchars($row['hari']) ?></span>
                  <div class="time-info">
                    <span class="time-main"><?= htmlspecialchars($row['waktu']) ?></span>
                    <?php if (!empty($row['keterangan'])): ?>
                      <span class="time-note"><?= htmlspecialchars($row['keterangan']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="schedule-row"><span class="day-badge libur">Info</span><div class="time-info"><span class="time-main">Belum ada jadwal</span></div></div>
            <?php endif; ?>
            <div class="jadwal-note">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="13" />
                <circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="currentColor" stroke-width="1" />
              </svg>
              <?= htmlspecialchars($catatan_kunjungan_txt) ?>
            </div>
          </div>
        </div>

        <!-- Jadwal Penitipan Barang/Makanan -->
        <div class="jadwal-card" id="jadwal-penitipan">
          <div class="jadwal-header">
            <div class="jadwal-icon" style="background: linear-gradient(135deg, #1a5c2a, #2d8a44);">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 0 1-8 0" />
              </svg>
            </div>
            <div style="flex:1;">
              <h3 class="jadwal-title">Penitipan Barang / Makanan</h3>
              <div id="status-penitipan" class="service-status-badge"></div>
            </div>
          </div>
          <div class="jadwal-body">
            <?php if (!empty($jadwal_penitipan_rows)): ?>
              <?php foreach ($jadwal_penitipan_rows as $row): 
                $is_closed = (int)$row['is_tutup'] === 1;
                $hari_lower = strtolower($row['hari']);
                $badge_class = 'senin-jumat';
                if ($is_closed || strpos($hari_lower, 'minggu') !== false || strpos($hari_lower, 'libur') !== false) {
                  $badge_class = 'libur';
                } elseif (strpos($hari_lower, 'sabtu') !== false || strpos($hari_lower, 'jum') !== false) {
                  $badge_class = 'sabtu';
                }
              ?>
                <div class="schedule-row<?= $is_closed ? ' closed' : '' ?>">
                  <span class="day-badge <?= $badge_class ?>"><?= htmlspecialchars($row['hari']) ?></span>
                  <div class="time-info">
                    <span class="time-main"><?= htmlspecialchars($row['waktu']) ?></span>
                    <?php if (!empty($row['keterangan'])): ?>
                      <span class="time-note"><?= htmlspecialchars($row['keterangan']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="schedule-row"><span class="day-badge libur">Info</span><div class="time-info"><span class="time-main">Belum ada jadwal</span></div></div>
            <?php endif; ?>
            <div class="jadwal-note">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="13" />
                <circle cx="12" cy="17" r="0.5" fill="currentColor" stroke="currentColor" stroke-width="1" />
              </svg>
              <?= htmlspecialchars($catatan_penitipan_txt) ?>
            </div>
          </div>
        </div>

        <!-- Poster Syarat Kunjungan -->
        <div class="jadwal-card jadwal-poster-card" id="jadwal-syarat-kunjungan"
          onclick="openLightbox('gambar atau poster/Syarat dan ketentuan pengunjung.jpg','Syarat &amp; Ketentuan Pengunjung')"
          style="cursor:zoom-in;">
          <div class="jadwal-header">
            <div class="jadwal-icon" style="background: linear-gradient(135deg, #0a2463, #1e3a8a);">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
            </div>
            <h3 class="jadwal-title">Syarat &amp; Ketentuan Pengunjung</h3>
          </div>
          <div class="jadwal-body"
            style="padding: 16px; background: #f8fafc; display: flex; flex-direction: column; align-items: center; border-radius: 0 0 16px 16px;">
            <img src="gambar atau poster/Syarat dan ketentuan pengunjung.jpg"
              alt="Syarat dan Ketentuan Pengunjung"
              style="width: 100%; max-height: 520px; object-fit: contain; display: block; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);" />
          </div>
        </div>

        <!-- Poster Syarat Penitipan -->
        <div class="jadwal-card jadwal-poster-card" id="jadwal-syarat-penitipan"
          onclick="openLightbox('gambar atau poster/Syarat dan ketentuan penitipan barang_makanan.png','Syarat &amp; Ketentuan Penitipan Barang/Makanan')"
          style="cursor:zoom-in;">
          <div class="jadwal-header">
            <div class="jadwal-icon" style="background: linear-gradient(135deg, #7c3a00, #c05a00);">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
            </div>
            <h3 class="jadwal-title">Syarat &amp; Ketentuan Penitipan</h3>
          </div>
          <div class="jadwal-body"
            style="padding: 16px; background: #f8fafc; display: flex; flex-direction: column; align-items: center; border-radius: 0 0 16px 16px;">
            <img src="gambar atau poster/Syarat dan ketentuan penitipan barang_makanan.png"
              alt="Syarat dan Ketentuan Penitipan Barang dan Makanan"
              style="width: 100%; max-height: 520px; object-fit: contain; display: block; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);" />
          </div>
        </div>
      </div>
    </div>
  </section>
<!-- ===== BANNER TOLAK GRATIFIKASI ===== -->
  <section class="section-banner-gratifikasi">
    <img src="banner/tolak gratifikasi panjang.png"
      alt="Komitmen Bersama Tolak Gratifikasi &amp; Pungli — Rutan Tanjung Kelas IIB" class="banner-gratifikasi-img"
      loading="lazy" />
  </section>

  <!-- ===== PENGADUAN via WhatsApp ===== -->
  <section class="section section-dark" id="pengaduan">
    <div class="pengaduan-bg-pattern"></div>
    <div class="container">
      <div class="section-header light">
        <span class="section-tag gold">Hak Anda</span>
        <h2 class="section-title">Pengaduan</h2>
        <p class="section-desc">Sampaikan pengaduan, saran, dan masukan Anda langsung melalui WhatsApp resmi kami.
          Setiap laporan akan ditindaklanjuti dengan serius dan profesional.</p>
      </div>

      <div class="wa-pengaduan-wrap">
        <!-- Alur Cara Mengadu -->
        <div class="wa-steps">
          <h3 class="wa-steps-title">Cara Menyampaikan Pengaduan</h3>
          <div class="wa-step-list">
            <div class="wa-step">
              <div class="wa-step-num">1</div>
              <div class="wa-step-info">
                <strong>Siapkan Informasi</strong>
                <p>Siapkan nama lengkap, nomor identitas, dan uraian singkat pengaduan Anda.</p>
              </div>
            </div>
            <div class="wa-step-arrow">→</div>
            <div class="wa-step">
              <div class="wa-step-num">2</div>
              <div class="wa-step-info">
                <strong>Hubungi via WhatsApp</strong>
                <p>Klik tombol di bawah untuk membuka chat WhatsApp resmi Rutan Tanjung.</p>
              </div>
            </div>
            <div class="wa-step-arrow">→</div>
            <div class="wa-step">
              <div class="wa-step-num">3</div>
              <div class="wa-step-info">
                <strong>Sampaikan Pengaduan</strong>
                <p>Ceritakan pengaduan Anda dengan jelas. Petugas siap merespons di jam kerja.</p>
              </div>
            </div>
            <div class="wa-step-arrow">→</div>
            <div class="wa-step">
              <div class="wa-step-num">4</div>
              <div class="wa-step-info">
                <strong>Tindak Lanjut</strong>
                <p>Pengaduan diproses dalam 7 hari kerja. Anda akan mendapat konfirmasi.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- WhatsApp Card -->
        <div class="wa-card-wrap">
          <div class="wa-card" id="wa-card">
            <div class="wa-card-top">
              <div class="wa-icon-big">
                <svg viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                  <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                  <path
                    d="M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.824 11.824 0 0 0 12.05 0zm0 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884z" />
                </svg>
              </div>
              <div class="wa-card-title-wrap">
                <span class="wa-badge">Resmi</span>
                <h3 class="wa-card-title">WhatsApp Pengaduan</h3>
                <p class="wa-card-sub">Rutan Tanjung Kelas IIB</p>
              </div>
            </div>
            <div class="wa-number-display" id="wa-number-display">
              <span class="wa-num-label">Nomor WhatsApp Resmi</span>
              <span class="wa-num">+62 812-4197-4185</span>
              <span class="wa-num-note">Aktif Senin–Jumat, 07.30–16.00 WITA</span>
            </div>
            <div class="wa-quick-msgs">
              <p class="wa-quick-label">Pilih topik pengaduan:</p>
              <div class="wa-quick-btns">
                <button class="wa-quick-btn" onclick="openWA('Pengaduan Pelayanan')" id="wa-q1">🏛️ Pelayanan</button>
                <button class="wa-quick-btn" onclick="openWA('Pengaduan Kunjungan')" id="wa-q2">👥 Kunjungan</button>
                <button class="wa-quick-btn" onclick="openWA('Pengaduan Perlakuan Tahanan')" id="wa-q3">⚖️
                  Perlakuan</button>
                <button class="wa-quick-btn" onclick="openWA('Pengaduan Administrasi')" id="wa-q4">📄
                  Administrasi</button>
                <button class="wa-quick-btn" onclick="openWA('Pengaduan Lainnya')" id="wa-q5">💬 Lainnya</button>
              </div>
            </div>
            <a href="https://wa.me/6281241974185?text=Selamat%20Pagi%2C%20Saya%20ingin%20menyampaikan%20pengaduan%20kepada%20Rutan%20Tanjung%20Kelas%20IIB.%0A%0ANama%3A%0AJenis%20Pengaduan%3A%0AUraian%3A"
              target="_blank" rel="noopener" class="btn-wa-main" id="btn-wa-main">
              <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                <path
                  d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                <path
                  d="M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.824 11.824 0 0 0 12.05 0zm0 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884z" />
              </svg>
              Mulai Chat WhatsApp Sekarang
            </a>
            <p class="wa-privacy-note">🔒 Identitas Anda dijaga kerahasiaannya sesuai peraturan perundang-undangan</p>
          </div>

          <!-- Info tambahan -->
          <div class="wa-info-cards">
            <div class="wa-info-card">
              <span>⚡</span>
              <strong>Respons Cepat</strong>
              <p>Dijawab dalam 1×24 jam kerja</p>
            </div>
            <div class="wa-info-card">
              <span>🛡️</span>
              <strong>Aman &amp; Terjamin</strong>
              <p>Identitas pelapor terlindungi</p>
            </div>
            <div class="wa-info-card">
              <span>📋</span>
              <strong>Ditindaklanjuti</strong>
              <p>Setiap laporan diproses serius</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== SOSIAL MEDIA KAMI ===== -->
  <section class="section section-alt" id="sosmed">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Publikasi &amp; Informasi Terkini</span>
        <h2 class="section-title">Sosial Media Kami</h2>
        <p class="section-desc">Ikuti seluruh kanal media sosial resmi Rumah Tahanan Negara Kelas IIB Tanjung untuk mendapatkan informasi terkini.</p>
      </div>

      <div class="sosmed-banners-list">
        <!-- 1. Instagram -->
        <div class="sosmed-banner-card" id="banner-instagram">
          <div class="sosmed-banner-left">
            <div class="sosmed-banner-avatar ig">
              <svg viewBox="0 0 64 64" width="60" height="60" class="ig-avatar-svg">
                <circle cx="32" cy="32" r="32" fill="url(#igBannerGrad)" />
                <defs>
                  <radialGradient id="igBannerGrad" cx="30%" cy="105%" r="130%">
                    <stop offset="0%" stop-color="#ffd879" />
                    <stop offset="25%" stop-color="#f26522" />
                    <stop offset="60%" stop-color="#d91a8a" />
                    <stop offset="100%" stop-color="#5b4fe9" />
                  </radialGradient>
                </defs>
                <rect x="18" y="18" width="28" height="28" rx="8" fill="none" stroke="white" stroke-width="2.5" />
                <circle cx="32" cy="32" r="6.5" fill="none" stroke="white" stroke-width="2.5" />
                <circle cx="39.5" cy="24.5" r="2" fill="white" />
              </svg>
            </div>
            <div class="sosmed-banner-info">
              <div class="sosmed-banner-handle">
                <h3 class="handle-ig">@rutan_tanjung</h3>
              </div>
              <p class="sosmed-banner-desc">Akun Instagram Resmi Rumah Tahanan Negara Kelas IIB Tanjung — Ditjen Pemasyarakatan</p>
            </div>
          </div>
          <div class="sosmed-banner-actions">
            <a href="https://www.instagram.com/rutan_tanjung" target="_blank" rel="noopener" class="btn-channel-banner ig" id="btn-ig-profile">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
              </svg>
              <span>Kunjungi Instagram ↗</span>
            </a>
          </div>
        </div>

        <!-- 2. Facebook -->
        <div class="sosmed-banner-card" id="banner-facebook">
          <div class="sosmed-banner-left">
            <div class="sosmed-banner-avatar fb">
              <svg viewBox="0 0 64 64" width="60" height="60">
                <circle cx="32" cy="32" r="32" fill="#1877f2" />
                <path d="M37 20h-4a6 6 0 0 0-6 6v4h-4v6h4v14h6V36h4.5l.8-6H33v-3.5a1.5 1.5 0 0 1 1.5-1.5H37z" fill="white" />
              </svg>
            </div>
            <div class="sosmed-banner-info">
              <div class="sosmed-banner-handle">
                <h3 class="handle-fb">Rutan Tanjung</h3>
              </div>
              <p class="sosmed-banner-desc">Halaman Facebook Resmi Rumah Tahanan Negara Kelas IIB Tanjung</p>
            </div>
          </div>
          <div class="sosmed-banner-actions">
            <a href="https://www.facebook.com/share/14nipLTAWgD/" target="_blank" rel="noopener" class="btn-channel-banner fb" id="btn-fb-profile">
              <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
              </svg>
              <span>Kunjungi Facebook ↗</span>
            </a>
          </div>
        </div>

        <!-- 3. YouTube -->
        <div class="sosmed-banner-card" id="banner-youtube">
          <div class="sosmed-banner-left">
            <div class="sosmed-banner-avatar yt">
              <svg viewBox="0 0 64 64" width="60" height="60">
                <circle cx="32" cy="32" r="32" fill="#ff0000" />
                <path d="M46 26.5c-.3-1.6-1.5-2.8-3.1-3.1C40.2 23 32 23 32 23s-8.2 0-10.9.4c-1.6.3-2.8 1.5-3.1 3.1-.4 2.7-.4 5.5-.4 5.5s0 2.8.4 5.5c.3 1.6 1.5 2.8 3.1 3.1 2.7.4 10.9.4 10.9.4s8.2 0 10.9-.4c1.6-.3 2.8-1.5 3.1-3.1.4-2.7.4-5.5.4-5.5s0-2.8-.4-5.5z" fill="white" />
                <polygon points="29 27.5 37 32 29 36.5" fill="#ff0000" />
              </svg>
            </div>
            <div class="sosmed-banner-info">
              <div class="sosmed-banner-handle">
                <h3 class="handle-yt">@rutantanjung</h3>
              </div>
              <p class="sosmed-banner-desc">Saluran YouTube Resmi Rutan Kelas IIB Tanjung — Publikasi Kegiatan &amp; Video Edukasi</p>
            </div>
          </div>
          <div class="sosmed-banner-actions">
            <a href="https://youtube.com/@rutantanjung?si=2dmk1-x2Z7LyfAdb" target="_blank" rel="noopener" class="btn-channel-banner yt" id="btn-yt-profile">
              <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                <path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z" />
                <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#ff0000" />
              </svg>
              <span>Kunjungi YouTube ↗</span>
            </a>
          </div>
        </div>

        <!-- 4. X (Twitter) -->
        <div class="sosmed-banner-card" id="banner-x">
          <div class="sosmed-banner-left">
            <div class="sosmed-banner-avatar tw">
              <svg viewBox="0 0 64 64" width="60" height="60">
                <circle cx="32" cy="32" r="32" fill="#0f1419" />
                <path d="M39.6 20h4.3l-9.4 10.8 11.1 14.6h-8.7l-6.8-8.9-7.8 8.9h-4.3l10.1-11.5L17.5 20h8.9l6.1 8.1zm-1.5 22.8h2.4L25.9 22.4h-2.6z" fill="white" />
              </svg>
            </div>
            <div class="sosmed-banner-info">
              <div class="sosmed-banner-handle">
                <h3 class="handle-tw">@HumasRutansa</h3>
              </div>
              <p class="sosmed-banner-desc">Akun Resmi Humas Rumah Tahanan Negara Kelas IIB Tanjung di X (Twitter)</p>
            </div>
          </div>
          <div class="sosmed-banner-actions">
            <a href="https://x.com/HumasRutansa" target="_blank" rel="noopener" class="btn-channel-banner tw" id="btn-tw-profile">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
              <span>Kunjungi X (Twitter) ↗</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
    </div>
  </section>

  <!-- ===== KONTAK & LOKASI ===== -->
  <section class="section" id="kontak">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Hubungi Kami</span>
        <h2 class="section-title">Kontak &amp; Lokasi</h2>
        <p class="section-desc">Temukan kami atau hubungi langsung melalui saluran resmi berikut</p>
      </div>
      <div class="kontak-grid">
        <!-- Informasi Kontak -->
        <div class="kontak-info-wrap">
          <div class="kontak-item" id="kontak-alamat">
            <div class="kontak-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="kontak-detail">
              <strong>Alamat Kantor</strong>
              <p>Jl. Jaksa Agung Suprapto No. 74, Tanjung,<br />Kecamatan Tanjung, Kabupaten Tabalong,<br />Kalimantan Selatan 71571</p>
              <a href="https://maps.app.goo.gl/13maXL3nUBThc6ro6" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:6px; margin-top:8px; font-size:13px; color:var(--gold-dark); font-weight:700;">
                <span>📍 Petunjuk Arah Google Maps</span> →
              </a>
            </div>
          </div>
          <div class="kontak-item" id="kontak-whatsapp">
            <div class="kontak-icon" style="color:#25d366; background:rgba(37,211,102,0.1); border-color:rgba(37,211,102,0.2);">
              <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                <path
                  d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                <path
                  d="M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.824 11.824 0 0 0 12.05 0zm0 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884z" />
              </svg>
            </div>
            <div class="kontak-detail">
              <strong>WhatsApp Layanan &amp; Pengaduan</strong>
              <p><a href="#pengaduan" style="color:var(--primary); font-weight:700; font-size:1.05rem;">0812-4197-4185</a></p>
              <a href="#pengaduan" style="display:inline-flex; align-items:center; gap:6px; font-size:13px; color:#128c7e; font-weight:600;">
                <span>💬 Sampaikan Pengaduan</span> →
              </a>
            </div>
          </div>
        </div>

        <!-- Peta Google Maps Embed -->
        <div class="map-wrap">
          <div class="map-card">
            <div class="map-header">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
              Lokasi Rutan Tanjung Kelas IIB
            </div>
            <div class="map-embed-container">
              <iframe
                src="https://maps.google.com/maps?q=RUTAN+KLAS+IIB+TANJUNG,+Jl.+Jaksa+Agung+Suprapto+No.74,+Tanjung,+Tabalong,+Kalimantan+Selatan&amp;t=&amp;z=16&amp;ie=UTF8&amp;iwloc=&amp;output=embed"
                width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade" title="Peta Lokasi Rutan Tanjung Kelas IIB"></iframe>
            </div>
            <a href="https://maps.app.goo.gl/13maXL3nUBThc6ro6" target="_blank" rel="noopener"
              class="btn-map-open">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                <polyline points="15 3 21 3 21 9" />
                <line x1="10" y1="14" x2="21" y2="3" />
              </svg>
              Buka di Google Maps
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <div class="footer-top">
      <div class="container footer-top-inner">
        <div class="footer-brand">
          <div class="footer-logo">
            <img src="banner/LOGO RUTAN png.png" alt="Logo Rutan Tanjung" width="48" height="48"
              style="object-fit: contain; display: block;" />
            <div>
              <span class="footer-logo-title">RUTAN TANJUNG</span>
              <span class="footer-logo-sub">Kelas IIB</span>
            </div>
          </div>
          <p class="footer-desc">Rumah Tahanan Negara Tanjung Kelas IIB — Unit Pelaksana Teknis di bawah Ditjen
            Pemasyarakatan, Kementerian Imigrasi dan Pemasyarakatan RI.</p>
          <div class="footer-sosmed">
            <a href="https://x.com/HumasRutansa" target="_blank" rel="noopener" id="footer-x" class="footer-sosmed-icon" aria-label="X (Twitter)"><svg viewBox="0 0 24 24"
                fill="currentColor" width="16" height="16">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg></a>
            <a href="https://www.facebook.com/share/14nipLTAWgD/" target="_blank" rel="noopener" id="footer-fb" class="footer-sosmed-icon" aria-label="Facebook"><svg viewBox="0 0 24 24"
                fill="currentColor" width="18" height="18">
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
              </svg></a>
            <a href="https://www.instagram.com/rutan_tanjung" target="_blank" rel="noopener" id="footer-ig" class="footer-sosmed-icon" aria-label="Instagram"><svg viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
              </svg></a>
            <a href="https://youtube.com/@rutantanjung?si=2dmk1-x2Z7LyfAdb" target="_blank" rel="noopener" id="footer-yt" class="footer-sosmed-icon" aria-label="YouTube"><svg viewBox="0 0 24 24"
                width="18" height="18">
                <path fill="currentColor"
                  d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z" />
                <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#061540" />
              </svg></a>
          </div>
        </div>
        <div class="footer-links-col">
          <h5>Navigasi</h5>
          <ul>
            <li><a href="#tentang">Tentang Kami</a></li>
            <li><a href="#visi-misi">Visi &amp; Misi</a></li>
            <li><a href="#pejabat">Pejabat Struktural</a></li>
            <li><a href="#jadwal">Jadwal Pelayanan</a></li>
            <li><a href="#pengaduan">Pengaduan</a></li>
            <li><a href="#sosmed">Sosial Media Kami</a></li>
            <li><a href="#kontak">Kontak &amp; Lokasi</a></li>
          </ul>
        </div>
        <div class="footer-links-col">
          <h5>Layanan</h5>
          <ul>
            <li><a href="#jadwal">Jadwal Kunjungan</a></li>
            <li><a href="#jadwal">Persyaratan Kunjungan</a></li>
            <li><a href="#pengaduan">Pengaduan Online</a></li>
            <li><a href="#kontak">Hubungi Kami</a></li>
          </ul>
        </div>

      </div>
    </div>
    <div class="footer-bottom">
      <div class="container footer-bottom-inner">
        <p>© <span id="footer-year"></span> Rumah Tahanan Negara Tanjung Kelas IIB — Kemenimipas RI. Hak Cipta Dilindungi.</p>
        <p>Dibangun untuk melayani masyarakat Indonesia dengan penuh dedikasi.</p>
      </div>
    </div>
  </footer>

  <!-- Success Modal (tidak digunakan — pengaduan langsung via WA) -->
  <div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
      <div class="modal-icon">✅</div>
      <h3>Pengaduan Terkirim!</h3>
      <p>Terima kasih. Pengaduan Anda telah berhasil dikirim. Nomor tiket pengaduan Anda:</p>
      <div class="ticket-number" id="ticketNumber">RTN-2024-XXXXX</div>
      <p class="ticket-note">Simpan nomor tiket ini untuk memantau status pengaduan Anda. Kami akan menghubungi Anda
        dalam 7 hari kerja.</p>
      <button class="btn-modal-close" onclick="closeModal()">Tutup</button>
    </div>
  </div>

  <!-- ===== POPUP ADS ON LOAD ===== -->
  <?php if (!empty($popup_ads_rows)): ?>
  <div id="popupAds" class="popup-ads-overlay" onclick="closePopupAds()">
    <div class="popup-ads-dialog" onclick="event.stopPropagation()">
      <!-- Tombol Tutup X -->
      <button class="popup-ads-close" onclick="closePopupAds()" aria-label="Tutup Pop-up">&#x2715;</button>

      <!-- Slides Gambar dari database -->
      <div class="popup-ads-slides-wrap">
        <?php foreach ($popup_ads_rows as $idx => $pop):
          $p_img = htmlspecialchars($pop['gambar']);
          $p_link = trim($pop['link_url'] ?? '');
          $p_alt = !empty($pop['keterangan']) ? htmlspecialchars($pop['keterangan']) : 'Informasi Rutan Tanjung';
        ?>
        <div class="popup-ads-slide <?= $idx === 0 ? 'active' : '' ?>">
          <?php if (!empty($p_link)): ?>
            <a href="<?= htmlspecialchars($p_link) ?>" target="_blank" rel="noopener noreferrer" style="display:block; width:100%; height:100%; cursor:pointer;" title="Klik untuk membuka link">
              <img src="<?= $p_img ?>" alt="<?= $p_alt ?>" class="popup-ads-img" />
            </a>
          <?php else: ?>
            <img src="<?= $p_img ?>" alt="<?= $p_alt ?>" class="popup-ads-img" />
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (count($popup_ads_rows) > 1): ?>
      <!-- Navigasi Slide -->
      <button class="popup-ads-arrow prev" onclick="changePopupSlide(-1, event)"
        aria-label="Sebelumnya">&#x2B9C;</button>
      <button class="popup-ads-arrow next" onclick="changePopupSlide(1, event)"
        aria-label="Selanjutnya">&#x2B9E;</button>
      <?php endif; ?>

      <!-- Footer Pop-up -->
      <div class="popup-ads-footer">
        <div class="popup-ads-counter" id="popupCounter">1 / <?= count($popup_ads_rows) ?></div>
        <?php if (count($popup_ads_rows) > 1): ?>
        <div class="popup-ads-dots">
          <?php foreach ($popup_ads_rows as $idx => $pop): ?>
          <span class="popup-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="goToPopupSlide(<?= $idx ?>, event)"></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button class="popup-ads-close-text" onclick="closePopupAds()">
          &#x2715; Tutup (Tap di mana saja)
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== LIGHTBOX FOTO PEJABAT ===== -->
  <div id="lightbox" onclick="closeLightbox()" style="
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(0,0,0,0.85); backdrop-filter:blur(6px);
    align-items:center; justify-content:center; cursor:zoom-out;
  ">
    <div style="position:relative; text-align:center;">
      <img id="lightbox-img" src="" alt="" style="
        max-width:90vw; max-height:85vh;
        border-radius:16px;
        border:3px solid rgba(201,168,76,0.6);
        box-shadow:0 20px 60px rgba(0,0,0,0.6);
        object-fit:contain;
      " />
      <p id="lightbox-caption" style="
        color:#fff; margin-top:14px; font-size:16px;
        font-weight:600; letter-spacing:1px;
      "></p>
      <button onclick="closeLightbox()" style="
        position:absolute; top:-18px; right:-18px;
        background:rgba(201,168,76,0.9); border:none; border-radius:50%;
        width:36px; height:36px; font-size:18px; cursor:pointer;
        color:#0a2463; font-weight:bold; line-height:36px;
      ">✕</button>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
    // Lightbox
    function openLightbox(src, caption) {
      const lb = document.getElementById('lightbox');
      document.getElementById('lightbox-img').src = src;
      document.getElementById('lightbox-caption').textContent = caption;
      lb.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
      document.getElementById('lightbox').style.display = 'none';
      document.body.style.overflow = '';
    }

    // ===== POPUP ADS LOGIC =====
    let currentPopupIndex = 0;
    function showPopupSlide(index) {
      const slides = document.querySelectorAll('.popup-ads-slide');
      const dots = document.querySelectorAll('.popup-dot');
      const counter = document.getElementById('popupCounter');
      if (!slides.length) return;
      if (index >= slides.length) currentPopupIndex = 0;
      else if (index < 0) currentPopupIndex = slides.length - 1;
      else currentPopupIndex = index;

      slides.forEach((slide, i) => {
        slide.classList.toggle('active', i === currentPopupIndex);
      });
      dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === currentPopupIndex);
      });
      if (counter) {
        counter.textContent = `${currentPopupIndex + 1} / ${slides.length}`;
      }
    }

    function changePopupSlide(step, e) {
      if (e) e.stopPropagation();
      showPopupSlide(currentPopupIndex + step);
    }

    function goToPopupSlide(index, e) {
      if (e) e.stopPropagation();
      showPopupSlide(index);
    }

    function openPopupAds() {
      const p = document.getElementById('popupAds');
      if (p) {
        showPopupSlide(0);
        p.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    function closePopupAds() {
      const p = document.getElementById('popupAds');
      if (p) {
        p.classList.remove('active');
        document.body.style.overflow = '';
      }
    }

    // Keydown ESC untuk menutup Lightbox & Popup Ads
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeLightbox();
        closePopupAds();
      }
    });

    // Otomatis muncul setiap kali web dibuka maupun di-refresh
    window.addEventListener('load', () => {
      setTimeout(() => {
        openPopupAds();
      }, 400);
    });
  </script>
</body>

</html>