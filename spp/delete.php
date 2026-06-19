<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = 'ID Transaksi tidak ditentukan.';
    header("Location: index.php");
    exit();
}

if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];

try {
    // Get transaction details for activity logging
    $stmt = $pdo->prepare("
        SELECT s.*, sw.nama AS nama_siswa 
        FROM spp_pembayaran s 
        JOIN siswa sw ON s.siswa_id = sw.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $_SESSION['error_message'] = 'Transaksi pembayaran tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
    
    // Perform deletion
    $del_stmt = $pdo->prepare("DELETE FROM spp_pembayaran WHERE id = ?");
    $del_stmt->execute([$id]);
    
    logActivity($pdo, 'Hapus SPP', "Menghapus catatan SPP siswa {$payment['nama_siswa']} periode {$payment['bulan']}/{$payment['tahun']} senilai Rp " . number_format($payment['jumlah_bayar'], 0, ',', '.'));
    
    $_SESSION['success_message'] = 'Catatan pembayaran SPP berhasil dihapus.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal menghapus data: ' . $e->getMessage();
}

header("Location: index.php");
exit();
?>
