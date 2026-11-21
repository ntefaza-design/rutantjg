<?php
/**
 * logout.php
 * Mengakhiri sesi admin dan redirect ke halaman login
 */
session_start();

// Hapus semua data sesi
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Redirect ke halaman login relatif
header("Location: login.php?msg=logout");
exit;
