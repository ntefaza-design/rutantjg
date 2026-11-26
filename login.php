<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Jika sudah login, langsung alihkan ke dashboard admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin/");
    exit;
}

$error = '';
$pesan = '';

// Pesan dari query string
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logout') {
        $pesan = 'Anda telah berhasil logout.';
    } elseif ($_GET['msg'] === 'belum_login') {
        $error = 'Silakan login terlebih dahulu untuk mengakses panel admin.';
    }
}

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Harap isi username dan password!';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Regenerasi ID sesi untuk mencegah session fixation
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $user['id'];
                $_SESSION['admin_username']  = $user['username'];
                $_SESSION['admin_nama']      = $user['nama_lengkap'];

                header("Location: admin/");
                exit;
            } else {
                $error = 'Username atau password tidak sesuai!';
            }
        } else {
            $error = 'Username atau password tidak sesuai!';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Pengelola — Rutan Kelas IV Tanjung</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="banner/LOGO RUTAN png.png">
  <style>
    :root {
      --primary: #0a2463;
      --primary-light: #1a3a7a;
      --primary-dark: #061540;
      --gold: #c9a84c;
      --gold-light: #e0c068;
      --gold-dark: #a88830;
      --white: #ffffff;
      --gray-100: #e8eaf0;
      --gray-200: #d0d4e0;
      --gray-600: #5a6170;
      --gray-800: #2a2f3d;
      --danger: #e53e3e;
      --success: #38a169;
      --font-body: 'Plus Jakarta Sans', sans-serif;
      --font-display: 'Playfair Display', serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-body);
      background: radial-gradient(circle at 50% 20%, #102a70 0%, #061540 70%, #030b22 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      color: var(--white);
      position: relative;
      overflow-x: hidden;
    }

    /* Ambient glowing background shapes */
    .bg-shape {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      pointer-events: none;
      z-index: 0;
    }
    .shape-1 {
      width: 320px;
      height: 320px;
      background: rgba(201, 168, 76, 0.15);
      top: 10%;
      left: 15%;
    }
    .shape-2 {
      width: 360px;
      height: 360px;
      background: rgba(26, 58, 122, 0.35);
      bottom: 10%;
      right: 15%;
    }

    .login-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 440px;
      background: rgba(10, 36, 99, 0.75);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(201, 168, 76, 0.35);
      border-radius: 20px;
      padding: 40px 36px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 25px rgba(201, 168, 76, 0.15);
      animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .login-header {
      text-align: center;
      margin-bottom: 28px;
    }

    .login-logo {
      width: 80px;
      height: 80px;
      margin: 0 auto 16px;
      display: block;
      filter: drop-shadow(0 0 12px rgba(201, 168, 76, 0.45));
      transition: transform 0.3s ease;
    }

    .login-logo:hover {
      transform: scale(1.05);
    }

    .login-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--white);
      letter-spacing: -0.3px;
    }

    .login-subtitle {
      font-size: 0.88rem;
      color: var(--gold-light);
      margin-top: 4px;
      font-weight: 500;
    }

    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.88rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      line-height: 1.4;
    }

    .alert-danger {
      background: rgba(229, 62, 62, 0.18);
      border: 1px solid rgba(229, 62, 62, 0.4);
      color: #feb2b2;
    }

    .alert-success {
      background: rgba(56, 161, 105, 0.18);
      border: 1px solid rgba(56, 161, 105, 0.4);
      color: #9ae6b4;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--gray-200);
      margin-bottom: 8px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      color: var(--gold);
      display: flex;
      align-items: center;
      pointer-events: none;
    }

    .form-control {
      width: 100%;
      background: rgba(6, 21, 64, 0.7);
      border: 1.5px solid rgba(201, 168, 76, 0.3);
      border-radius: 10px;
      padding: 12px 14px 12px 44px;
      color: var(--white);
      font-size: 0.95rem;
      transition: all 0.25s ease;
      outline: none;
    }

    .form-control:focus {
      border-color: var(--gold);
      background: rgba(6, 21, 64, 0.95);
      box-shadow: 0 0 12px rgba(201, 168, 76, 0.25);
    }

    .toggle-pass {
      position: absolute;
      right: 14px;
      background: none;
      border: none;
      color: var(--gray-200);
      cursor: pointer;
      display: flex;
      align-items: center;
      padding: 4px;
      transition: color 0.2s;
    }

    .toggle-pass:hover {
      color: var(--gold);
    }

    .btn-login {
      width: 100%;
      background: linear-gradient(135deg, var(--gold) 0%, #b89335 100%);
      color: #061540;
      font-weight: 700;
      font-size: 1rem;
      padding: 14px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 10px;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(201, 168, 76, 0.45);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .login-footer {
      text-align: center;
      margin-top: 24px;
      padding-top: 18px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      font-size: 0.8rem;
      color: var(--gray-400, #8890a8);
    }

    .login-footer a {
      color: var(--gold-light);
      text-decoration: none;
      transition: color 0.2s;
    }

    .login-footer a:hover {
      color: var(--white);
      text-decoration: underline;
    }

    @media (max-width: 480px) {
      body {
        padding: 16px 12px;
      }
      .login-container {
        padding: 28px 20px;
        border-radius: 16px;
      }
      .login-logo {
        width: 64px;
        height: 64px;
        margin-bottom: 12px;
      }
      .login-title {
        font-size: 1.3rem;
      }
      .form-control {
        font-size: 16px;
        padding: 12px 14px 12px 42px;
      }
      .btn-login {
        min-height: 48px;
      }
    }
  </style>
</head>
<body>

  <div class="bg-shape shape-1"></div>
  <div class="bg-shape shape-2"></div>

  <div class="login-container">
    <div class="login-header">
      <img src="banner/LOGO RUTAN png.png" alt="Logo Rutan Tanjung" class="login-logo">
      <h1 class="login-title">Panel Pengelola</h1>
      <p class="login-subtitle">Rutan Kelas IV Tanjung</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($pesan)): ?>
      <div class="alert alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span><?= htmlspecialchars($pesan) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <div class="input-wrapper">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>
          <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus autocomplete="username">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrapper">
          <span class="input-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>
          <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
          <button type="button" class="toggle-pass" id="btnTogglePass" aria-label="Lihat password" onclick="togglePassword()">
            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
          <polyline points="10 17 15 12 10 7"></polyline>
          <line x1="15" y1="12" x2="3" y2="12"></line>
        </svg>
        Masuk ke Sistem
      </button>
    </form>

    <div class="login-footer">
      <p>&copy; <?= date('Y') ?> Rutan Kelas IV Tanjung &bull; Akses Terbatas</p>
    </div>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      const icon = document.getElementById('eyeIcon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
      } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
      }
    }
  </script>
</body>
</html>
