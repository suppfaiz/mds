<?php
/**
 * ADMIN SECRET GATE
 * Diakses via: http://domain.com/01aac7d617a6d8b2f90a8c2d5e7b4f3a
 * 
 * Nginx langsung jalankan file ini saat secret path diakses.
 * Akses langsung ke /gate.php diblokir Nginx (return 404).
 */

// Cek bahwa request datang via secret path (bukan nama file langsung)
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/01aac7d617a6d8b2f90a8c2d5e7b4f3a') !== 0) {
    http_response_code(403);
    exit('Forbidden.');
}

require_once 'config/db.php';

// Set penanda sesi — admin masuk via path yang benar
$_SESSION['admin_gate']      = hash('sha256', '01aac7d617a6d8b2f90a8c2d5e7b4f3a' . date('Y-m-d'));
$_SESSION['admin_gate_ip']   = $_SERVER['REMOTE_ADDR'] ?? '';
$_SESSION['admin_gate_time'] = time();

// Sudah login? Langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: /dashboard_core.php");
    exit();
}

// Belum login? Ke halaman login
session_write_close();
header("Location: /auth/login.php");
exit();
?>
