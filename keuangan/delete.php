<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth: Block Guru and Kepala Sekolah (Only Admin & Operator can delete)
checkRole(['super_admin', 'operator']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['error_message'] = 'ID transaksi tidak valid.';
    header("Location: index.php");
    exit();
}

if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    header("Location: index.php");
    exit();
}

try {
    // Fetch transaction details for logging
    $stmt = $pdo->prepare("SELECT * FROM keuangan_transaksi WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();

    if ($t) {
        $stmt_del = $pdo->prepare("DELETE FROM keuangan_transaksi WHERE id = ?");
        $stmt_del->execute([$id]);

        logActivity($pdo, 'Hapus Kas', "Menghapus transaksi " . $t['tipe'] . " ID $id sebesar Rp " . number_format($t['nominal'], 0, ',', '.') . " kategori " . $t['kategori']);
        $_SESSION['success_message'] = 'Transaksi arus kas berhasil dihapus.';
    } else {
        $_SESSION['error_message'] = 'Transaksi tidak ditemukan atau sudah dihapus.';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal menghapus transaksi: ' . $e->getMessage();
}

header("Location: index.php");
exit();
?>
