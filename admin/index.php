<?php
/**
 * admin/index.php
 * Dashboard Admin & CRUD Manajemen Berita Rutan Tanjung Kelas IIB
 */
session_start();

// Proteksi Sesi Admin - Jika belum login, redirect ke halaman login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php?msg=belum_login");
    exit;
}

require_once __DIR__ . '/../koneksi.php';

$upload_dir = __DIR__ . '/../uploads/berita/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Inisialisasi variabel pesan notifikasi
$pesan_sukses = $_SESSION['flash_sukses'] ?? '';
$pesan_error  = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_sukses'], $_SESSION['flash_error']);

// Tentukan aksi / tampilan (default: 'list', 'tambah', 'edit')
$action = $_GET['action'] ?? 'list';
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ==========================================
// PROSES CRUD: TAMBAH BERITA (CREATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_tambah'])) {
    $judul   = trim($_POST['judul'] ?? '');
    $penulis = trim($_POST['penulis'] ?? '');
    $isi     = trim($_POST['isi'] ?? '');

    if (empty($judul) || empty($penulis) || empty($isi)) {
        $_SESSION['flash_error'] = 'Judul, penulis, dan isi berita wajib diisi!';
        header("Location: index.php?action=tambah");
        exit;
    }

    // Buat slug ramah SEO dan pastikan unik
    $slug_base = buat_slug($judul);
    $slug = $slug_base;
    $counter = 1;
    while (true) {
        $check_stmt = $conn->prepare("SELECT id FROM berita WHERE slug = ? LIMIT 1");
        $check_stmt->bind_param("s", $slug);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        if ($check_res->num_rows === 0) {
            $check_stmt->close();
            break;
        }
        $check_stmt->close();
        $slug = $slug_base . '-' . $counter;
        $counter++;
    }

    // Proses upload gambar
    $nama_file_gambar = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['gambar']['tmp_name'];
        $file_name = $_FILES['gambar']['name'];
        $file_size = $_FILES['gambar']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_ext)) {
            $_SESSION['flash_error'] = 'Format gambar tidak valid! Gunakan format JPG, JPEG, PNG, atau WEBP.';
            header("Location: index.php?action=tambah");
            exit;
        }

        if ($file_size > 5 * 1024 * 1024) { // Max 5MB
            $_SESSION['flash_error'] = 'Ukuran gambar terlalu besar! Maksimal 5MB.';
            header("Location: index.php?action=tambah");
            exit;
        }

        $nama_file_gambar = 'berita-' . time() . '-' . uniqid() . '.' . $file_ext;
        $tujuan_upload    = $upload_dir . $nama_file_gambar;

        if (!move_uploaded_file($file_tmp, $tujuan_upload)) {
            $_SESSION['flash_error'] = 'Gagal mengunggah file gambar ke server.';
            header("Location: index.php?action=tambah");
            exit;
        }
    }

    // Simpan ke database dengan Prepared Statement
    $stmt = $conn->prepare("INSERT INTO berita (judul, slug, isi, gambar, penulis, tanggal_dibuat) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $judul, $slug, $isi, $nama_file_gambar, $penulis);

    if ($stmt->execute()) {
        $_SESSION['flash_sukses'] = 'Berita baru berhasil ditambahkan!';
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['flash_error'] = 'Gagal menyimpan berita: ' . $stmt->error;
        header("Location: index.php?action=tambah");
        exit;
    }
}

// ==========================================
// PROSES CRUD: EDIT BERITA (UPDATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_edit'])) {
    $id      = (int)($_POST['id'] ?? 0);
    $judul   = trim($_POST['judul'] ?? '');
    $penulis = trim($_POST['penulis'] ?? '');
    $isi     = trim($_POST['isi'] ?? '');

    if ($id <= 0 || empty($judul) || empty($penulis) || empty($isi)) {
        $_SESSION['flash_error'] = 'Data tidak lengkap!';
        header("Location: index.php?action=edit&id=" . $id);
        exit;
    }

    // Ambil data lama
    $get_old = $conn->prepare("SELECT gambar, slug, judul FROM berita WHERE id = ? LIMIT 1");
    $get_old->bind_param("i", $id);
    $get_old->execute();
    $old_data = $get_old->get_result()->fetch_assoc();
    $get_old->close();

    if (!$old_data) {
        $_SESSION['flash_error'] = 'Data berita tidak ditemukan!';
        header("Location: index.php");
        exit;
    }

    // Update slug jika judul berubah
    $slug = $old_data['slug'];
    if ($old_data['judul'] !== $judul) {
        $slug_base = buat_slug($judul);
        $slug = $slug_base;
        $counter = 1;
        while (true) {
            $check_stmt = $conn->prepare("SELECT id FROM berita WHERE slug = ? AND id != ? LIMIT 1");
            $check_stmt->bind_param("si", $slug, $id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($check_res->num_rows === 0) {
                $check_stmt->close();
                break;
            }
            $check_stmt->close();
            $slug = $slug_base . '-' . $counter;
            $counter++;
        }
    }

    $nama_file_gambar = $old_data['gambar'];

    // Cek apakah ada upload gambar baru
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['gambar']['tmp_name'];
        $file_name = $_FILES['gambar']['name'];
        $file_size = $_FILES['gambar']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_ext)) {
            $_SESSION['flash_error'] = 'Format gambar tidak valid! Gunakan format JPG, JPEG, PNG, atau WEBP.';
            header("Location: index.php?action=edit&id=" . $id);
            exit;
        }

        if ($file_size > 5 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Ukuran gambar terlalu besar! Maksimal 5MB.';
            header("Location: index.php?action=edit&id=" . $id);
            exit;
        }

        $nama_file_baru = 'berita-' . time() . '-' . uniqid() . '.' . $file_ext;
        $tujuan_upload   = $upload_dir . $nama_file_baru;

        if (move_uploaded_file($file_tmp, $tujuan_upload)) {
            // Hapus file gambar lama jika ada dan file fisiknya ada di folder uploads/berita
            if (!empty($nama_file_gambar) && file_exists($upload_dir . $nama_file_gambar)) {
                @unlink($upload_dir . $nama_file_gambar);
            }
            $nama_file_gambar = $nama_file_baru;
        }
    }

    // Simpan perubahan ke database dengan Prepared Statement
    $stmt = $conn->prepare("UPDATE berita SET judul = ?, slug = ?, isi = ?, gambar = ?, penulis = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $judul, $slug, $isi, $nama_file_gambar, $penulis, $id);

    if ($stmt->execute()) {
        $_SESSION['flash_sukses'] = 'Berita berhasil diperbarui!';
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['flash_error'] = 'Gagal memperbarui berita: ' . $stmt->error;
        header("Location: index.php?action=edit&id=" . $id);
        exit;
    }
}

// ==========================================
// PROSES CRUD: HAPUS BERITA (DELETE)
// ==========================================
if ($action === 'hapus' && $edit_id > 0) {
    // Ambil info gambar sebelum menghapus dari database
    $get_img = $conn->prepare("SELECT gambar FROM berita WHERE id = ? LIMIT 1");
    $get_img->bind_param("i", $edit_id);
    $get_img->execute();
    $img_res = $get_img->get_result();
    $data_img = $img_res->fetch_assoc();
    $get_img->close();

    if ($data_img) {
        $stmt = $conn->prepare("DELETE FROM berita WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        if ($stmt->execute()) {
            // Hapus file fisik gambar jika ada di uploads/berita/
            if (!empty($data_img['gambar']) && file_exists($upload_dir . $data_img['gambar'])) {
                @unlink($upload_dir . $data_img['gambar']);
            }
            $_SESSION['flash_sukses'] = 'Berita dan gambar berhasil dihapus!';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus berita: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = 'Data berita tidak ditemukan!';
    }
    header("Location: index.php");
    exit;
}

// Ambil total berita untuk statistik
$stat_berita = $conn->query("SELECT COUNT(*) as total FROM berita")->fetch_assoc()['total'] ?? 0;

// Ambil data untuk form edit jika action=edit
$data_edit = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM berita WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data_edit = $res->fetch_assoc();
    $stmt->close();
    if (!$data_edit) {
        $_SESSION['flash_error'] = 'Berita yang ingin diedit tidak ditemukan!';
        header("Location: index.php");
        exit;
    }
}

// ==========================================
// PENGATURAN TAB (berita | jadwal)
// ==========================================
$tab = $_GET['tab'] ?? 'berita';

// ==========================================
// PROSES CRUD: JADWAL PELAYANAN
// ==========================================

// 1. Simpan Catatan Ketentuan Pelayanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_simpan_catatan'])) {
    $catatan_penitipan = trim($_POST['catatan_penitipan'] ?? '');
    $catatan_kunjungan = trim($_POST['catatan_kunjungan'] ?? '');

    $stmt_cp = $conn->prepare("INSERT INTO pengaturan_jadwal (kunci, nilai) VALUES ('catatan_penitipan', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    $stmt_cp->bind_param("s", $catatan_penitipan);
    $stmt_cp->execute();
    $stmt_cp->close();

    $stmt_ck = $conn->prepare("INSERT INTO pengaturan_jadwal (kunci, nilai) VALUES ('catatan_kunjungan', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    $stmt_ck->bind_param("s", $catatan_kunjungan);
    $stmt_ck->execute();
    $stmt_ck->close();

    $_SESSION['flash_sukses'] = 'Catatan ketentuan pelayanan berhasil disimpan!';
    header("Location: index.php?tab=jadwal");
    exit;
}

// 2. Tambah Baris Jadwal Baru (Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_tambah_jadwal'])) {
    $kategori   = in_array($_POST['kategori'] ?? '', ['kunjungan', 'penitipan']) ? $_POST['kategori'] : 'kunjungan';
    $hari       = trim($_POST['hari'] ?? '');
    $waktu      = trim($_POST['waktu'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $is_tutup   = isset($_POST['is_tutup']) ? 1 : 0;
    $urutan     = (int)($_POST['urutan'] ?? 1);

    if (empty($hari) || empty($waktu)) {
        $_SESSION['flash_error'] = 'Hari dan Jam/Waktu pelayanan wajib diisi!';
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO jadwal_pelayanan (kategori, hari, waktu, keterangan, is_tutup, urutan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("ssssii", $kategori, $hari, $waktu, $keterangan, $is_tutup, $urutan);
        if ($stmt_ins->execute()) {
            $_SESSION['flash_sukses'] = 'Baris jadwal baru berhasil ditambahkan!';
        } else {
            $_SESSION['flash_error'] = 'Gagal menambahkan jadwal: ' . $stmt_ins->error;
        }
        $stmt_ins->close();
    }
    header("Location: index.php?tab=jadwal");
    exit;
}

// 3. Edit Baris Jadwal (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_edit_jadwal'])) {
    $id_jadwal  = (int)($_POST['id_jadwal'] ?? 0);
    $kategori   = in_array($_POST['kategori'] ?? '', ['kunjungan', 'penitipan']) ? $_POST['kategori'] : 'kunjungan';
    $hari       = trim($_POST['hari'] ?? '');
    $waktu      = trim($_POST['waktu'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $is_tutup   = isset($_POST['is_tutup']) ? 1 : 0;
    $urutan     = (int)($_POST['urutan'] ?? 1);

    if ($id_jadwal <= 0 || empty($hari) || empty($waktu)) {
        $_SESSION['flash_error'] = 'Data jadwal tidak lengkap atau tidak valid!';
    } else {
        $stmt_upd = $conn->prepare("UPDATE jadwal_pelayanan SET kategori = ?, hari = ?, waktu = ?, keterangan = ?, is_tutup = ?, urutan = ? WHERE id = ?");
        $stmt_upd->bind_param("ssssiii", $kategori, $hari, $waktu, $keterangan, $is_tutup, $urutan, $id_jadwal);
        if ($stmt_upd->execute()) {
            $_SESSION['flash_sukses'] = 'Baris jadwal berhasil diperbarui!';
        } else {
            $_SESSION['flash_error'] = 'Gagal memperbarui jadwal: ' . $stmt_upd->error;
        }
        $stmt_upd->close();
    }
    header("Location: index.php?tab=jadwal");
    exit;
}

// 4. Hapus Baris Jadwal (Delete)
if (isset($_GET['hapus_jadwal'])) {
    $id_hapus = (int)$_GET['hapus_jadwal'];
    if ($id_hapus > 0) {
        $stmt_del = $conn->prepare("DELETE FROM jadwal_pelayanan WHERE id = ?");
        $stmt_del->bind_param("i", $id_hapus);
        if ($stmt_del->execute()) {
            $_SESSION['flash_sukses'] = 'Baris jadwal berhasil dihapus!';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus jadwal: ' . $stmt_del->error;
        }
        $stmt_del->close();
    }
    header("Location: index.php?tab=jadwal");
    exit;
}

// Data untuk form edit jadwal jika parameter edit_jadwal ada
$jadwal_edit_data = null;
$jadwal_edit_id = isset($_GET['edit_jadwal']) ? (int)$_GET['edit_jadwal'] : 0;
if ($jadwal_edit_id > 0) {
    $stmt_je = $conn->prepare("SELECT * FROM jadwal_pelayanan WHERE id = ? LIMIT 1");
    $stmt_je->bind_param("i", $jadwal_edit_id);
    $stmt_je->execute();
    $jadwal_edit_data = $stmt_je->get_result()->fetch_assoc();
    $stmt_je->close();
    if ($jadwal_edit_data) {
        $tab = 'jadwal';
    }
}

// Ambil data semua baris jadwal untuk tampilan tab jadwal
$stmt_jk_all = $conn->prepare("SELECT * FROM jadwal_pelayanan WHERE kategori = 'kunjungan' ORDER BY urutan ASC, id ASC");
$stmt_jk_all->execute();
$list_jadwal_kunjungan = $stmt_jk_all->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_jk_all->close();

$stmt_jp_all = $conn->prepare("SELECT * FROM jadwal_pelayanan WHERE kategori = 'penitipan' ORDER BY urutan ASC, id ASC");
$stmt_jp_all->execute();
$list_jadwal_penitipan = $stmt_jp_all->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_jp_all->close();

$catatan_penitipan_val = '';
$catatan_kunjungan_val = '';
$stmt_cp_val = $conn->prepare("SELECT kunci, nilai FROM pengaturan_jadwal WHERE kunci IN ('catatan_penitipan', 'catatan_kunjungan')");
$stmt_cp_val->execute();
$res_cp_val = $stmt_cp_val->get_result();
while ($row_c = $res_cp_val->fetch_assoc()) {
    if ($row_c['kunci'] === 'catatan_penitipan') {
        $catatan_penitipan_val = $row_c['nilai'];
    } elseif ($row_c['kunci'] === 'catatan_kunjungan') {
        $catatan_kunjungan_val = $row_c['nilai'];
    }
}
$stmt_cp_val->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — Manajemen Berita Rutan Tanjung</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../banner/LOGO RUTAN png.png">
  <style>
    :root {
      --primary: #0a2463;
      --primary-light: #1a3a7a;
      --primary-dark: #061540;
      --gold: #c9a84c;
      --gold-light: #e0c068;
      --gold-dark: #a88830;
      --white: #ffffff;
      --bg-page: #f4f6fa;
      --surface: #ffffff;
      --gray-50: #f8f9fc;
      --gray-100: #eef1f6;
      --gray-200: #dce1eb;
      --gray-400: #8f98a8;
      --gray-600: #555f75;
      --gray-800: #1e2433;
      --danger: #e53e3e;
      --danger-light: #fff5f5;
      --success: #2e8540;
      --success-light: #f0fff4;
      --font-body: 'Plus Jakarta Sans', sans-serif;
      --shadow-sm: 0 2px 6px rgba(10, 36, 99, 0.05);
      --shadow-md: 0 4px 16px rgba(10, 36, 99, 0.08);
      --shadow-lg: 0 10px 30px rgba(10, 36, 99, 0.12);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 18px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-body);
      background-color: var(--bg-page);
      color: var(--gray-800);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Top Navigation Bar */
    .admin-navbar {
      background: var(--primary-dark);
      color: var(--white);
      padding: 14px 28px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
      border-bottom: 2px solid var(--gold);
    }

    .admin-nav-container {
      max-width: 1300px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }

    .admin-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: var(--white);
    }

    .admin-brand img {
      width: 44px;
      height: 44px;
      object-fit: contain;
      filter: drop-shadow(0 0 6px rgba(201, 168, 76, 0.5));
    }

    .admin-brand-text h1 {
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: -0.2px;
      color: var(--white);
      line-height: 1.2;
    }

    .admin-brand-text span {
      font-size: 0.78rem;
      color: var(--gold-light);
      font-weight: 500;
    }

    .admin-nav-actions {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .user-pill {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(201, 168, 76, 0.3);
      padding: 6px 14px;
      border-radius: 30px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      color: var(--white);
    }

    .user-dot {
      width: 8px;
      height: 8px;
      background: #48bb78;
      border-radius: 50%;
      box-shadow: 0 0 6px #48bb78;
    }

    .btn-view-site {
      background: rgba(255, 255, 255, 0.1);
      color: var(--white);
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }

    .btn-view-site:hover {
      background: rgba(255, 255, 255, 0.2);
      color: var(--gold-light);
    }

    .btn-logout {
      background: rgba(229, 62, 62, 0.15);
      border: 1px solid rgba(229, 62, 62, 0.3);
      color: #feb2b2;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }

    .btn-logout:hover {
      background: var(--danger);
      color: var(--white);
      border-color: var(--danger);
    }

    /* Main Container */
    .admin-main {
      max-width: 1300px;
      margin: 28px auto;
      padding: 0 24px;
      width: 100%;
      flex: 1;
    }

    /* Alert Banner */
    .alert-banner {
      padding: 14px 18px;
      border-radius: var(--radius-sm);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      font-size: 0.92rem;
      font-weight: 500;
      animation: fadeIn 0.3s ease;
    }

    .alert-banner.success {
      background: var(--success-light);
      border: 1px solid #c6f6d5;
      color: var(--success);
    }

    .alert-banner.error {
      background: var(--danger-light);
      border: 1px solid #fed7d7;
      color: var(--danger);
    }

    .alert-close {
      cursor: pointer;
      background: none;
      border: none;
      color: inherit;
      font-size: 1.2rem;
      opacity: 0.7;
      transition: opacity 0.2s;
    }
    .alert-close:hover { opacity: 1; }

    /* Top Stats Grid */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 18px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: var(--surface);
      border-radius: var(--radius-md);
      padding: 20px 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--gray-200);
      display: flex;
      align-items: center;
      gap: 18px;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .stat-icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .stat-icon.blue {
      background: rgba(10, 36, 99, 0.1);
      color: var(--primary);
    }

    .stat-icon.gold {
      background: rgba(201, 168, 76, 0.15);
      color: var(--gold-dark);
    }

    .stat-icon.green {
      background: rgba(46, 133, 64, 0.1);
      color: var(--success);
    }

    .stat-info h4 {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--gray-600);
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-info .stat-value {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--primary-dark);
      line-height: 1;
    }

    /* Content Card */
    .content-box {
      background: var(--surface);
      border-radius: var(--radius-lg);
      border: 1px solid var(--gray-200);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .content-box-header {
      padding: 20px 28px;
      border-bottom: 1px solid var(--gray-100);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .content-box-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary-dark);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: 0.9rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(10, 36, 99, 0.15);
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--primary-light) 0%, #204b9e 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(10, 36, 99, 0.25);
    }

    .btn-secondary {
      background: var(--gray-100);
      color: var(--gray-800);
      padding: 10px 18px;
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: 0.9rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid var(--gray-200);
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      background: var(--gray-200);
    }

    /* Search & Filter Bar */
    .filter-bar {
      padding: 16px 28px;
      background: var(--gray-50);
      border-bottom: 1px solid var(--gray-100);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .search-box {
      position: relative;
      max-width: 380px;
      width: 100%;
    }

    .search-box input {
      width: 100%;
      padding: 9px 14px 9px 38px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-sm);
      font-size: 0.88rem;
      background: var(--white);
      outline: none;
      transition: border-color 0.2s;
    }

    .search-box input:focus {
      border-color: var(--primary);
    }

    .search-box svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray-400);
    }

    /* Table Styles */
    .table-responsive {
      overflow-x: auto;
      width: 100%;
    }

    .table-news {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.92rem;
    }

    .table-news th {
      background: var(--gray-50);
      color: var(--gray-600);
      font-weight: 700;
      padding: 14px 20px;
      border-bottom: 2px solid var(--gray-200);
      text-transform: uppercase;
      font-size: 0.78rem;
      letter-spacing: 0.5px;
    }

    .table-news td {
      padding: 16px 20px;
      border-bottom: 1px solid var(--gray-100);
      vertical-align: middle;
    }

    .table-news tr:hover td {
      background: rgba(244, 246, 250, 0.7);
    }

    .news-thumbnail {
      width: 72px;
      height: 52px;
      border-radius: 8px;
      object-fit: cover;
      background: var(--gray-100);
      border: 1px solid var(--gray-200);
      display: block;
      cursor: pointer;
      transition: transform 0.2s;
    }

    .news-thumbnail:hover {
      transform: scale(1.08);
      box-shadow: var(--shadow-sm);
    }

    .news-no-img {
      width: 72px;
      height: 52px;
      border-radius: 8px;
      background: var(--gray-100);
      border: 1px dashed var(--gray-400);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      color: var(--gray-400);
      text-align: center;
      line-height: 1.1;
      padding: 4px;
    }

    .news-title-cell {
      max-width: 380px;
    }

    .news-title-link {
      font-weight: 700;
      color: var(--primary-dark);
      text-decoration: none;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.35;
      transition: color 0.2s;
    }

    .news-title-link:hover {
      color: var(--gold-dark);
    }

    .news-slug-badge {
      font-size: 0.75rem;
      color: var(--gray-400);
      margin-top: 4px;
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 360px;
    }

    .badge-author {
      background: rgba(10, 36, 99, 0.08);
      color: var(--primary);
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      display: inline-block;
    }

    .action-buttons {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn-action-edit {
      background: #edf2f7;
      color: var(--primary);
      border: 1px solid var(--gray-200);
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s;
    }

    .btn-action-edit:hover {
      background: var(--primary);
      color: var(--white);
      border-color: var(--primary);
    }

    .btn-action-delete {
      background: var(--danger-light);
      color: var(--danger);
      border: 1px solid #fed7d7;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 0.82rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s;
      cursor: pointer;
    }

    .btn-action-delete:hover {
      background: var(--danger);
      color: var(--white);
      border-color: var(--danger);
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--gray-600);
    }

    .empty-state svg {
      margin: 0 auto 16px;
      color: var(--gray-400);
    }

    /* Form Styles (Tambah & Edit) */
    .form-container {
      padding: 32px 36px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 32px;
    }

    @media (max-width: 900px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    .form-group {
      margin-bottom: 22px;
    }

    .form-label {
      display: block;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--gray-800);
      margin-bottom: 8px;
    }

    .form-label span.req {
      color: var(--danger);
      margin-left: 2px;
    }

    .form-input, .form-textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-sm);
      font-size: 0.95rem;
      background: var(--white);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-input:focus, .form-textarea:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
    }

    .form-textarea {
      min-height: 240px;
      resize: vertical;
      line-height: 1.6;
    }

    .form-help {
      font-size: 0.8rem;
      color: var(--gray-600);
      margin-top: 6px;
    }

    /* Image Upload Box */
    .upload-box {
      border: 2px dashed var(--gray-200);
      border-radius: var(--radius-md);
      padding: 24px;
      text-align: center;
      background: var(--gray-50);
      position: relative;
      cursor: pointer;
      transition: all 0.2s;
    }

    .upload-box:hover {
      border-color: var(--gold);
      background: #fafaf5;
    }

    .upload-box input[type="file"] {
      position: absolute;
      top: 0; left: 0; width: 100%; height: 100%;
      opacity: 0;
      cursor: pointer;
    }

    .upload-preview-area img {
      max-width: 100%;
      max-height: 180px;
      object-fit: cover;
      border-radius: 8px;
      margin-top: 12px;
      box-shadow: var(--shadow-sm);
    }

    .form-actions-bar {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--gray-100);
    }

    /* Modal Image Lightbox */
    .admin-modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 20px;
    }
    .admin-modal-overlay.active { display: flex; }
    .admin-modal-img {
      max-width: 90vw;
      max-height: 85vh;
      border-radius: 8px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }

    /* Footer */
    .admin-footer {
      background: var(--surface);
      border-top: 1px solid var(--gray-200);
      text-align: center;
      padding: 16px;
      font-size: 0.82rem;
      color: var(--gray-600);
      margin-top: auto;
    }

    /* Tab Navigasi Admin */
    .admin-tabs-nav {
      display: flex;
      gap: 8px;
      margin-bottom: 24px;
      border-bottom: 2px solid var(--gray-200);
      padding-bottom: 0;
    }
    .admin-tab-item {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 24px;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--gray-600);
      text-decoration: none;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      transition: all 0.2s ease;
      border-radius: 8px 8px 0 0;
    }
    .admin-tab-item:hover {
      color: var(--primary);
      background: rgba(10, 36, 99, 0.04);
    }
    .admin-tab-item.active {
      color: var(--primary-dark);
      background: var(--white);
      border-bottom: 3px solid var(--gold);
      box-shadow: 0 -2px 10px rgba(0,0,0,0.03);
    }
    .badge-jadwal-buka {
      background: #dcfce7;
      color: #15803d;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .badge-jadwal-tutup {
      background: #f1f5f9;
      color: #64748b;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
  </style>
</head>
<body>

  <!-- Top Navigation Bar -->
  <header class="admin-navbar">
    <div class="admin-nav-container">
      <a href="index.php" class="admin-brand">
        <img src="../banner/LOGO RUTAN png.png" alt="Logo Rutan">
        <div class="admin-brand-text">
          <h1>RUTAN TANJUNG KELAS IIB</h1>
          <span>Panel Administrasi Berita & Informasi</span>
        </div>
      </a>

      <div class="admin-nav-actions">
        <div class="user-pill">
          <span class="user-dot"></span>
          <span><strong><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?></strong></span>
        </div>
        <a href="../index.php" target="_blank" class="btn-view-site">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
            <polyline points="15 3 21 3 21 9"></polyline>
            <line x1="10" y1="14" x2="21" y2="3"></line>
          </svg>
          Lihat Website
        </a>
        <a href="../logout.php" class="btn-logout" onclick="return confirm('Apakah Anda yakin ingin logout?');">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          Logout
        </a>
      </div>
    </div>
  </header>

  <main class="admin-main">

    <!-- Flash Notifikasi -->
    <?php if (!empty($pesan_sukses)): ?>
      <div class="alert-banner success" id="alertBanner">
        <div style="display:flex;align-items:center;gap:10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <span><?= htmlspecialchars($pesan_sukses) ?></span>
        </div>
        <button class="alert-close" onclick="document.getElementById('alertBanner').remove()">&times;</button>
      </div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
      <div class="alert-banner error" id="alertBanner">
        <div style="display:flex;align-items:center;gap:10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span><?= htmlspecialchars($pesan_error) ?></span>
        </div>
        <button class="alert-close" onclick="document.getElementById('alertBanner').remove()">&times;</button>
      </div>
    <?php endif; ?>

    <!-- Statistik Singkat -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon blue">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1"></path>
            <path d="M19 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-5"></path>
            <path d="M7 10h6"></path>
            <path d="M7 14h8"></path>
          </svg>
        </div>
        <div class="stat-info">
          <h4>Total Berita</h4>
          <div class="stat-value"><?= number_format($stat_berita) ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon gold">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </div>
        <div class="stat-info">
          <h4>Admin Aktif</h4>
          <div class="stat-value" style="font-size:1.2rem;"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
        </div>
        <div class="stat-info">
          <h4>Tanggal Hari Ini</h4>
          <div class="stat-value" style="font-size:1.1rem;"><?= format_tanggal_indo(date('Y-m-d')) ?></div>
        </div>
      </div>
    </div>

    <!-- Tab Navigasi Admin -->
    <div class="admin-tabs-nav">
      <a href="index.php?tab=berita" class="admin-tab-item <?= ($tab !== 'jadwal') ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        Manajemen Berita
      </a>
      <a href="index.php?tab=jadwal" class="admin-tab-item <?= ($tab === 'jadwal') ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        Kelola Jadwal Pelayanan
      </a>
    </div>

    <?php if ($tab !== 'jadwal'): ?>
    <!-- ============================================== -->
    <!-- TAMPILAN 1: FORM TAMBAH BERITA -->
    <!-- ============================================== -->
    <?php if ($action === 'tambah'): ?>
      <div class="content-box">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 5v14M5 12h14"></path>
            </svg>
            Tambah Berita Baru
          </div>
          <a href="index.php" class="btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Kembali ke Daftar
          </a>
        </div>

        <form action="index.php" method="POST" enctype="multipart/form-data" class="form-container">
          <input type="hidden" name="proses_tambah" value="1">

          <div class="form-grid">
            <div>
              <div class="form-group">
                <label class="form-label" for="judul">Judul Berita <span class="req">*</span></label>
                <input type="text" id="judul" name="judul" class="form-input" placeholder="Masukkan judul berita yang menarik dan informatif..." required autofocus>
              </div>

              <div class="form-group">
                <label class="form-label" for="isi">Isi Berita <span class="req">*</span></label>
                <textarea id="isi" name="isi" class="form-textarea" placeholder="Tuliskan isi berita secara lengkap di sini. Gunakan baris baru antar paragraf..." required></textarea>
                <div class="form-help">Format teks mendukung paragraf terpisah secara otomatis saat ditampilkan ke pembaca.</div>
              </div>
            </div>

            <div>
              <div class="form-group">
                <label class="form-label" for="penulis">Nama Penulis / Tim Redaksi <span class="req">*</span></label>
                <input type="text" id="penulis" name="penulis" class="form-input" value="Humas Rutan Tanjung" required>
              </div>

              <div class="form-group">
                <label class="form-label">Gambar Thumbnail Berita</label>
                <div class="upload-box" id="dropArea">
                  <input type="file" id="gambarInput" name="gambar" accept=".jpg,.jpeg,.png,.webp" onchange="previewImage(this)">
                  <div id="uploadPlaceholder">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--gold);margin:0 auto 10px;">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <circle cx="8.5" cy="8.5" r="1.5"></circle>
                      <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <p style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Klik atau Tarik Gambar ke Sini</p>
                    <p style="font-size:0.75rem;color:var(--gray-600);">JPG, JPEG, PNG, atau WEBP (Maks 5MB)</p>
                  </div>
                  <div class="upload-preview-area" id="previewArea" style="display:none;">
                    <img id="imgPreview" src="" alt="Preview">
                    <p style="font-size:0.8rem;color:var(--primary);margin-top:8px;font-weight:600;">Klik untuk ganti file</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="form-actions-bar">
            <button type="submit" class="btn-primary">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Simpan &amp; Publikasikan Berita
            </button>
            <a href="index.php" class="btn-secondary">Batal</a>
          </div>
        </form>
      </div>

    <!-- ============================================== -->
    <!-- TAMPILAN 2: FORM EDIT BERITA -->
    <!-- ============================================== -->
    <?php elseif ($action === 'edit' && $data_edit): ?>
      <div class="content-box">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit Berita
          </div>
          <a href="index.php" class="btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Kembali ke Daftar
          </a>
        </div>

        <form action="index.php" method="POST" enctype="multipart/form-data" class="form-container">
          <input type="hidden" name="proses_edit" value="1">
          <input type="hidden" name="id" value="<?= (int)$data_edit['id'] ?>">

          <div class="form-grid">
            <div>
              <div class="form-group">
                <label class="form-label" for="judul">Judul Berita <span class="req">*</span></label>
                <input type="text" id="judul" name="judul" class="form-input" value="<?= htmlspecialchars($data_edit['judul']) ?>" required autofocus>
              </div>

              <div class="form-group">
                <label class="form-label" for="isi">Isi Berita <span class="req">*</span></label>
                <textarea id="isi" name="isi" class="form-textarea" required><?= htmlspecialchars($data_edit['isi']) ?></textarea>
                <div class="form-help">Format teks mendukung paragraf terpisah secara otomatis.</div>
              </div>
            </div>

            <div>
              <div class="form-group">
                <label class="form-label" for="penulis">Nama Penulis / Tim Redaksi <span class="req">*</span></label>
                <input type="text" id="penulis" name="penulis" class="form-input" value="<?= htmlspecialchars($data_edit['penulis']) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Gambar Thumbnail Berita</label>
                <div class="upload-box" id="dropArea">
                  <input type="file" id="gambarInput" name="gambar" accept=".jpg,.jpeg,.png,.webp" onchange="previewImage(this)">
                  
                  <?php if (!empty($data_edit['gambar'])): ?>
                    <div id="uploadPlaceholder" style="display:none;">
                      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--gold);margin:0 auto 10px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                      </svg>
                      <p style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Klik untuk ganti gambar</p>
                    </div>
                    <div class="upload-preview-area" id="previewArea" style="display:block;">
                      <img id="imgPreview" src="../uploads/berita/<?= htmlspecialchars($data_edit['gambar']) ?>" alt="Thumbnail Berita">
                      <p style="font-size:0.8rem;color:var(--primary);margin-top:8px;font-weight:600;">Klik untuk mengganti gambar saat ini</p>
                    </div>
                  <?php else: ?>
                    <div id="uploadPlaceholder">
                      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--gold);margin:0 auto 10px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                      </svg>
                      <p style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Klik atau Tarik Gambar ke Sini</p>
                      <p style="font-size:0.75rem;color:var(--gray-600);">JPG, JPEG, PNG, atau WEBP (Maks 5MB)</p>
                    </div>
                    <div class="upload-preview-area" id="previewArea" style="display:none;">
                      <img id="imgPreview" src="" alt="Preview">
                      <p style="font-size:0.8rem;color:var(--primary);margin-top:8px;font-weight:600;">Klik untuk ganti file</p>
                    </div>
                  <?php endif; ?>

                </div>
                <div class="form-help">Biarkan kosong jika tidak ingin mengubah gambar thumbnail yang sudah ada.</div>
              </div>
            </div>
          </div>

          <div class="form-actions-bar">
            <button type="submit" class="btn-primary">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Simpan Perubahan Berita
            </button>
            <a href="index.php" class="btn-secondary">Batal</a>
          </div>
        </form>
      </div>

    <!-- ============================================== -->
    <!-- TAMPILAN 3: DAFTAR BERITA (READ / LIST) -->
    <!-- ============================================== -->
    <?php else: ?>
      <div class="content-box">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            Daftar Berita &amp; Publikasi
          </div>
          <a href="index.php?action=tambah" class="btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="16"></line>
              <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            Tambah Berita Baru
          </a>
        </div>

        <div class="filter-bar">
          <div class="search-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="searchInput" placeholder="Cari judul berita atau penulis..." onkeyup="filterTable()">
          </div>
          <span style="font-size:0.85rem;color:var(--gray-600);">
            Menampilkan <strong><?= number_format($stat_berita) ?></strong> berita
          </span>
        </div>

        <div class="table-responsive">
          <table class="table-news" id="newsTable">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">No</th>
                <th style="width: 90px; text-align: center;">Thumbnail</th>
                <th>Judul &amp; URL Slug</th>
                <th style="width: 170px;">Penulis</th>
                <th style="width: 150px;">Tanggal Terbit</th>
                <th style="width: 160px; text-align: center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sql = "SELECT * FROM berita ORDER BY tanggal_dibuat DESC, id DESC";
              $list_berita = $conn->query($sql);
              $no = 1;

              if ($list_berita && $list_berita->num_rows > 0):
                while ($row = $list_berita->fetch_assoc()):
              ?>
                <tr>
                  <td style="text-align: center; color: var(--gray-600); font-weight: 600;"><?= $no++ ?></td>
                  <td style="text-align: center;">
                    <?php if (!empty($row['gambar']) && file_exists($upload_dir . $row['gambar'])): ?>
                      <img src="../uploads/berita/<?= htmlspecialchars($row['gambar']) ?>" alt="Thumbnail" class="news-thumbnail" onclick="openImageModal(this.src)" title="Klik untuk memperbesar">
                    <?php else: ?>
                      <div class="news-no-img">No Image</div>
                    <?php endif; ?>
                  </td>
                  <td class="news-title-cell">
                    <a href="../detail-berita.php?id=<?= $row['id'] ?>" target="_blank" class="news-title-link" title="<?= htmlspecialchars($row['judul']) ?>">
                      <?= htmlspecialchars($row['judul']) ?>
                    </a>
                    <span class="news-slug-badge">Slug: /berita/<?= htmlspecialchars($row['slug']) ?></span>
                  </td>
                  <td>
                    <span class="badge-author"><?= htmlspecialchars($row['penulis']) ?></span>
                  </td>
                  <td style="font-size: 0.85rem; color: var(--gray-600);">
                    <?= format_tanggal_indo($row['tanggal_dibuat']) ?>
                  </td>
                  <td style="text-align: center;">
                    <div class="action-buttons" style="justify-content: center;">
                      <a href="index.php?action=edit&id=<?= $row['id'] ?>" class="btn-action-edit" title="Edit Berita">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit
                      </a>
                      <a href="index.php?action=hapus&id=<?= $row['id'] ?>" class="btn-action-delete" title="Hapus Berita" onclick="return confirm('Apakah Anda yakin ingin menghapus berita ini secara permanen? File gambar juga akan dihapus.');">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          <line x1="10" y1="11" x2="10" y2="17"></line>
                          <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                        Hapus
                      </a>
                    </div>
                  </td>
                </tr>
              <?php
                endwhile;
              else:
              ?>
                <tr>
                  <td colspan="6">
                    <div class="empty-state">
                      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1"></path>
                        <path d="M19 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-5"></path>
                      </svg>
                      <h3 style="margin-bottom:6px;font-size:1.1rem;color:var(--gray-800);">Belum Ada Berita</h3>
                      <p style="font-size:0.88rem;color:var(--gray-600);margin-bottom:16px;">Mulai publikasikan berita dan informasi terkini Rutan Tanjung sekarang.</p>
                      <a href="index.php?action=tambah" class="btn-primary" style="display:inline-flex;">Tambah Berita Baru</a>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
    <?php endif; // Akhir Tab Berita ?>

    <!-- ============================================== -->
    <!-- TAB: KELOLA JADWAL PELAYANAN -->
    <!-- ============================================== -->
    <?php if ($tab === 'jadwal'): ?>

      <!-- 1. FORM TAMBAH / EDIT JADWAL -->
      <div class="content-box" style="margin-bottom: 28px;">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <?= $jadwal_edit_data ? 'Edit Baris Jadwal Pelayanan' : 'Tambah Baris Jadwal Pelayanan Baru' ?>
          </div>
          <?php if ($jadwal_edit_data): ?>
            <a href="index.php?tab=jadwal" class="btn-secondary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Batal Edit (Tambah Baru)
            </a>
          <?php endif; ?>
        </div>

        <form method="POST" action="index.php?tab=jadwal" class="news-form" style="padding: 24px 28px;">
          <?php if ($jadwal_edit_data): ?>
            <input type="hidden" name="id_jadwal" value="<?= (int)$jadwal_edit_data['id'] ?>">
          <?php endif; ?>

          <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div class="form-group">
              <label class="form-label">Kategori Pelayanan <span class="required">*</span></label>
              <select name="kategori" class="form-control" required style="cursor:pointer;">
                <option value="kunjungan" <?= (($jadwal_edit_data['kategori'] ?? '') === 'kunjungan') ? 'selected' : '' ?>>Jadwal Kunjungan</option>
                <option value="penitipan" <?= (($jadwal_edit_data['kategori'] ?? '') === 'penitipan') ? 'selected' : '' ?>>Penitipan Barang / Makanan</option>
              </select>
              <div class="form-help">Pilih kategori kartu pelayanan yang dituju.</div>
            </div>

            <div class="form-group">
              <label class="form-label">Hari Pelayanan <span class="required">*</span></label>
              <input type="text" name="hari" class="form-control" placeholder="Contoh: Senin – Kamis, Jum'at &amp; Sabtu" value="<?= htmlspecialchars($jadwal_edit_data['hari'] ?? '') ?>" required>
              <div class="form-help">Contoh: Senin – Kamis, Jum'at &amp; Sabtu, Minggu &amp; Hari Libur.</div>
            </div>

            <div class="form-group">
              <label class="form-label">Jam / Waktu Pelayanan <span class="required">*</span></label>
              <input type="text" name="waktu" class="form-control" placeholder="Contoh: 08.30 – 11.30 WITA atau Tutup" value="<?= htmlspecialchars($jadwal_edit_data['waktu'] ?? '') ?>" required>
              <div class="form-help">Ketik jam operasional, atau isi "Tutup" jika libur.</div>
            </div>

            <div class="form-group">
              <label class="form-label">Keterangan Sesi</label>
              <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Sesi Pagi, Sesi Siang, Tidak ada kunjungan" value="<?= htmlspecialchars($jadwal_edit_data['keterangan'] ?? '') ?>">
              <div class="form-help">Keterangan tambahan di bawah jam pelayanan (opsional).</div>
            </div>

            <div class="form-group">
              <label class="form-label">Nomor Urutan Tampilan</label>
              <input type="number" name="urutan" class="form-control" min="1" max="99" value="<?= htmlspecialchars($jadwal_edit_data['urutan'] ?? '1') ?>" required>
              <div class="form-help">Angka urutan posisi baris (1 = baris teratas).</div>
            </div>

            <div class="form-group" style="display:flex; flex-direction:column; justify-content:center;">
              <label class="form-label">Opsi Status Tutup</label>
              <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px 14px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px;">
                <input type="checkbox" name="is_tutup" value="1" <?= (!empty($jadwal_edit_data['is_tutup'])) ? 'checked' : '' ?> style="width:18px; height:18px; accent-color:var(--danger); cursor:pointer;">
                <span style="font-weight:600; font-size:0.9rem; color:var(--gray-800);">Tandai sebagai Status <strong>TUTUP</strong> (badge redup/abu-abu)</span>
              </label>
              <div class="form-help">Centang jika baris ini menyatakan pelayanan libur/tutup.</div>
            </div>
          </div>

          <div class="form-actions-bar">
            <?php if ($jadwal_edit_data): ?>
              <button type="submit" name="proses_edit_jadwal" class="btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                  <polyline points="17 21 17 13 7 13 7 21"></polyline>
                  <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Simpan Perubahan Jadwal
              </button>
              <a href="index.php?tab=jadwal" class="btn-secondary">Batal</a>
            <?php else: ?>
              <button type="submit" name="proses_tambah_jadwal" class="btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 5v14M5 12h14"></path>
                </svg>
                Tambah Baris Jadwal
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- 2. TABEL DAFTAR JADWAL KUNJUNGAN -->
      <div class="content-box" style="margin-bottom: 28px;">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            Daftar Jadwal Kunjungan (<?= count($list_jadwal_kunjungan) ?> Baris)
          </div>
        </div>

        <div class="table-responsive">
          <table class="table-news">
            <thead>
              <tr>
                <th style="width:60px; text-align:center;">Urutan</th>
                <th>Hari</th>
                <th>Jam / Waktu Pelayanan</th>
                <th>Keterangan Sesi</th>
                <th style="width:120px; text-align:center;">Status</th>
                <th style="width:140px; text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($list_jadwal_kunjungan)): ?>
                <?php foreach ($list_jadwal_kunjungan as $jd): ?>
                  <tr>
                    <td style="text-align:center; font-weight:700; color:var(--primary);"><?= (int)$jd['urutan'] ?></td>
                    <td><strong><?= htmlspecialchars($jd['hari']) ?></strong></td>
                    <td><span style="font-family:monospace; font-size:0.95rem; font-weight:600;"><?= htmlspecialchars($jd['waktu']) ?></span></td>
                    <td style="color:var(--gray-600);"><?= !empty($jd['keterangan']) ? htmlspecialchars($jd['keterangan']) : '—' ?></td>
                    <td style="text-align:center;">
                      <?php if ((int)$jd['is_tutup'] === 1): ?>
                        <span class="badge-jadwal-tutup">TUTUP</span>
                      <?php else: ?>
                        <span class="badge-jadwal-buka">BUKA</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <div class="table-actions" style="justify-content:center;">
                        <a href="index.php?tab=jadwal&edit_jadwal=<?= (int)$jd['id'] ?>" class="btn-action edit" title="Edit Baris Jadwal">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                          </svg>
                          Edit
                        </a>
                        <a href="index.php?tab=jadwal&hapus_jadwal=<?= (int)$jd['id'] ?>" class="btn-action delete" title="Hapus Baris Jadwal" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal <?= htmlspecialchars($jd['hari']) ?>?');">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          </svg>
                          Hapus
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center; padding:30px; color:var(--gray-600);">Belum ada jadwal kunjungan yang diatur. Silakan tambah baris jadwal baru di atas.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 3. TABEL DAFTAR JADWAL PENITIPAN BARANG / MAKANAN -->
      <div class="content-box" style="margin-bottom: 28px;">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
            Daftar Jadwal Penitipan Barang / Makanan (<?= count($list_jadwal_penitipan) ?> Baris)
          </div>
        </div>

        <div class="table-responsive">
          <table class="table-news">
            <thead>
              <tr>
                <th style="width:60px; text-align:center;">Urutan</th>
                <th>Hari</th>
                <th>Jam / Waktu Pelayanan</th>
                <th>Keterangan Sesi</th>
                <th style="width:120px; text-align:center;">Status</th>
                <th style="width:140px; text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($list_jadwal_penitipan)): ?>
                <?php foreach ($list_jadwal_penitipan as $jd): ?>
                  <tr>
                    <td style="text-align:center; font-weight:700; color:var(--primary);"><?= (int)$jd['urutan'] ?></td>
                    <td><strong><?= htmlspecialchars($jd['hari']) ?></strong></td>
                    <td><span style="font-family:monospace; font-size:0.95rem; font-weight:600;"><?= htmlspecialchars($jd['waktu']) ?></span></td>
                    <td style="color:var(--gray-600);"><?= !empty($jd['keterangan']) ? htmlspecialchars($jd['keterangan']) : '—' ?></td>
                    <td style="text-align:center;">
                      <?php if ((int)$jd['is_tutup'] === 1): ?>
                        <span class="badge-jadwal-tutup">TUTUP</span>
                      <?php else: ?>
                        <span class="badge-jadwal-buka">BUKA</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <div class="table-actions" style="justify-content:center;">
                        <a href="index.php?tab=jadwal&edit_jadwal=<?= (int)$jd['id'] ?>" class="btn-action edit" title="Edit Baris Jadwal">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                          </svg>
                          Edit
                        </a>
                        <a href="index.php?tab=jadwal&hapus_jadwal=<?= (int)$jd['id'] ?>" class="btn-action delete" title="Hapus Baris Jadwal" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal <?= htmlspecialchars($jd['hari']) ?>?');">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          </svg>
                          Hapus
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center; padding:30px; color:var(--gray-600);">Belum ada jadwal penitipan yang diatur. Silakan tambah baris jadwal baru di atas.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. FORM EDIT CATATAN KETENTUAN PELAYANAN -->
      <div class="content-box">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            Catatan Ketentuan Pelayanan (Kunjungan &amp; Penitipan)
          </div>
        </div>

        <form method="POST" action="index.php?tab=jadwal" class="news-form" style="padding: 24px 28px;">
          <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label">Teks Catatan di Bawah Kartu Jadwal Kunjungan</label>
            <textarea name="catatan_kunjungan" class="form-control" rows="2" placeholder="Tuliskan catatan atau himbauan terkait kunjungan..." required><?= htmlspecialchars($catatan_kunjungan_val) ?></textarea>
            <div class="form-help">Teks ini akan ditampilkan dengan ikon informasi di bawah baris jadwal kunjungan pada halaman utama.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Teks Catatan di Bawah Kartu Penitipan Barang / Makanan</label>
            <textarea name="catatan_penitipan" class="form-control" rows="2" placeholder="Tuliskan catatan atau himbauan terkait penitipan..." required><?= htmlspecialchars($catatan_penitipan_val) ?></textarea>
            <div class="form-help">Teks ini akan ditampilkan dengan ikon informasi di bawah baris jadwal penitipan pada halaman utama.</div>
          </div>

          <div class="form-actions-bar">
            <button type="submit" name="proses_simpan_catatan" class="btn-primary">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              Simpan Catatan Ketentuan
            </button>
          </div>
        </form>
      </div>

    <?php endif; // Akhir Tab Jadwal ?>

  </main>

  <!-- Modal Preview Gambar Full Size -->
  <div class="admin-modal-overlay" id="imageModal" onclick="closeImageModal()">
    <img src="" alt="Full Preview" class="admin-modal-img" id="modalImg">
  </div>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> Rumah Tahanan Negara Tanjung Kelas IIB &bull; Ditjen Pemasyarakatan &bull; Kemenimipas RI</p>
  </footer>

  <script>
    function previewImage(input) {
      const previewArea = document.getElementById('previewArea');
      const placeholder = document.getElementById('uploadPlaceholder');
      const img = document.getElementById('imgPreview');

      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          img.src = e.target.result;
          previewArea.style.display = 'block';
          if (placeholder) placeholder.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
      }
    }

    function openImageModal(src) {
      const modal = document.getElementById('imageModal');
      const modalImg = document.getElementById('modalImg');
      modalImg.src = src;
      modal.classList.add('active');
    }

    function closeImageModal() {
      document.getElementById('imageModal').classList.remove('active');
    }

    function filterTable() {
      const input = document.getElementById('searchInput');
      const filter = input.value.toLowerCase();
      const table = document.getElementById('newsTable');
      const trs = table.getElementsByTagName('tr');

      for (let i = 1; i < trs.length; i++) {
        const tdTitle = trs[i].getElementsByTagName('td')[2];
        const tdAuthor = trs[i].getElementsByTagName('td')[3];
        if (tdTitle || tdAuthor) {
          const txtTitle = tdTitle ? (tdTitle.textContent || tdTitle.innerText).toLowerCase() : '';
          const txtAuthor = tdAuthor ? (tdAuthor.textContent || tdAuthor.innerText).toLowerCase() : '';
          if (txtTitle.indexOf(filter) > -1 || txtAuthor.indexOf(filter) > -1) {
            trs[i].style.display = '';
          } else {
            trs[i].style.display = 'none';
          }
        }
      }
    }
  </script>
</body>
</html>
