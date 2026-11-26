<?php
/**
 * admin/index.php
 * Dashboard Admin & CRUD Manajemen Berita Rutan Kelas IV Tanjung
 */
session_start();

// Cegah caching browser pada panel admin agar perubahan data langsung terlihat real-time
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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

/**
 * Format ukuran file menjadi KB atau MB yang mudah dibaca.
 */
if (!function_exists('format_ukuran_file')) {
    function format_ukuran_file($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        return max(1, round($bytes / 1024)) . ' KB';
    }
}

/**
 * Membuat kanvas GD ter-resize proporsional dengan menjaga transparansi (PNG/WebP).
 */
if (!function_exists('buat_kanvas_gambar_proporsional')) {
    function buat_kanvas_gambar_proporsional($src_img, $orig_w, $orig_h, $max_dim, $preserve_alpha = false) {
        if ($orig_w > $max_dim || $orig_h > $max_dim) {
            if ($orig_w >= $orig_h) {
                $new_w = $max_dim;
                $new_h = max(1, (int)round($orig_h * ($max_dim / $orig_w)));
            } else {
                $new_h = $max_dim;
                $new_w = max(1, (int)round($orig_w * ($max_dim / $orig_h)));
            }
        } else {
            $new_w = $orig_w;
            $new_h = $orig_h;
        }

        $dst = imagecreatetruecolor($new_w, $new_h);
        if ($preserve_alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);
        }
        imagecopyresampled($dst, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        return $dst;
    }
}

/**
 * Validasi, upload, dan kompres otomatis gambar agar ukuran akhir selalu di bawah 1MB.
 */
if (!function_exists('proses_upload_dan_kompres_gambar')) {
    function proses_upload_dan_kompres_gambar($file, $dest_dir, $prefix = 'img', $max_input_bytes = 20971520, $target_bytes = 980000) {
        if (!isset($file) || !is_array($file)) {
            return ['success' => false, 'error' => 'File gambar tidak ditemukan.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                return ['success' => false, 'error' => 'Ukuran file melebihi batas maksimal server.'];
            }
            return ['success' => false, 'error' => 'Terjadi kesalahan saat mengunggah file gambar.'];
        }

        $file_tmp  = $file['tmp_name'];
        $file_name = $file['name'];
        $file_size = (int)$file['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed, true)) {
            return ['success' => false, 'error' => 'Format gambar tidak valid! Gunakan format JPG, JPEG, PNG, atau WEBP.'];
        }

        if ($file_size > $max_input_bytes) {
            $max_mb = round($max_input_bytes / (1024 * 1024));
            return ['success' => false, 'error' => "Ukuran file input terlalu besar! Maksimal {$max_mb}MB."];
        }

        $img_info = @getimagesize($file_tmp);
        if ($img_info === false) {
            return ['success' => false, 'error' => 'File yang diunggah bukan gambar yang valid.'];
        }

        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }

        $base_name = $prefix . '-' . time() . '-' . uniqid();

        $needs_exif_rotate = false;
        if (in_array($file_ext, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file_tmp);
            if (!empty($exif['Orientation']) && in_array((int)$exif['Orientation'], [3, 6, 8], true)) {
                $needs_exif_rotate = true;
            }
        }

        // Jika sudah di bawah 1MB dan tidak perlu koreksi orientasi EXIF, simpan langsung
        if ($file_size <= $target_bytes && !$needs_exif_rotate) {
            $final_name = $base_name . '.' . $file_ext;
            $dest_path  = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $final_name;
            $moved = is_uploaded_file($file_tmp) ? move_uploaded_file($file_tmp, $dest_path) : copy($file_tmp, $dest_path);
            if (!$moved) {
                return ['success' => false, 'error' => 'Gagal mengunggah file gambar ke server.'];
            }
            return [
                'success'       => true,
                'filename'      => $final_name,
                'compressed'    => false,
                'original_size' => $file_size,
                'final_size'    => filesize($dest_path),
            ];
        }

        if (!extension_loaded('gd')) {
            return ['success' => false, 'error' => 'Ekstensi PHP GD belum aktif di server untuk kompresi gambar.'];
        }

        $mime = $img_info['mime'] ?? '';
        $src_img = null;
        if ($mime === 'image/jpeg') {
            $src_img = @imagecreatefromjpeg($file_tmp);
        } elseif ($mime === 'image/png') {
            $src_img = @imagecreatefrompng($file_tmp);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $src_img = @imagecreatefromwebp($file_tmp);
        }

        if (!$src_img) {
            return ['success' => false, 'error' => 'Gagal membaca gambar untuk dikompres.'];
        }

        if ($mime === 'image/jpeg' && $needs_exif_rotate) {
            $exif = @exif_read_data($file_tmp);
            if (!empty($exif['Orientation'])) {
                switch ((int)$exif['Orientation']) {
                    case 3: $src_img = imagerotate($src_img, 180, 0); break;
                    case 6: $src_img = imagerotate($src_img, -90, 0); break;
                    case 8: $src_img = imagerotate($src_img, 90, 0); break;
                }
            }
        }

        $orig_w     = imagesx($src_img);
        $orig_h     = imagesy($src_img);
        $save_ok    = false;
        $final_name = '';
        $dest_path  = '';

        if ($mime === 'image/png') {
            // Tahap 1: Kompres PNG sambil pertahankan alpha transparency
            foreach ([1600, 1280, 1024] as $max_dim) {
                $canvas   = buat_kanvas_gambar_proporsional($src_img, $orig_w, $orig_h, $max_dim, true);
                $try_name = $base_name . '.png';
                $try_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $try_name;
                imagepng($canvas, $try_path, 9);
                imagedestroy($canvas);
                if (file_exists($try_path) && filesize($try_path) <= $target_bytes) {
                    $save_ok    = true;
                    $final_name = $try_name;
                    $dest_path  = $try_path;
                    break;
                }
                if (file_exists($try_path)) @unlink($try_path);
            }

            // Tahap 2: Jika PNG resolusi besar masih > 1MB, konversi ke WebP (transparansi tetap terjaga)
            if (!$save_ok && function_exists('imagewebp')) {
                foreach ([1600, 1280, 960, 720] as $max_dim) {
                    $canvas = buat_kanvas_gambar_proporsional($src_img, $orig_w, $orig_h, $max_dim, true);
                    foreach ([85, 75, 65, 55, 45] as $q) {
                        $try_name = $base_name . '.webp';
                        $try_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $try_name;
                        imagewebp($canvas, $try_path, $q);
                        if (file_exists($try_path) && filesize($try_path) <= $target_bytes) {
                            $save_ok    = true;
                            $final_name = $try_name;
                            $dest_path  = $try_path;
                            imagedestroy($canvas);
                            break 2;
                        }
                        if (file_exists($try_path)) @unlink($try_path);
                    }
                    imagedestroy($canvas);
                }
            }

            // Tahap 3 fallback jika tidak ada WebP
            if (!$save_ok) {
                foreach ([800, 640, 480] as $max_dim) {
                    $canvas   = buat_kanvas_gambar_proporsional($src_img, $orig_w, $orig_h, $max_dim, true);
                    $try_name = $base_name . '.png';
                    $try_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $try_name;
                    imagepng($canvas, $try_path, 9);
                    imagedestroy($canvas);
                    if (file_exists($try_path) && (filesize($try_path) <= $target_bytes || $max_dim === 480)) {
                        $save_ok    = true;
                        $final_name = $try_name;
                        $dest_path  = $try_path;
                        break;
                    }
                    if (file_exists($try_path)) @unlink($try_path);
                }
            }
        } else {
            // JPG / JPEG / WebP: resize proporsional + turunkan kualitas bertahap hingga < 1MB
            $is_webp = ($mime === 'image/webp' && function_exists('imagewebp'));
            $out_ext = $is_webp ? 'webp' : 'jpg';
            $dims    = [1920, 1440, 1080, 800, 600];
            $quals   = [85, 76, 68, 58, 48, 38];

            foreach ($dims as $max_dim) {
                $canvas = buat_kanvas_gambar_proporsional($src_img, $orig_w, $orig_h, $max_dim, $is_webp);
                foreach ($quals as $q) {
                    $try_name = $base_name . '.' . $out_ext;
                    $try_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $try_name;
                    if ($is_webp) {
                        imagewebp($canvas, $try_path, $q);
                    } else {
                        imagejpeg($canvas, $try_path, $q);
                    }
                    if (file_exists($try_path) && (filesize($try_path) <= $target_bytes || ($max_dim === 600 && $q === 38))) {
                        $save_ok    = true;
                        $final_name = $try_name;
                        $dest_path  = $try_path;
                        imagedestroy($canvas);
                        break 2;
                    }
                    if (file_exists($try_path)) @unlink($try_path);
                }
                imagedestroy($canvas);
            }
        }

        imagedestroy($src_img);

        if (!$save_ok || empty($dest_path) || !file_exists($dest_path)) {
            return ['success' => false, 'error' => 'Gagal mengompres dan menyimpan file gambar ke server.'];
        }

        return [
            'success'       => true,
            'filename'      => $final_name,
            'compressed'    => ($file_size > $target_bytes),
            'original_size' => $file_size,
            'final_size'    => filesize($dest_path),
        ];
    }
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

    // Proses upload gambar (otomatis kompres < 1MB)
    $nama_file_gambar = null;
    $info_kompresi = '';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $res_upload = proses_upload_dan_kompres_gambar($_FILES['gambar'], $upload_dir, 'berita');
        if (!$res_upload['success']) {
            $_SESSION['flash_error'] = $res_upload['error'];
            header("Location: index.php?action=tambah");
            exit;
        }
        $nama_file_gambar = $res_upload['filename'];
        if (!empty($res_upload['compressed'])) {
            $info_kompresi = ' (Foto otomatis dikompres dari ' . format_ukuran_file($res_upload['original_size']) . ' menjadi ' . format_ukuran_file($res_upload['final_size']) . ')';
        }
    }

    // Simpan ke database dengan Prepared Statement
    $stmt = $conn->prepare("INSERT INTO berita (judul, slug, isi, gambar, penulis, tanggal_dibuat) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $judul, $slug, $isi, $nama_file_gambar, $penulis);

    if ($stmt->execute()) {
        $_SESSION['flash_sukses'] = 'Berita baru berhasil ditambahkan!' . $info_kompresi;
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
    $info_kompresi = '';

    // Cek apakah ada upload gambar baru (otomatis kompres < 1MB)
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $res_upload = proses_upload_dan_kompres_gambar($_FILES['gambar'], $upload_dir, 'berita');
        if (!$res_upload['success']) {
            $_SESSION['flash_error'] = $res_upload['error'];
            header("Location: index.php?action=edit&id=" . $id);
            exit;
        }
        // Hapus file gambar lama jika ada dan file fisiknya ada di folder uploads/berita
        if (!empty($nama_file_gambar) && file_exists($upload_dir . $nama_file_gambar)) {
            @unlink($upload_dir . $nama_file_gambar);
        }
        $nama_file_gambar = $res_upload['filename'];
        if (!empty($res_upload['compressed'])) {
            $info_kompresi = ' (Foto otomatis dikompres dari ' . format_ukuran_file($res_upload['original_size']) . ' menjadi ' . format_ukuran_file($res_upload['final_size']) . ')';
        }
    }

    // Simpan perubahan ke database dengan Prepared Statement
    $stmt = $conn->prepare("UPDATE berita SET judul = ?, slug = ?, isi = ?, gambar = ?, penulis = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $judul, $slug, $isi, $nama_file_gambar, $penulis, $id);

    if ($stmt->execute()) {
        $_SESSION['flash_sukses'] = 'Berita berhasil diperbarui!' . $info_kompresi;
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
$id_hapus = 0;
if ($action === 'hapus' && $edit_id > 0) {
    $id_hapus = $edit_id;
} elseif (isset($_GET['hapus_berita']) && (int)$_GET['hapus_berita'] > 0) {
    $id_hapus = (int)$_GET['hapus_berita'];
} elseif (isset($_GET['hapus']) && (int)$_GET['hapus'] > 0) {
    $id_hapus = (int)$_GET['hapus'];
} elseif (isset($_POST['hapus_id']) && (int)$_POST['hapus_id'] > 0) {
    $id_hapus = (int)$_POST['hapus_id'];
}

if ($id_hapus > 0) {
    // Ambil info gambar sebelum menghapus dari database
    $get_img = $conn->prepare("SELECT gambar FROM berita WHERE id = ? LIMIT 1");
    $get_img->bind_param("i", $id_hapus);
    $get_img->execute();
    $img_res = $get_img->get_result();
    $data_img = $img_res->fetch_assoc();
    $get_img->close();

    if ($data_img !== null) {
        $stmt = $conn->prepare("DELETE FROM berita WHERE id = ?");
        $stmt->bind_param("i", $id_hapus);
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
        $_SESSION['flash_error'] = 'Data berita tidak ditemukan atau sudah dihapus!';
    }
    header("Location: index.php?tab=berita&t=" . time());
    exit;
}

// Ambil total berita, pejabat struktural & pop-up ads untuk statistik
$stat_berita = $conn->query("SELECT COUNT(*) as total FROM berita")->fetch_assoc()['total'] ?? 0;
$stat_popup_aktif = $conn->query("SELECT COUNT(*) as total FROM popup_ads WHERE is_aktif = 1")->fetch_assoc()['total'] ?? 0;
$stat_popup_total = $conn->query("SELECT COUNT(*) as total FROM popup_ads")->fetch_assoc()['total'] ?? 0;
$stat_pejabat_aktif = 0;
$stat_pejabat_total = 0;
if ($chk_pj = $conn->query("SHOW TABLES LIKE 'pejabat_struktural'")) {
    if ($chk_pj->num_rows > 0) {
        $stat_pejabat_aktif = $conn->query("SELECT COUNT(*) as total FROM pejabat_struktural WHERE is_aktif = 1")->fetch_assoc()['total'] ?? 0;
        $stat_pejabat_total = $conn->query("SELECT COUNT(*) as total FROM pejabat_struktural")->fetch_assoc()['total'] ?? 0;
    }
}

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
// PENGATURAN TAB (berita | jadwal | popup | pejabat)
// ==========================================
$tab = $_GET['tab'] ?? 'berita';

// ==========================================
// PROSES CRUD: PEJABAT STRUKTURAL
// ==========================================
$pejabat_upload_dir = __DIR__ . '/../uploads/pejabat/';
if (!is_dir($pejabat_upload_dir)) {
    mkdir($pejabat_upload_dir, 0755, true);
}

// Pastikan tabel pejabat_struktural tersedia
$conn->query("CREATE TABLE IF NOT EXISTS `pejabat_struktural` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (!function_exists('normalisasi_urutan_pejabat')) {
    function normalisasi_urutan_pejabat($conn) {
        foreach ([1, 0] as $kepala_flag) {
            $stmt_sel = $conn->prepare("SELECT id FROM pejabat_struktural WHERE is_kepala = ? ORDER BY urutan ASC, id ASC");
            $stmt_sel->bind_param("i", $kepala_flag);
            $stmt_sel->execute();
            $res = $stmt_sel->get_result();
            if ($res) {
                $seq = 1;
                $stmt_u = $conn->prepare("UPDATE pejabat_struktural SET urutan = ? WHERE id = ?");
                while ($row = $res->fetch_assoc()) {
                    $stmt_u->bind_param("ii", $seq, $row['id']);
                    $stmt_u->execute();
                    $seq++;
                }
                $stmt_u->close();
            }
            $stmt_sel->close();
        }
    }
}

// Tambah Pejabat Struktural
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_tambah_pejabat'])) {
    $pj_nama      = trim($_POST['pejabat_nama'] ?? '');
    $pj_jabatan   = trim($_POST['pejabat_jabatan'] ?? '');
    $pj_deskripsi = trim($_POST['pejabat_deskripsi'] ?? '');
    $pj_is_kepala = (isset($_POST['pejabat_is_kepala']) && (int)$_POST['pejabat_is_kepala'] === 1) ? 1 : 0;
    $pj_is_aktif  = isset($_POST['pejabat_is_aktif']) ? 1 : 0;
    $pj_urutan    = max(1, (int)($_POST['pejabat_urutan'] ?? 1));

    if (empty($pj_nama) || empty($pj_jabatan)) {
        $_SESSION['flash_error'] = 'Nama lengkap dan jabatan pejabat wajib diisi!';
        header("Location: index.php?tab=pejabat");
        exit;
    }

    $foto_path = '';
    $info_kompresi = '';
    if (isset($_FILES['pejabat_foto']) && $_FILES['pejabat_foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $res_upload = proses_upload_dan_kompres_gambar($_FILES['pejabat_foto'], $pejabat_upload_dir, 'pejabat');
        if (!$res_upload['success']) {
            $_SESSION['flash_error'] = $res_upload['error'];
            header("Location: index.php?tab=pejabat");
            exit;
        }
        $foto_path = 'uploads/pejabat/' . $res_upload['filename'];
        if (!empty($res_upload['compressed'])) {
            $info_kompresi = ' (Foto otomatis dikompres dari ' . format_ukuran_file($res_upload['original_size']) . ' menjadi ' . format_ukuran_file($res_upload['final_size']) . ')';
        }
    }

    // Geser urutan di kategori yang sama
    $stmt_shift = $conn->prepare("UPDATE pejabat_struktural SET urutan = urutan + 1 WHERE is_kepala = ? AND urutan >= ?");
    $stmt_shift->bind_param("ii", $pj_is_kepala, $pj_urutan);
    $stmt_shift->execute();
    $stmt_shift->close();

    $stmt_ins = $conn->prepare("INSERT INTO pejabat_struktural (nama, jabatan, deskripsi, foto, is_kepala, is_aktif, urutan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("ssssiii", $pj_nama, $pj_jabatan, $pj_deskripsi, $foto_path, $pj_is_kepala, $pj_is_aktif, $pj_urutan);
    if ($stmt_ins->execute()) {
        $stmt_ins->close();
        normalisasi_urutan_pejabat($conn);
        $_SESSION['flash_sukses'] = 'Data pejabat struktural baru berhasil ditambahkan!' . $info_kompresi;
    } else {
        $stmt_ins->close();
        $_SESSION['flash_error'] = 'Gagal menambahkan data pejabat: ' . $conn->error;
    }
    header("Location: index.php?tab=pejabat&t=" . time());
    exit;
}

// Edit Pejabat Struktural
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_edit_pejabat'])) {
    $pj_id        = (int)($_POST['pejabat_id'] ?? 0);
    $pj_nama      = trim($_POST['pejabat_nama'] ?? '');
    $pj_jabatan   = trim($_POST['pejabat_jabatan'] ?? '');
    $pj_deskripsi = trim($_POST['pejabat_deskripsi'] ?? '');
    $pj_is_kepala = (isset($_POST['pejabat_is_kepala']) && (int)$_POST['pejabat_is_kepala'] === 1) ? 1 : 0;
    $pj_is_aktif  = isset($_POST['pejabat_is_aktif']) ? 1 : 0;
    $pj_urutan    = max(1, (int)($_POST['pejabat_urutan'] ?? 1));

    if ($pj_id <= 0 || empty($pj_nama) || empty($pj_jabatan)) {
        $_SESSION['flash_error'] = 'Data pejabat tidak lengkap atau tidak valid!';
        header("Location: index.php?tab=pejabat");
        exit;
    }

    $stmt_old = $conn->prepare("SELECT foto, urutan, is_kepala FROM pejabat_struktural WHERE id = ? LIMIT 1");
    $stmt_old->bind_param("i", $pj_id);
    $stmt_old->execute();
    $old_pj = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    if (!$old_pj) {
        $_SESSION['flash_error'] = 'Data pejabat yang ingin diedit tidak ditemukan!';
        header("Location: index.php?tab=pejabat");
        exit;
    }

    $foto_path     = $old_pj['foto'] ?? '';
    $old_urutan    = (int)$old_pj['urutan'];
    $old_is_kepala = (int)$old_pj['is_kepala'];
    $info_kompresi = '';

    if (isset($_FILES['pejabat_foto']) && $_FILES['pejabat_foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $res_upload = proses_upload_dan_kompres_gambar($_FILES['pejabat_foto'], $pejabat_upload_dir, 'pejabat');
        if (!$res_upload['success']) {
            $_SESSION['flash_error'] = $res_upload['error'];
            header("Location: index.php?tab=pejabat&edit_pejabat=" . $pj_id);
            exit;
        }
        if (!empty($foto_path) && str_starts_with($foto_path, 'uploads/pejabat/')) {
            $old_file = __DIR__ . '/../' . $foto_path;
            if (file_exists($old_file)) @unlink($old_file);
        }
        $foto_path = 'uploads/pejabat/' . $res_upload['filename'];
        if (!empty($res_upload['compressed'])) {
            $info_kompresi = ' (Foto otomatis dikompres dari ' . format_ukuran_file($res_upload['original_size']) . ' menjadi ' . format_ukuran_file($res_upload['final_size']) . ')';
        }
    }

    // Geser urutan jika berubah
    if ($pj_is_kepala === $old_is_kepala && $pj_urutan !== $old_urutan) {
        if ($pj_urutan < $old_urutan) {
            $stmt_s = $conn->prepare("UPDATE pejabat_struktural SET urutan = urutan + 1 WHERE is_kepala = ? AND urutan >= ? AND urutan < ? AND id != ?");
            $stmt_s->bind_param("iiii", $pj_is_kepala, $pj_urutan, $old_urutan, $pj_id);
            $stmt_s->execute();
            $stmt_s->close();
        } else {
            $stmt_s = $conn->prepare("UPDATE pejabat_struktural SET urutan = urutan - 1 WHERE is_kepala = ? AND urutan > ? AND urutan <= ? AND id != ?");
            $stmt_s->bind_param("iiii", $pj_is_kepala, $old_urutan, $pj_urutan, $pj_id);
            $stmt_s->execute();
            $stmt_s->close();
        }
    } elseif ($pj_is_kepala !== $old_is_kepala) {
        $stmt_s = $conn->prepare("UPDATE pejabat_struktural SET urutan = urutan + 1 WHERE is_kepala = ? AND urutan >= ? AND id != ?");
        $stmt_s->bind_param("iii", $pj_is_kepala, $pj_urutan, $pj_id);
        $stmt_s->execute();
        $stmt_s->close();
    }

    $stmt_upd = $conn->prepare("UPDATE pejabat_struktural SET nama = ?, jabatan = ?, deskripsi = ?, foto = ?, is_kepala = ?, is_aktif = ?, urutan = ? WHERE id = ?");
    $stmt_upd->bind_param("ssssiiii", $pj_nama, $pj_jabatan, $pj_deskripsi, $foto_path, $pj_is_kepala, $pj_is_aktif, $pj_urutan, $pj_id);
    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        normalisasi_urutan_pejabat($conn);
        $_SESSION['flash_sukses'] = 'Data pejabat struktural berhasil diperbarui!' . $info_kompresi;
    } else {
        $stmt_upd->close();
        $_SESSION['flash_error'] = 'Gagal memperbarui data pejabat: ' . $conn->error;
    }
    header("Location: index.php?tab=pejabat&t=" . time());
    exit;
}

// Hapus Pejabat Struktural
if (isset($_GET['hapus_pejabat']) && $tab === 'pejabat') {
    $pj_del = (int)$_GET['hapus_pejabat'];
    if ($pj_del > 0) {
        $stmt_gd = $conn->prepare("SELECT foto FROM pejabat_struktural WHERE id = ? LIMIT 1");
        $stmt_gd->bind_param("i", $pj_del);
        $stmt_gd->execute();
        $del_row = $stmt_gd->get_result()->fetch_assoc();
        $stmt_gd->close();
        if ($del_row && !empty($del_row['foto']) && str_starts_with($del_row['foto'], 'uploads/pejabat/')) {
            $del_path = __DIR__ . '/../' . $del_row['foto'];
            if (file_exists($del_path)) @unlink($del_path);
        }
        $stmt_del = $conn->prepare("DELETE FROM pejabat_struktural WHERE id = ?");
        $stmt_del->bind_param("i", $pj_del);
        if ($stmt_del->execute()) {
            $stmt_del->close();
            normalisasi_urutan_pejabat($conn);
            $_SESSION['flash_sukses'] = 'Data pejabat struktural berhasil dihapus!';
        } else {
            $stmt_del->close();
            $_SESSION['flash_error'] = 'Gagal menghapus data pejabat: ' . $conn->error;
        }
    }
    header("Location: index.php?tab=pejabat&t=" . time());
    exit;
}

// Toggle aktif/nonaktif pejabat
if (isset($_GET['toggle_pejabat']) && $tab === 'pejabat') {
    $pj_tog = (int)$_GET['toggle_pejabat'];
    if ($pj_tog > 0) {
        $stmt_tog = $conn->prepare("UPDATE pejabat_struktural SET is_aktif = 1 - is_aktif WHERE id = ?");
        $stmt_tog->bind_param("i", $pj_tog);
        $stmt_tog->execute();
        $stmt_tog->close();
        $_SESSION['flash_sukses'] = 'Status tampil pejabat berhasil diubah!';
    }
    header("Location: index.php?tab=pejabat&t=" . time());
    exit;
}

// Data edit pejabat & daftar semua pejabat
$pejabat_edit_data = null;
$pejabat_edit_id   = isset($_GET['edit_pejabat']) ? (int)$_GET['edit_pejabat'] : 0;
if ($pejabat_edit_id > 0) {
    $stmt_pe = $conn->prepare("SELECT * FROM pejabat_struktural WHERE id = ? LIMIT 1");
    $stmt_pe->bind_param("i", $pejabat_edit_id);
    $stmt_pe->execute();
    $pejabat_edit_data = $stmt_pe->get_result()->fetch_assoc();
    $stmt_pe->close();
    if ($pejabat_edit_data) {
        $tab = 'pejabat';
    }
}

$list_pejabat_q = $conn->query("SELECT * FROM pejabat_struktural ORDER BY is_kepala DESC, urutan ASC, id ASC");
$list_pejabat_rows = $list_pejabat_q ? $list_pejabat_q->fetch_all(MYSQLI_ASSOC) : [];

// ==========================================
// PROSES CRUD: POPUP ADS
// ==========================================
$popup_upload_dir = __DIR__ . '/../uploads/popup/';
if (!is_dir($popup_upload_dir)) {
    mkdir($popup_upload_dir, 0755, true);
}

/**
 * Normalisasi urutan pop-up ads agar berurutan 1, 2, 3... tanpa nomor duplikat atau loncat
 */
if (!function_exists('normalisasi_urutan_popup')) {
    function normalisasi_urutan_popup($conn) {
        $res = $conn->query("SELECT id FROM popup_ads ORDER BY urutan ASC, id ASC");
        if ($res) {
            $seq = 1;
            $stmt = $conn->prepare("UPDATE popup_ads SET urutan = ? WHERE id = ?");
            while ($row = $res->fetch_assoc()) {
                $stmt->bind_param("ii", $seq, $row['id']);
                $stmt->execute();
                $seq++;
            }
            $stmt->close();
        }
    }
}

// Tambah gambar popup baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_tambah_popup'])) {
    if (!isset($_FILES['popup_gambar']) || $_FILES['popup_gambar']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['flash_error'] = 'File gambar wajib diunggah!';
        header("Location: index.php?tab=popup"); exit;
    }
    $res_upload = proses_upload_dan_kompres_gambar($_FILES['popup_gambar'], $popup_upload_dir, 'popup');
    if (!$res_upload['success']) {
        $_SESSION['flash_error'] = $res_upload['error'];
        header("Location: index.php?tab=popup"); exit;
    }
    $tujuan        = $popup_upload_dir . $res_upload['filename'];
    $info_kompresi = !empty($res_upload['compressed'])
        ? ' (Gambar otomatis dikompres dari ' . format_ukuran_file($res_upload['original_size']) . ' menjadi ' . format_ukuran_file($res_upload['final_size']) . ')'
        : '';
    $link_url    = trim($_POST['popup_link_url'] ?? '');
    $keterangan  = trim($_POST['popup_keterangan'] ?? '');
    $is_aktif    = isset($_POST['popup_is_aktif']) ? 1 : 0;
    $urutan      = max(1, (int)($_POST['popup_urutan'] ?? 1));
    $gambar_path = 'uploads/popup/' . $res_upload['filename'];

    // Geser urutan yang sudah ada: jika ada urutan >= $urutan, geser +1 agar urutan sebelumnya turun
    $stmt_shift = $conn->prepare("UPDATE popup_ads SET urutan = urutan + 1 WHERE urutan >= ?");
    $stmt_shift->bind_param("i", $urutan);
    $stmt_shift->execute();
    $stmt_shift->close();

    $stmt_p = $conn->prepare("INSERT INTO popup_ads (gambar, link_url, is_aktif, urutan, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt_p->bind_param("ssiss", $gambar_path, $link_url, $is_aktif, $urutan, $keterangan);
    if ($stmt_p->execute()) {
        $stmt_p->close();
        normalisasi_urutan_popup($conn);
        $_SESSION['flash_sukses'] = 'Gambar pop-up berhasil ditambahkan pada urutan ke-' . $urutan . ' (urutan lama yang sama otomatis bergeser turun)!' . $info_kompresi;
    } else {
        $stmt_p->close();
        @unlink($tujuan);
        $_SESSION['flash_error'] = 'Gagal menyimpan ke database: ' . $conn->error;
    }
    header("Location: index.php?tab=popup"); exit;
}

// Edit popup (update link, status, urutan, keterangan — gambar opsional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_edit_popup'])) {
    $pid        = (int)($_POST['popup_id'] ?? 0);
    $link_url   = trim($_POST['popup_link_url'] ?? '');
    $keterangan = trim($_POST['popup_keterangan'] ?? '');
    $is_aktif   = isset($_POST['popup_is_aktif']) ? 1 : 0;
    $new_urutan = max(1, (int)($_POST['popup_urutan'] ?? 1));
    if ($pid <= 0) {
        $_SESSION['flash_error'] = 'ID popup tidak valid!';
        header("Location: index.php?tab=popup"); exit;
    }
    // Ambil data lama
    $stmt_old = $conn->prepare("SELECT gambar, urutan FROM popup_ads WHERE id = ? LIMIT 1");
    $stmt_old->bind_param("i", $pid);
    $stmt_old->execute();
    $old_row = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();
    if (!$old_row) {
        $_SESSION['flash_error'] = 'Data pop-up tidak ditemukan!';
        header("Location: index.php?tab=popup"); exit;
    }
    $old_urutan    = (int)$old_row['urutan'];
    $gambar_path   = $old_row['gambar'] ?? '';
    $info_kompresi = '';

    // Proses ganti gambar jika ada file baru (otomatis kompres < 1MB)
    if (isset($_FILES['popup_gambar']) && $_FILES['popup_gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $res_upload = proses_upload_dan_kompres_gambar($_FILES['popup_gambar'], $popup_upload_dir, 'popup');
        if (!$res_upload['success']) {
            $_SESSION['flash_error'] = $res_upload['error'];
            header("Location: index.php?tab=popup&edit_popup=" . $pid); exit;
        }
        // Hapus gambar lama jika dari folder uploads/popup
        if (!empty($gambar_path) && str_starts_with($gambar_path, 'uploads/popup/')) {
            $old_path = __DIR__ . '/../' . $gambar_path;
            if (file_exists($old_path)) @unlink($old_path);
        }
        $gambar_path = 'uploads/popup/' . $res_upload['filename'];
        if (!empty($res_upload['compressed'])) {
            $info_kompresi = ' (Gambar otomatis dikompres dari ' . format_ukuran_file($res_upload['original_size']) . ' menjadi ' . format_ukuran_file($res_upload['final_size']) . ')';
        }
    }

    // Geser urutan jika berubah posisi
    if ($new_urutan !== $old_urutan) {
        if ($new_urutan < $old_urutan) {
            // Pindah ke urutan lebih awal (misal dari 7 ke 3):
            // Yang berada di [new_urutan, old_urutan - 1] bergeser naik (+1)
            $stmt_s = $conn->prepare("UPDATE popup_ads SET urutan = urutan + 1 WHERE urutan >= ? AND urutan < ? AND id != ?");
            $stmt_s->bind_param("iii", $new_urutan, $old_urutan, $pid);
            $stmt_s->execute();
            $stmt_s->close();
        } else {
            // Pindah ke urutan lebih akhir (misal dari 3 ke 7):
            // Yang berada di [old_urutan + 1, new_urutan] bergeser turun (-1)
            $stmt_s = $conn->prepare("UPDATE popup_ads SET urutan = urutan - 1 WHERE urutan > ? AND urutan <= ? AND id != ?");
            $stmt_s->bind_param("iii", $old_urutan, $new_urutan, $pid);
            $stmt_s->execute();
            $stmt_s->close();
        }
    }

    $stmt_upd = $conn->prepare("UPDATE popup_ads SET gambar=?, link_url=?, is_aktif=?, urutan=?, keterangan=? WHERE id=?");
    $stmt_upd->bind_param("ssiisi", $gambar_path, $link_url, $is_aktif, $new_urutan, $keterangan, $pid);
    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        normalisasi_urutan_popup($conn);
        $_SESSION['flash_sukses'] = 'Pop-up berhasil diperbarui dan urutan otomatis diselaraskan!' . $info_kompresi;
    } else {
        $stmt_upd->close();
        $_SESSION['flash_error'] = 'Gagal memperbarui: ' . $conn->error;
    }
    header("Location: index.php?tab=popup"); exit;
}

// Hapus popup
if (isset($_GET['hapus_popup']) && $tab === 'popup') {
    $pid_del = (int)$_GET['hapus_popup'];
    if ($pid_del > 0) {
        $stmt_gd = $conn->prepare("SELECT gambar FROM popup_ads WHERE id = ? LIMIT 1");
        $stmt_gd->bind_param("i", $pid_del);
        $stmt_gd->execute();
        $del_row = $stmt_gd->get_result()->fetch_assoc();
        $stmt_gd->close();
        if ($del_row && str_starts_with($del_row['gambar'], 'uploads/popup/')) {
            $del_path = __DIR__ . '/../' . $del_row['gambar'];
            if (file_exists($del_path)) @unlink($del_path);
        }
        $stmt_del = $conn->prepare("DELETE FROM popup_ads WHERE id = ?");
        $stmt_del->bind_param("i", $pid_del);
        if ($stmt_del->execute()) {
            $stmt_del->close();
            normalisasi_urutan_popup($conn);
            $_SESSION['flash_sukses'] = 'Pop-up berhasil dihapus dan urutan diperbarui!';
        } else {
            $stmt_del->close();
            $_SESSION['flash_error'] = 'Gagal menghapus: ' . $conn->error;
        }
    }
    header("Location: index.php?tab=popup"); exit;
}

// Toggle aktif/nonaktif
if (isset($_GET['toggle_popup']) && $tab === 'popup') {
    $pid_tog = (int)$_GET['toggle_popup'];
    if ($pid_tog > 0) {
        $stmt_tog = $conn->prepare("UPDATE popup_ads SET is_aktif = 1 - is_aktif WHERE id = ?");
        $stmt_tog->bind_param("i", $pid_tog);
        $stmt_tog->execute();
        $stmt_tog->close();
        $_SESSION['flash_sukses'] = 'Status pop-up berhasil diubah!';
    }
    header("Location: index.php?tab=popup"); exit;
}

// Data edit popup
$popup_edit_data = null;
$popup_edit_id   = isset($_GET['edit_popup']) ? (int)$_GET['edit_popup'] : 0;
if ($popup_edit_id > 0 && $tab === 'popup') {
    $stmt_pe = $conn->prepare("SELECT * FROM popup_ads WHERE id = ? LIMIT 1");
    $stmt_pe->bind_param("i", $popup_edit_id);
    $stmt_pe->execute();
    $popup_edit_data = $stmt_pe->get_result()->fetch_assoc();
    $stmt_pe->close();
}

// Ambil semua popup untuk tampilan tab
normalisasi_urutan_popup($conn);
$list_popup = $conn->query("SELECT * FROM popup_ads ORDER BY urutan ASC, id ASC");
$list_popup_rows = $list_popup ? $list_popup->fetch_all(MYSQLI_ASSOC) : [];

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
      grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
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

    .upload-box.drag-over {
      border-color: var(--primary);
      background: rgba(10, 36, 99, 0.06);
      box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.2);
      transform: scale(1.01);
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

    /* Pop-up Ads Admin Specific Styles */
    .switch-toggle-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 6px;
    }
    .switch-toggle {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 26px;
      flex-shrink: 0;
    }
    .switch-toggle input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .switch-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: var(--gray-200);
      transition: .3s;
      border-radius: 30px;
    }
    .switch-slider:before {
      position: absolute;
      content: "";
      height: 20px;
      width: 20px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .3s;
      border-radius: 50%;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .switch-toggle input:checked + .switch-slider {
      background-color: var(--success);
    }
    .switch-toggle input:checked + .switch-slider:before {
      transform: translateX(24px);
    }
    .switch-label-text {
      font-size: 0.92rem;
      font-weight: 600;
      color: var(--gray-800);
    }

    /* Pop-up Preview Grid (Galeri Pratinjau Aktif) */
    .popup-preview-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 20px;
      padding: 24px;
    }
    .popup-preview-card {
      background: var(--gray-50);
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-md);
      overflow: hidden;
      position: relative;
      display: flex;
      flex-direction: column;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .popup-preview-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    .popup-preview-img-wrap {
      width: 100%;
      height: 180px;
      background: #000;
      overflow: hidden;
      position: relative;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .popup-preview-img-wrap img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      transition: transform 0.3s;
    }
    .popup-preview-img-wrap:hover img {
      transform: scale(1.05);
    }
    .popup-preview-meta {
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }
    .popup-preview-meta-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--gray-800);
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .popup-preview-meta-link {
      font-size: 0.78rem;
      color: var(--primary);
      text-decoration: none;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    /* Badge & Quick Action */
    .btn-status-toggle {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid transparent;
    }
    .btn-status-toggle.active {
      background: rgba(46, 133, 64, 0.12);
      color: var(--success);
      border-color: rgba(46, 133, 64, 0.25);
    }
    .btn-status-toggle.active:hover {
      background: rgba(46, 133, 64, 0.2);
    }
    .btn-status-toggle.inactive {
      background: rgba(229, 62, 62, 0.1);
      color: var(--danger);
      border-color: rgba(229, 62, 62, 0.2);
    }
    .btn-status-toggle.inactive:hover {
      background: rgba(229, 62, 62, 0.18);
    }

    /* Pejabat Struktural Admin Specific Styles */
    .pejabat-meta-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }
    .badge-pejabat-kepala {
      background: linear-gradient(135deg, rgba(201, 168, 76, 0.22), rgba(201, 168, 76, 0.1));
      color: #8a6d1b;
      border: 1px solid rgba(201, 168, 76, 0.45);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
    }
    .badge-pejabat-grid {
      background: rgba(10, 36, 99, 0.08);
      color: var(--primary);
      border: 1px solid rgba(10, 36, 99, 0.2);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
    }
    .pejabat-thumb-circle {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      border: 2px solid rgba(201, 168, 76, 0.45);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
    }
    .pejabat-thumb-circle img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* ==============================================
       ADMIN RESPONSIVE STYLES
       Desktop (> 1024px), Tablet (768px – 1024px), Mobile (< 768px)
       ============================================== */

    /* 1. TABLET (768px – 1024px) */
    @media (max-width: 1024px) {
      .admin-navbar {
        padding: 12px 20px;
      }
      .admin-main {
        padding: 0 16px;
        margin: 20px auto;
      }
      .stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin-bottom: 22px;
      }
      .form-grid {
        grid-template-columns: 1fr;
        gap: 24px;
      }
      .content-box-header {
        padding: 16px 20px;
      }
      .filter-bar {
        padding: 14px 20px;
        flex-wrap: wrap;
        gap: 12px;
      }
      .table-news th, .table-news td {
        padding: 12px 14px;
      }
      .admin-tabs-nav {
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
      }
    }

    /* 2. MOBILE / HP (< 768px) */
    @media (max-width: 768px) {
      .admin-navbar {
        padding: 12px 14px;
      }
      .admin-nav-container {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }
      .admin-brand {
        justify-content: flex-start;
      }
      .admin-brand img {
        width: 38px;
        height: 38px;
      }
      .admin-brand-text h1 {
        font-size: 1rem;
      }
      .admin-brand-text span {
        font-size: 0.72rem;
      }
      .admin-nav-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: 100%;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 10px;
      }
      .user-pill {
        padding: 5px 10px;
        font-size: 0.8rem;
      }
      .btn-view-site, .btn-logout {
        padding: 6px 12px;
        font-size: 0.8rem;
        min-height: 38px;
        display: inline-flex;
        align-items: center;
      }

      /* Horizontal Scrollable Tabs */
      .admin-tabs-nav {
        display: flex;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        gap: 6px;
        padding-bottom: 4px;
        margin-bottom: 18px;
        border-bottom: 2px solid var(--gray-200);
        scrollbar-width: none;
      }
      .admin-tabs-nav::-webkit-scrollbar {
        display: none;
      }
      .admin-tab-item {
        padding: 10px 16px;
        font-size: 0.86rem;
        flex-shrink: 0;
      }

      /* Stats */
      .stats-row {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 20px;
      }
      .stat-card {
        padding: 14px 16px;
        gap: 14px;
      }
      .stat-icon {
        width: 44px;
        height: 44px;
      }
      .stat-icon svg {
        width: 22px;
        height: 22px;
      }
      .stat-info .stat-value {
        font-size: 1.35rem;
      }

      /* Main container & content box */
      .admin-main {
        padding: 0 10px;
        margin: 14px auto;
      }
      .content-box {
        border-radius: var(--radius-md);
      }
      .content-box-header {
        padding: 14px 16px;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        text-align: left;
      }
      .content-box-title {
        font-size: 1.1rem;
      }
      .content-box-header .btn-primary,
      .content-box-header .btn-secondary {
        width: 100%;
        justify-content: center;
        min-height: 44px;
      }

      /* Filter bar */
      .filter-bar {
        padding: 12px 16px;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .search-box {
        max-width: 100%;
        width: 100%;
      }
      .search-box input {
        font-size: 16px;
        min-height: 44px;
      }

      /* Tables: scrollable horizontally with clear min-width */
      .table-responsive {
        border-radius: 0 0 12px 12px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        display: block;
        width: 100%;
      }
      .table-news {
        min-width: 650px;
        font-size: 0.86rem;
      }
      .table-news th {
        padding: 10px 12px;
        font-size: 0.72rem;
      }
      .table-news td {
        padding: 12px 12px;
      }
      .news-thumbnail {
        width: 58px;
        height: 42px;
      }
      .action-buttons {
        gap: 6px;
        flex-wrap: nowrap;
      }
      .btn-action-edit, .btn-action-delete {
        padding: 8px 10px;
        font-size: 0.78rem;
        min-height: 36px;
      }

      /* Form */
      .form-container, .news-form {
        padding: 18px 14px !important;
      }
      .pejabat-meta-grid {
        grid-template-columns: 1fr;
        gap: 12px;
      }
      .form-input, .form-textarea, select.form-control, input.form-control {
        font-size: 16px !important;
        min-height: 44px;
        padding: 10px 14px;
      }
      .form-actions-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .form-actions-bar .btn-primary,
      .form-actions-bar .btn-secondary {
        width: 100%;
        justify-content: center;
        min-height: 46px;
      }

      /* Pop-up previews in Admin */
      .popup-preview-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
        padding: 14px;
      }
      .popup-preview-img-wrap {
        height: 120px;
      }

      /* Lightbox in Admin */
      .admin-modal-img {
        max-width: 95vw;
        max-height: 80vh;
      }
    }

    /* 3. SMALL MOBILE (< 480px) */
    @media (max-width: 480px) {
      .admin-brand-text h1 {
        font-size: 0.92rem;
      }
      .user-pill {
        font-size: 0.75rem;
        padding: 4px 8px;
      }
      .btn-view-site, .btn-logout {
        padding: 6px 10px;
        font-size: 0.75rem;
      }
      .stat-card {
        padding: 12px 14px;
      }
      .form-grid {
        grid-template-columns: 1fr !important;
      }
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
          <h1>RUTAN KELAS IV TANJUNG</h1>
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
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"></circle>
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
        <div class="stat-icon" style="background:rgba(124, 58, 237, 0.12); color:#7c3aed;">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <div class="stat-info">
          <h4>Pop-up Ads</h4>
          <div class="stat-value" style="font-size:1.35rem;"><?= $stat_popup_aktif ?> <span style="font-size:0.85rem; font-weight:600; color:var(--gray-600);">Aktif (<?= $stat_popup_total ?> total)</span></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(14, 116, 144, 0.12); color:#0e7490;">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
        </div>
        <div class="stat-info">
          <h4>Pejabat Struktural</h4>
          <div class="stat-value" style="font-size:1.35rem;"><?= $stat_pejabat_aktif ?> <span style="font-size:0.85rem; font-weight:600; color:var(--gray-600);">Aktif (<?= $stat_pejabat_total ?> total)</span></div>
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
      <a href="index.php?tab=berita" class="admin-tab-item <?= ($tab === 'berita') ? 'active' : '' ?>">
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
      <a href="index.php?tab=popup" class="admin-tab-item <?= ($tab === 'popup') ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="8" y1="21" x2="16" y2="21"></line>
          <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
        Kelola Pop-up Ads
      </a>
      <a href="index.php?tab=pejabat" class="admin-tab-item <?= ($tab === 'pejabat') ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Kelola Pejabat Struktural
      </a>
    </div>

    <?php if ($tab === 'berita'): ?>
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
                    <p style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Klik untuk Memilih Gambar</p>
                    <p style="font-size:0.75rem;color:var(--gray-600);">JPG, JPEG, PNG, atau WEBP (Maks 20MB, otomatis dikompres &lt; 1MB)</p>
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
                      <p style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Klik untuk Memilih Gambar</p>
                      <p style="font-size:0.75rem;color:var(--gray-600);">JPG, JPEG, PNG, atau WEBP (Maks 20MB, otomatis dikompres &lt; 1MB)</p>
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
                      <a href="index.php?action=hapus&id=<?= $row['id'] ?>" class="btn-action-delete" title="Hapus Berita" onclick="return confirmDeleteNews('index.php?action=hapus&id=<?= $row['id'] ?>', '<?= htmlspecialchars(addslashes($row['judul'])) ?>', event);">
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
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"></circle>
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

    <!-- ============================================== -->
    <!-- TAB: KELOLA POP-UP ADS -->
    <!-- ============================================== -->
    <?php if ($tab === 'popup'): ?>

      <!-- 1. PRATINJAU POP-UP YANG SEDANG AKTIF -->
      <div class="content-box" style="margin-bottom: 28px;">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Pratinjau Pop-up Ads Aktif di Halaman Utama (<?= count(array_filter($list_popup_rows, fn($p) => (int)$p['is_aktif'] === 1)) ?> Gambar Aktif)
          </div>
          <span style="font-size:0.85rem; color:var(--gray-600);">
            Gambar di bawah ini akan otomatis muncul sebagai slide pop-up saat pengunjung membuka atau me-refresh homepage.
          </span>
        </div>

        <?php 
        $active_popups = array_values(array_filter($list_popup_rows, fn($p) => (int)$p['is_aktif'] === 1));
        if (!empty($active_popups)): 
        ?>
          <div class="popup-preview-grid">
            <?php foreach ($active_popups as $idx => $p): 
              $p_img_url = '../' . htmlspecialchars($p['gambar']);
            ?>
              <div class="popup-preview-card">
                <div class="popup-preview-img-wrap" onclick="openImageModal('<?= $p_img_url ?>')" title="Klik untuk memperbesar gambar">
                  <img src="<?= $p_img_url ?>" alt="<?= htmlspecialchars($p['keterangan'] ?? 'Popup') ?>">
                  <span style="position:absolute; top:8px; right:8px; background:rgba(0,0,0,0.7); color:#fff; font-size:0.75rem; font-weight:700; padding:3px 8px; border-radius:4px;">
                    Slide #<?= $idx + 1 ?> (Urutan: <?= (int)$p['urutan'] ?>)
                  </span>
                </div>
                <div class="popup-preview-meta">
                  <div class="popup-preview-meta-title" title="<?= htmlspecialchars($p['keterangan'] ?: 'Pop-up #' . $p['id']) ?>">
                    <?= htmlspecialchars($p['keterangan'] ?: 'Pop-up #' . $p['id']) ?>
                  </div>
                  <?php if (!empty($p['link_url'])): ?>
                    <a href="<?= htmlspecialchars($p['link_url']) ?>" target="_blank" rel="noopener" class="popup-preview-meta-link" title="<?= htmlspecialchars($p['link_url']) ?>">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                      <?= htmlspecialchars($p['link_url']) ?>
                    </a>
                  <?php else: ?>
                    <span style="font-size:0.75rem; color:var(--gray-400);">Tanpa link tujuan</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
            </svg>
            <h3 style="font-size:1.05rem; margin-bottom:6px; color:var(--gray-800);">Tidak Ada Pop-up yang Sedang Aktif</h3>
            <p style="font-size:0.88rem; color:var(--gray-600);">Semua pop-up sedang dinonaktifkan. Pengunjung tidak akan melihat pop-up saat membuka homepage. Aktifkan status di tabel bawah atau unggah gambar baru.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- 2. FORM TAMBAH / EDIT POP-UP ADS -->
      <div class="content-box" style="margin-bottom: 28px;">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <?php if ($popup_edit_data): ?>
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              <?php else: ?>
                <path d="M12 5v14M5 12h14"></path>
              <?php endif; ?>
            </svg>
            <?= $popup_edit_data ? 'Edit Pengaturan Pop-up #' . (int)$popup_edit_data['id'] : 'Unggah &amp; Tambah Pop-up Ad Baru' ?>
          </div>
          <?php if ($popup_edit_data): ?>
            <a href="index.php?tab=popup" class="btn-secondary" style="font-size:0.85rem;">
              ✕ Batal Edit (Tambah Baru)
            </a>
          <?php endif; ?>
        </div>

        <form action="index.php?tab=popup" method="POST" enctype="multipart/form-data" class="form-container">
          <input type="hidden" name="<?= $popup_edit_data ? 'proses_edit_popup' : 'proses_tambah_popup' ?>" value="1">
          <?php if ($popup_edit_data): ?>
            <input type="hidden" name="popup_id" value="<?= (int)$popup_edit_data['id'] ?>">
          <?php endif; ?>

          <div class="form-grid">
            <div>
              <!-- Judul / Keterangan Pop-up -->
              <div class="form-group">
                <label class="form-label">Judul / Keterangan Pop-up <span style="font-weight:400;color:var(--gray-600);">(opsional)</span></label>
                <input type="text" name="popup_keterangan" class="form-input" placeholder="Contoh: Banner Selamat Datang, Maklumat Pelayanan, dll." value="<?= htmlspecialchars($popup_edit_data['keterangan'] ?? '') ?>">
                <div class="form-help">Label deskriptif dan teks penjelas gambar pop-up.</div>
              </div>

              <!-- Link URL Tujuan -->
              <div class="form-group">
                <label class="form-label">URL / Link Tujuan Saat Gambar Diklik <span style="font-weight:400;color:var(--gray-600);">(opsional)</span></label>
                <input type="text" name="popup_link_url" class="form-input" placeholder="Contoh: https://kemenimipas.go.id atau berita.php" value="<?= htmlspecialchars($popup_edit_data['link_url'] ?? '') ?>">
                <div class="form-help">Jika diisi, pengunjung yang mengklik gambar pop-up di homepage akan otomatis diarahkan ke URL tujuan ini. Kosongkan jika hanya ingin menampilkan gambar.</div>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <!-- Urutan Tampilan -->
                <div class="form-group">
                  <label class="form-label">Urutan Tampilan</label>
                  <input type="number" name="popup_urutan" class="form-input" min="1" value="<?= (int)($popup_edit_data['urutan'] ?? (count($list_popup_rows) + 1)) ?>" required>
                  <div class="form-help">Nomor urut slide (1 = slide pertama). Jika diisi nomor yang sudah ada (misal urutan 3 atau 7), pop-up yang sebelumnya ada di posisi tersebut akan otomatis bergeser turun (+1).</div>
                </div>

                <!-- Toggle Switch Status Aktif -->
                <div class="form-group">
                  <label class="form-label">Status Pop-up</label>
                  <div class="switch-toggle-wrap" style="padding-top:6px;">
                    <label class="switch-toggle">
                      <input type="checkbox" name="popup_is_aktif" value="1" id="popupAktifSwitch" <?= (!isset($popup_edit_data) || (int)$popup_edit_data['is_aktif'] === 1) ? 'checked' : '' ?> onchange="updateSwitchLabel(this)">
                      <span class="switch-slider"></span>
                    </label>
                    <span class="switch-label-text" id="switchLabelText">
                      <?= (!isset($popup_edit_data) || (int)$popup_edit_data['is_aktif'] === 1) ? 'Aktif (Ditampilkan)' : 'Nonaktif (Disembunyikan)' ?>
                    </span>
                  </div>
                  <div class="form-help">Bisa dimatikan kapan saja tanpa menghapus gambar.</div>
                </div>
              </div>
            </div>

            <!-- Upload File Gambar & Live Preview -->
            <div>
              <div class="form-group">
                <label class="form-label">
                  File Gambar Pop-up <?= !$popup_edit_data ? '<span class="req">*</span>' : '<span style="font-weight:400;color:var(--gray-600);">(Pilih jika ingin ganti)</span>' ?>
                </label>
                <div class="upload-box" onclick="document.getElementById('popupGambarInput').click()" style="padding:16px;">
                  <input type="file" name="popup_gambar" id="popupGambarInput" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewPopupImage(this)" <?= !$popup_edit_data ? 'required' : '' ?>>
                  
                  <?php if ($popup_edit_data && !empty($popup_edit_data['gambar'])): ?>
                    <!-- Gambar yang sedang aktif sekarang -->
                    <div id="popupExistingArea">
                      <img src="../<?= htmlspecialchars($popup_edit_data['gambar']) ?>" alt="Existing" style="max-height:150px; max-width:100%; border-radius:8px; margin-bottom:8px; object-fit:contain; display:block; margin:0 auto;">
                      <span style="font-size:0.75rem; color:var(--gray-600); display:block;">Gambar saat ini. Klik untuk memilih gambar baru pengganti.</span>
                    </div>
                  <?php endif; ?>

                  <div id="popupUploadPlaceholder" style="<?= ($popup_edit_data && !empty($popup_edit_data['gambar'])) ? 'display:none;' : '' ?>">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--primary); margin:0 auto 10px; display:block;">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <circle cx="8.5" cy="8.5" r="1.5"></circle>
                      <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <strong style="color:var(--primary); font-size:0.9rem; display:block;">Pilih Gambar</strong>
                    <span style="font-size:0.78rem; color:var(--gray-600);">Format JPG, PNG, atau WebP (Maks 20MB, otomatis dikompres &lt; 1MB)</span>
                  </div>

                  <!-- Live Preview Gambar Baru -->
                  <div id="popupPreviewArea" style="display:none; margin-top:8px;">
                    <img id="popupImgPreview" src="" alt="Pratinjau Baru" style="max-height:150px; max-width:100%; border-radius:8px; object-fit:contain; display:block; margin:0 auto 6px;">
                    <span style="font-size:0.75rem; color:var(--success); font-weight:700;">✓ Pratinjau gambar baru terpilih</span>
                  </div>
                </div>
              </div>

              <div style="margin-top:20px;">
                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                  </svg>
                  <?= $popup_edit_data ? 'Simpan Perubahan Pop-up' : 'Simpan &amp; Unggah Pop-up' ?>
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- 3. TABEL DAFTAR SEMUA POP-UP ADS -->
      <div class="content-box">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            Daftar Manajemen Seluruh Pop-up Ads (<?= count($list_popup_rows) ?> Total)
          </div>
        </div>

        <div class="table-responsive">
          <table class="table-news">
            <thead>
              <tr>
                <th style="width:60px; text-align:center;">Urutan</th>
                <th style="width:120px;">Pratinjau</th>
                <th>Keterangan / Judul</th>
                <th>Tautan Tujuan</th>
                <th style="width:160px; text-align:center;">Status Aktif (Toggle)</th>
                <th style="width:140px; text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($list_popup_rows)): ?>
                <?php foreach ($list_popup_rows as $p): 
                  $img_src = '../' . htmlspecialchars($p['gambar']);
                ?>
                  <tr>
                    <td style="text-align:center; font-weight:800; color:var(--primary);">
                      <?= (int)$p['urutan'] ?>
                    </td>
                    <td>
                      <div style="width:90px; height:60px; border-radius:6px; overflow:hidden; background:#000; cursor:pointer; display:flex; align-items:center; justify-content:center; border:1px solid var(--gray-200);" onclick="openImageModal('<?= $img_src ?>')" title="Klik untuk memperbesar">
                        <img src="<?= $img_src ?>" alt="Thumbnail" style="width:100%; height:100%; object-fit:contain;">
                      </div>
                    </td>
                    <td>
                      <strong style="color:var(--gray-800); font-size:0.95rem;">
                        <?= htmlspecialchars($p['keterangan'] ?: 'Pop-up #' . $p['id']) ?>
                      </strong>
                      <div style="font-size:0.75rem; color:var(--gray-400); margin-top:2px;">
                        File: <?= htmlspecialchars($p['gambar']) ?>
                      </div>
                    </td>
                    <td>
                      <?php if (!empty($p['link_url'])): ?>
                        <a href="<?= htmlspecialchars($p['link_url']) ?>" target="_blank" rel="noopener" style="color:var(--primary); font-size:0.85rem; display:inline-flex; align-items:center; gap:4px; text-decoration:none;" title="Buka tautan">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                          <span style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['link_url']) ?></span>
                        </a>
                      <?php else: ?>
                        <span style="color:var(--gray-400); font-size:0.82rem;">— Tanpa link tujuan —</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <a href="index.php?tab=popup&toggle_popup=<?= (int)$p['id'] ?>" class="btn-status-toggle <?= (int)$p['is_aktif'] === 1 ? 'active' : 'inactive' ?>" title="Klik untuk beralih status Aktif / Nonaktif">
                        <?= (int)$p['is_aktif'] === 1 ? '● Aktif (Tayang)' : '○ Nonaktif (Mati)' ?>
                      </a>
                    </td>
                    <td style="text-align:center;">
                      <div style="display:flex; align-items:center; justify-content:center; gap:8px;">
                        <a href="index.php?tab=popup&edit_popup=<?= (int)$p['id'] ?>" class="btn-action edit" title="Edit Pop-up">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </a>
                        <a href="index.php?tab=popup&hapus_popup=<?= (int)$p['id'] ?>" class="btn-action delete" title="Hapus Pop-up" onclick="return confirm('Apakah Anda yakin ingin menghapus pop-up ini? File gambar di server juga akan otomatis dihapus.');">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center; padding:30px; color:var(--gray-600);">
                    Belum ada data pop-up ads. Silakan unggah gambar baru pada formulir di atas.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; // Akhir Tab Pop-up Ads ?>

    <!-- ============================================== -->
    <!-- TAB: KELOLA PEJABAT STRUKTURAL -->
    <!-- ============================================== -->
    <?php if ($tab === 'pejabat'): ?>

      <!-- 1. FORM TAMBAH / EDIT PEJABAT STRUKTURAL -->
      <div class="content-box" style="margin-bottom: 28px;">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <?php if ($pejabat_edit_data): ?>
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              <?php else: ?>
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
              <?php endif; ?>
            </svg>
            <?= $pejabat_edit_data ? 'Edit Data Pejabat: ' . htmlspecialchars($pejabat_edit_data['nama']) : 'Tambah Pejabat Struktural Baru' ?>
          </div>
          <?php if ($pejabat_edit_data): ?>
            <a href="index.php?tab=pejabat" class="btn-secondary" style="font-size:0.85rem;">
              ✕ Batal Edit (Tambah Baru)
            </a>
          <?php endif; ?>
        </div>

        <form action="index.php?tab=pejabat" method="POST" enctype="multipart/form-data" class="form-container">
          <input type="hidden" name="<?= $pejabat_edit_data ? 'proses_edit_pejabat' : 'proses_tambah_pejabat' ?>" value="1">
          <?php if ($pejabat_edit_data): ?>
            <input type="hidden" name="pejabat_id" value="<?= (int)$pejabat_edit_data['id'] ?>">
          <?php endif; ?>

          <div class="form-grid">
            <div>
              <div class="form-group">
                <label class="form-label" for="pejabat_nama">Nama Lengkap &amp; Gelar <span class="req">*</span></label>
                <input type="text" id="pejabat_nama" name="pejabat_nama" class="form-input" placeholder="Contoh: RAYMON ANDIKA GIRSANG, A.Md.IP., S.H., M.H." value="<?= htmlspecialchars($pejabat_edit_data['nama'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="pejabat_jabatan">Jabatan Struktural <span class="req">*</span></label>
                <input type="text" id="pejabat_jabatan" name="pejabat_jabatan" class="form-input" placeholder="Contoh: Kepala Rutan, Kasubsi Pelayanan Tahanan, dll." value="<?= htmlspecialchars($pejabat_edit_data['jabatan'] ?? '') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="pejabat_deskripsi">Deskripsi Tugas / Profil Singkat</label>
                <textarea id="pejabat_deskripsi" name="pejabat_deskripsi" class="form-textarea" style="min-height:110px;" placeholder="Tuliskan tugas pokok dan fungsi singkat pejabat..."><?= htmlspecialchars($pejabat_edit_data['deskripsi'] ?? '') ?></textarea>
                <div class="form-help">Ditampilkan di bawah nama &amp; jabatan pada halaman utama website.</div>
              </div>

              <div class="pejabat-meta-grid">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label" for="pejabat_is_kepala">Posisi Tampilan</label>
                  <select id="pejabat_is_kepala" name="pejabat_is_kepala" class="form-input">
                    <option value="0" <?= (isset($pejabat_edit_data) && (int)$pejabat_edit_data['is_kepala'] === 0) ? 'selected' : '' ?>>Pejabat Struktural (Kartu Grid)</option>
                    <option value="1" <?= (isset($pejabat_edit_data) && (int)$pejabat_edit_data['is_kepala'] === 1) ? 'selected' : '' ?>>Kepala Rutan (Kartu Utama Atas)</option>
                  </select>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label" for="pejabat_urutan">Urutan Tampil</label>
                  <input type="number" id="pejabat_urutan" name="pejabat_urutan" class="form-input" min="1" value="<?= (int)($pejabat_edit_data['urutan'] ?? (count(array_filter($list_pejabat_rows, fn($r) => (int)$r['is_kepala'] === 0)) + 1)) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Status Tampil</label>
                  <div class="switch-toggle-wrap" style="padding-top:6px;">
                    <label class="switch-toggle">
                      <input type="checkbox" name="pejabat_is_aktif" value="1" id="pejabatAktifSwitch" <?= (!isset($pejabat_edit_data) || (int)$pejabat_edit_data['is_aktif'] === 1) ? 'checked' : '' ?> onchange="updatePejabatSwitchLabel(this)">
                      <span class="switch-slider"></span>
                    </label>
                    <span class="switch-label-text" id="pejabatSwitchLabelText">
                      <?= (!isset($pejabat_edit_data) || (int)$pejabat_edit_data['is_aktif'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Upload Foto Pejabat & Live Preview -->
            <div>
              <div class="form-group">
                <label class="form-label">
                  Foto Pejabat <span style="font-weight:400;color:var(--gray-600);">(<?= $pejabat_edit_data ? 'Pilih jika ingin ganti' : 'opsional' ?>)</span>
                </label>
                <div class="upload-box" onclick="document.getElementById('pejabatFotoInput').click()" style="padding:20px;">
                  <input type="file" name="pejabat_foto" id="pejabatFotoInput" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewPejabatImage(this)">

                  <?php if ($pejabat_edit_data && !empty($pejabat_edit_data['foto'])): ?>
                    <div id="pejabatExistingArea">
                      <div style="width:130px; height:130px; border-radius:50%; background:linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border:3px solid rgba(201,168,76,0.45); overflow:hidden; margin:0 auto 10px;">
                        <img src="../<?= htmlspecialchars($pejabat_edit_data['foto']) ?>?v=<?= time() ?>" alt="Foto Pejabat Saat Ini" style="width:100%; height:100%; object-fit:cover;">
                      </div>
                      <span style="font-size:0.78rem; color:var(--gray-600); display:block;">Foto saat ini. Klik di sini untuk mengganti foto.</span>
                    </div>
                  <?php endif; ?>

                  <div id="pejabatUploadPlaceholder" style="<?= ($pejabat_edit_data && !empty($pejabat_edit_data['foto'])) ? 'display:none;' : '' ?>">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--primary); margin:0 auto 10px; display:block;">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <strong style="color:var(--primary); font-size:0.9rem; display:block;">Pilih Foto Pejabat</strong>
                    <span style="font-size:0.78rem; color:var(--gray-600);">Format JPG, PNG, atau WebP (Maks 20MB, otomatis dikompres &lt; 1MB)</span>
                  </div>

                  <div id="pejabatPreviewArea" style="display:none; margin-top:8px;">
                    <div style="width:130px; height:130px; border-radius:50%; background:linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border:3px solid rgba(201,168,76,0.45); overflow:hidden; margin:0 auto 8px;">
                      <img id="pejabatImgPreview" src="" alt="Pratinjau Foto" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <span style="font-size:0.78rem; color:var(--success); font-weight:700;">✓ Pratinjau foto baru terpilih</span>
                  </div>
                </div>
                <div class="form-help">Disarankan menggunakan foto PNG berlatar transparan atau foto potret wajah menghadap depan agar serasi di bingkai lingkaran.</div>
              </div>

              <div style="margin-top:20px;">
                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                  </svg>
                  <?= $pejabat_edit_data ? 'Simpan Perubahan Pejabat' : 'Simpan &amp; Tambah Pejabat' ?>
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- 2. TABEL DAFTAR PEJABAT STRUKTURAL -->
      <div class="content-box">
        <div class="content-box-header">
          <div class="content-box-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            Daftar Pejabat Struktural Rutan Tanjung (<?= count($list_pejabat_rows) ?> Total)
          </div>
          <a href="../index.php#pejabat" target="_blank" class="btn-secondary" style="font-size:0.85rem;">
            Lihat Bagian Pejabat di Website &rarr;
          </a>
        </div>

        <div class="table-responsive">
          <table class="table-news">
            <thead>
              <tr>
                <th style="width:65px; text-align:center;">Urut</th>
                <th style="width:86px; text-align:center;">Foto</th>
                <th>Nama &amp; Jabatan</th>
                <th style="width:175px;">Posisi Tampilan</th>
                <th>Deskripsi Tugas</th>
                <th style="width:145px; text-align:center;">Status</th>
                <th style="width:170px; text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($list_pejabat_rows)): ?>
                <?php foreach ($list_pejabat_rows as $pj):
                  $pj_foto_url = !empty($pj['foto']) ? '../' . htmlspecialchars($pj['foto']) : '';
                  $pj_foto_exists = !empty($pj['foto']) && file_exists(__DIR__ . '/../' . $pj['foto']);
                  $pj_foto_src = $pj_foto_exists ? $pj_foto_url . '?v=' . filemtime(__DIR__ . '/../' . $pj['foto']) : $pj_foto_url;
                ?>
                  <tr>
                    <td style="text-align:center; font-weight:800; color:var(--primary);">
                      <?= (int)$pj['urutan'] ?>
                    </td>
                    <td style="text-align:center;">
                      <?php if (!empty($pj['foto'])): ?>
                        <div class="pejabat-thumb-circle" style="margin:0 auto;" onclick="openImageModal('<?= htmlspecialchars($pj_foto_src, ENT_QUOTES) ?>')" title="Klik untuk memperbesar">
                          <img src="<?= htmlspecialchars($pj_foto_src) ?>" alt="<?= htmlspecialchars($pj['nama']) ?>">
                        </div>
                      <?php else: ?>
                        <div class="pejabat-thumb-circle" style="margin:0 auto; cursor:default;" title="Belum ada foto">
                          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                          </svg>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong style="color:var(--primary-dark); font-size:0.95rem; display:block; margin-bottom:4px;">
                        <?= htmlspecialchars($pj['nama']) ?>
                      </strong>
                      <span class="badge-author"><?= htmlspecialchars($pj['jabatan']) ?></span>
                    </td>
                    <td>
                      <?php if ((int)$pj['is_kepala'] === 1): ?>
                        <span class="badge-pejabat-kepala">★ Kepala Rutan (Utama)</span>
                      <?php else: ?>
                        <span class="badge-pejabat-grid">Pejabat Grid</span>
                      <?php endif; ?>
                    </td>
                    <td style="color:var(--gray-600); font-size:0.86rem; max-width:280px; line-height:1.5;">
                      <?= !empty($pj['deskripsi']) ? htmlspecialchars(mb_strimwidth($pj['deskripsi'], 0, 120, '...')) : '<span style="color:var(--gray-400);">— Belum ada deskripsi —</span>' ?>
                    </td>
                    <td style="text-align:center;">
                      <a href="index.php?tab=pejabat&toggle_pejabat=<?= (int)$pj['id'] ?>" class="btn-status-toggle <?= (int)$pj['is_aktif'] === 1 ? 'active' : 'inactive' ?>" title="Klik untuk beralih status Aktif / Nonaktif">
                        <?= (int)$pj['is_aktif'] === 1 ? '● Aktif' : '○ Nonaktif' ?>
                      </a>
                    </td>
                    <td style="text-align:center;">
                      <div class="action-buttons" style="justify-content:center;">
                        <a href="index.php?tab=pejabat&edit_pejabat=<?= (int)$pj['id'] ?>" class="btn-action-edit" title="Edit Data Pejabat">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                          </svg>
                          Edit
                        </a>
                        <a href="index.php?tab=pejabat&hapus_pejabat=<?= (int)$pj['id'] ?>" class="btn-action-delete" title="Hapus Data Pejabat" onclick="return confirmDeletePejabat('index.php?tab=pejabat&hapus_pejabat=<?= (int)$pj['id'] ?>', <?= htmlspecialchars(json_encode($pj['nama']), ENT_QUOTES) ?>, event)">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                  <td colspan="7" style="text-align:center; padding:30px; color:var(--gray-600);">
                    Belum ada data pejabat struktural. Silakan tambahkan data pejabat baru melalui formulir di atas.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; // Akhir Tab Pejabat Struktural ?>

  </main>

  <!-- Modal Preview Gambar Full Size -->
  <div class="admin-modal-overlay" id="imageModal" onclick="closeImageModal()">
    <img src="" alt="Full Preview" class="admin-modal-img" id="modalImg">
  </div>

  <!-- Modal Konfirmasi Hapus Berita (Anti-Block) -->
  <div class="admin-modal-overlay" id="deleteConfirmModal" style="display:none; z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; max-width:440px; width:90%; padding:28px 24px; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
      <div style="width:56px; height:56px; border-radius:50%; background:#fed7d7; color:#e53e3e; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
          <line x1="10" y1="11" x2="10" y2="17"></line>
          <line x1="14" y1="11" x2="14" y2="17"></line>
        </svg>
      </div>
      <h3 id="deleteModalTitle" style="margin:0 0 8px; font-size:1.15rem; color:#1a202c;">Hapus Berita Ini?</h3>
      <p id="deleteModalJudul" style="font-size:0.9rem; color:#718096; margin:0 0 24px; line-height:1.5; word-break:break-word;"></p>
      <div style="display:flex; gap:12px; justify-content:center;">
        <button type="button" class="btn-secondary" onclick="closeDeleteModal()" style="min-width:100px; padding:9px 18px; cursor:pointer;">Batal</button>
        <a id="deleteModalConfirmBtn" href="#" class="btn-action-delete" style="min-width:100px; padding:9px 18px; text-align:center; background:#e53e3e; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; display:inline-block; border:none;">Ya, Hapus</a>
      </div>
    </div>
  </div>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> Rumah Tahanan Negara Kelas IV Tanjung &bull; Ditjen Pemasyarakatan &bull; Kemenimipas RI</p>
  </footer>

  <script>
    function previewImage(input) {
      const previewArea = document.getElementById('previewArea');
      const placeholder = document.getElementById('uploadPlaceholder');
      const img = document.getElementById('imgPreview');

      if (input.files && input.files[0]) {
        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(file.type) && !['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
          alert('Format gambar tidak valid! Gunakan JPG, JPEG, PNG, atau WEBP.');
          input.value = '';
          return;
        }
        if (file.size > 20 * 1024 * 1024) {
          alert('Ukuran gambar terlalu besar! Maksimal input 20MB.');
          input.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          img.src = e.target.result;
          previewArea.style.display = 'block';
          if (placeholder) placeholder.style.display = 'none';
        }
        reader.readAsDataURL(file);
      }
    }

    // Drag to Upload khusus pada Manajemen Berita (Tambah & Edit Berita)
    (function initBeritaDragUpload() {
      const dropArea = document.getElementById('dropArea');
      const gambarInput = document.getElementById('gambarInput');
      if (!dropArea || !gambarInput) return;

      // Cegah browser membuka file jika terlepas sedikit di luar kotak pada form berita
      ['dragover', 'drop'].forEach(function(evtName) {
        window.addEventListener(evtName, function(e) {
          e.preventDefault();
        });
      });

      ['dragenter', 'dragover'].forEach(function(evtName) {
        dropArea.addEventListener(evtName, function(e) {
          e.preventDefault();
          e.stopPropagation();
          dropArea.classList.add('drag-over');
        });
      });

      dropArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!dropArea.contains(e.relatedTarget)) {
          dropArea.classList.remove('drag-over');
        }
      });

      dropArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropArea.classList.remove('drag-over');

        const files = e.dataTransfer ? e.dataTransfer.files : null;
        if (!files || files.length === 0) {
          alert('Silakan lepaskan file gambar langsung dari komputer/perangkat Anda.');
          return;
        }

        const file = files[0];
        const dt = new DataTransfer();
        dt.items.add(file);
        gambarInput.files = dt.files;

        previewImage(gambarInput);
      });
    })();

    function openImageModal(src) {
      const modal = document.getElementById('imageModal');
      const modalImg = document.getElementById('modalImg');
      modalImg.src = src;
      modal.classList.add('active');
    }

    function closeImageModal() {
      document.getElementById('imageModal').classList.remove('active');
    }

    function previewPopupImage(input) {
      const previewArea = document.getElementById('popupPreviewArea');
      const placeholder = document.getElementById('popupUploadPlaceholder');
      const existing = document.getElementById('popupExistingArea');
      const img = document.getElementById('popupImgPreview');

      if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 20 * 1024 * 1024) {
          alert('Ukuran gambar terlalu besar! Maksimal input 20MB.');
          input.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          img.src = e.target.result;
          previewArea.style.display = 'block';
          if (placeholder) placeholder.style.display = 'none';
          if (existing) existing.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    }

    function updateSwitchLabel(checkbox) {
      const label = document.getElementById('switchLabelText');
      if (label) {
        label.textContent = checkbox.checked ? 'Aktif (Ditampilkan)' : 'Nonaktif (Disembunyikan)';
      }
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

    function confirmDeleteNews(url, judul, event) {
      if (event) event.preventDefault();
      const modal = document.getElementById('deleteConfirmModal');
      const titleEl = document.getElementById('deleteModalTitle');
      const judulEl = document.getElementById('deleteModalJudul');
      const btn = document.getElementById('deleteModalConfirmBtn');
      if (modal && btn) {
        if (titleEl) titleEl.textContent = 'Hapus Berita Ini?';
        if (judulEl) judulEl.textContent = 'Apakah Anda yakin ingin menghapus berita "' + judul + '" secara permanen? File gambar terkait juga akan otomatis dihapus.';
        btn.href = url;
        modal.style.display = 'flex';
        return false;
      }
      return confirm('Apakah Anda yakin ingin menghapus berita ini secara permanen?');
    }

    function confirmDeletePejabat(url, nama, event) {
      if (event) event.preventDefault();
      const modal = document.getElementById('deleteConfirmModal');
      const titleEl = document.getElementById('deleteModalTitle');
      const judulEl = document.getElementById('deleteModalJudul');
      const btn = document.getElementById('deleteModalConfirmBtn');
      if (modal && btn) {
        if (titleEl) titleEl.textContent = 'Hapus Data Pejabat?';
        if (judulEl) judulEl.textContent = 'Apakah Anda yakin ingin menghapus data pejabat "' + nama + '" secara permanen?';
        btn.href = url;
        modal.style.display = 'flex';
        return false;
      }
      return confirm('Apakah Anda yakin ingin menghapus data pejabat ini secara permanen?');
    }

    function previewPejabatImage(input) {
      const previewArea = document.getElementById('pejabatPreviewArea');
      const placeholder = document.getElementById('pejabatUploadPlaceholder');
      const existing = document.getElementById('pejabatExistingArea');
      const img = document.getElementById('pejabatImgPreview');

      if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 20 * 1024 * 1024) {
          alert('Ukuran foto terlalu besar! Maksimal input 20MB.');
          input.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          img.src = e.target.result;
          previewArea.style.display = 'block';
          if (placeholder) placeholder.style.display = 'none';
          if (existing) existing.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    }

    function updatePejabatSwitchLabel(checkbox) {
      const label = document.getElementById('pejabatSwitchLabelText');
      if (label) {
        label.textContent = checkbox.checked ? 'Aktif' : 'Nonaktif';
      }
    }

    function closeDeleteModal() {
      const modal = document.getElementById('deleteConfirmModal');
      if (modal) modal.style.display = 'none';
    }
  </script>
</body>
</html>
