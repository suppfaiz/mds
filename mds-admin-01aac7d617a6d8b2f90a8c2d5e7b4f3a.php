<?php
/**
 * =====================================================
 * ADMIN SECRET GATE - PHP Only Version
 * =====================================================
 * 
 * Tidak butuh konfigurasi Nginx sama sekali!
 * 
 * CARA KERJA:
 * - domain.com/         → redirect ke PMB (publik)
 * - domain.com/masuk.php → HALAMAN INI TIDAK ADA (404 via PHP)
 * - domain.com/mds-admin-01aac7d617a6d8b2f90a8c2d5e7b4f3a.php → ADMIN GATE ✅
 * 
 * Admin tinggal bookmark URL rahasia ini.
 * Orang iseng tidak akan menebak nama file-nya.
 * =====================================================
 */

// ---- Konfigurasi ----
// Ganti ini dengan token Anda sendiri jika mau lebih personal
define('ADMIN_SECRET_TOKEN', '01aac7d617a6d8b2f90a8c2d5e7b4f3a');

// Verifikasi bahwa file ini diakses langsung (bukan di-include)
if (basename(__FILE__) !== 'mds-admin-' . ADMIN_SECRET_TOKEN . '.php') {
    http_response_code(404);
    exit();
}

// Set session penanda "admin masuk via gate yang benar"
// Ini penting agar redirect antar halaman admin tetap bekerja
session_start();
$_SESSION['admin_gate']      = hash('sha256', ADMIN_SECRET_TOKEN . date('Y-m-d'));
$_SESSION['admin_gate_time'] = time();

// Redirect ke login admin jika belum punya sesi user
if (!isset($_SESSION['user_id'])) {
    // Tandai bahwa user datang dari gate yang benar
    $_SESSION['from_gate'] = true;
    header("Location: auth/login.php");
    exit();
}

// Sudah login — langsung ke dashboard
header("Location: dashboard_core.php");
exit();
?>
