<?php
/**
 * =====================================================
 * ADMIN SECRET ENTRY POINT
 * =====================================================
 * 
 * File ini adalah pintu masuk admin yang sesungguhnya.
 * Dipanggil oleh Nginx ketika ada request ke:
 *   http://domain.com/01aac7d617a6d8b2f90a8c2d5e7b4f3a/
 * 
 * JANGAN dipublikasikan keberadaan file ini.
 * JANGAN buat link apapun ke path ini di halaman publik.
 * =====================================================
 */

// Token rahasia — harus cocok dengan yang di Nginx config
define('ADMIN_GATE_TOKEN', '01aac7d617a6d8b2f90a8c2d5e7b4f3a');
define('ADMIN_GATE_HEADER', 'HTTP_X_ADMIN_PATH');

// Verifikasi bahwa request datang dari Nginx secret path
// Nginx menambahkan header X-Admin-Path saat meneruskan request
$incoming_gate = $_SERVER[ADMIN_GATE_HEADER] ?? '';
if ($incoming_gate !== ADMIN_GATE_TOKEN) {
    // Akses tidak dari path yang benar → 404 biasa
    http_response_code(404);
    exit('Not found.');
}

// Tandai sesi bahwa pengguna masuk via path yang benar
$_SESSION['admin_gate']       = hash('sha256', ADMIN_GATE_TOKEN . session_id());
$_SESSION['admin_gate_time']  = time();

// Serahkan ke dashboard utama dengan path prefix yang benar
$path_prefix = './';
$page_title  = 'Dashboard';
$active_menu = 'dashboard';

require_once 'config/db.php';
require_once 'includes/auth_check.php';

// Jika belum login, redirect ke halaman login admin (via path rahasia)
if (!isset($_SESSION['user_id'])) {
    header("Location: /" . ADMIN_GATE_TOKEN . "/masuk.php");
    exit();
}

checkLoginRoot();

// Muat dashboard core (isi logika dari file yang dulu adalah index.php asli)
require_once 'dashboard_core.php';
?>
