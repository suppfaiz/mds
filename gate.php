<?php
/**
 * ADMIN SECRET GATE
 * 
 * File ini TIDAK bisa diakses langsung via browser.
 * Nginx hanya melayaninya via path rahasia: /01aac7d617a6d8b2
 * 
 * Akses langsung ke /gate.php → 403 (diblokir Nginx)
 */

// Pastikan hanya bisa diakses via internal Nginx rewrite
// (Nginx menyetel header X-Gate saat rewrite dari secret path)
if (empty($_SERVER['HTTP_X_GATE_TOKEN']) || $_SERVER['HTTP_X_GATE_TOKEN'] !== '01aac7d617a6d8b2') {
    http_response_code(403);
    exit('Forbidden.');
}

if (session_status() === PHP_SESSION_NONE) session_start();

// Tandai bahwa pengguna masuk via path yang benar
$_SESSION['admin_gate']      = hash('sha256', '01aac7d617a6d8b2' . date('Y-m-d'));
$_SESSION['admin_gate_ip']   = $_SERVER['REMOTE_ADDR'] ?? '';
$_SESSION['admin_gate_time'] = time();

// Sudah login → langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard_core.php");
    exit();
}

// Belum login → ke halaman login
header("Location: auth/login.php");
exit();
?>
