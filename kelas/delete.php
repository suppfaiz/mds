<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check role
checkRole(['super_admin', 'operator']);

if (isset($_GET['id'])) {
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        header("Location: index.php");
        exit();
    }
    $id = (int)$_GET['id'];
    
    try {
        // Fetch class name for logging
        $stmt = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $stmt->execute([$id]);
        $class = $stmt->fetch();
        
        if ($class) {
            // Delete class (Foreign key SET NULL constraint handles relation safety)
            $del_stmt = $pdo->prepare("DELETE FROM kelas WHERE id = ?");
            $del_stmt->execute([$id]);
            
            logActivity($pdo, 'Hapus Kelas', 'Menghapus kelas: ' . $class['nama_kelas'] . ' (ID: ' . $id . ')');
            $_SESSION['success_message'] = 'Kelas ' . htmlspecialchars($class['nama_kelas']) . ' berhasil dihapus.';
        } else {
            $_SESSION['error_message'] = 'Kelas tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal menghapus kelas: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID Kelas tidak valid.';
}

header("Location: index.php");
exit();
?>
