<?php
/**
 * koneksi.php
 * Konfigurasi koneksi database MySQL untuk Rutan Tanjung Kelas IIB
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_rutantjg';

// Membuat koneksi menggunakan MySQLi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Set karakter utf8mb4 agar mendukung karakter khusus dan emoji
$conn->set_charset("utf8mb4");

/**
 * Helper untuk format tanggal Indonesia
 * Contoh: 2026-11-21 -> 21 November 2026
 */
if (!function_exists('format_tanggal_indo')) {
    function format_tanggal_indo($datetime) {
        if (empty($datetime)) return '-';
        $timestamp = strtotime($datetime);
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $tgl = date('d', $timestamp);
        $bln = $bulan[(int)date('m', $timestamp)];
        $thn = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);
        return "$tgl $bln $thn";
    }
}

/**
 * Helper untuk membuat slug ramah SEO dari string
 */
if (!function_exists('buat_slug')) {
    function buat_slug($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a-' . time();
        }
        return $text;
    }
}
?>
