<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    logActivity($pdo, 'Logout', 'User ' . $_SESSION['username'] . ' logout dari sistem.');
}

// Hapus session user saja, pertahankan session gate agar tidak 404 setelah logout
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['role']);
unset($_SESSION['nama_lengkap']);

header("Location: login.php");
exit();
?>
