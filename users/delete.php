<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check role - Only Super Admin can access
checkRole(['super_admin']);

if (isset($_GET['id'])) {
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        header("Location: index.php");
        exit();
    }
    $id = $_GET['id'];
    
    // Prevent self-deletion
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'Anda tidak dapat menghapus akun Anda sendiri.';
        header("Location: index.php");
        exit();
    }
    
    try {
        // Fetch username first for logs
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            logActivity($pdo, 'Hapus User', 'Menghapus user: ' . $user['username'] . ' (ID: ' . $id . ')');
            $_SESSION['success_message'] = 'User ' . htmlspecialchars($user['username']) . ' berhasil dihapus.';
        } else {
            $_SESSION['error_message'] = 'User tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal menghapus user: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID User tidak valid.';
}

header("Location: index.php");
exit();
?>
