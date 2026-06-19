<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check roles - Admins, Operators, and Teachers can delete grades
checkRole(['super_admin', 'operator', 'guru']);

if (isset($_GET['id']) && isset($_GET['siswa_id'])) {
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        header("Location: index.php");
        exit();
    }
    $id = (int)$_GET['id'];
    $siswa_id = (int)$_GET['siswa_id'];
    
    try {
        // Fetch grade info for logging
        $stmt = $pdo->prepare("
            SELECT n.mata_pelajaran, s.nama 
            FROM nilai n 
            JOIN siswa s ON n.siswa_id = s.id 
            WHERE n.id = ? AND n.siswa_id = ?
        ");
        $stmt->execute([$id, $siswa_id]);
        $data = $stmt->fetch();
        
        if ($data) {
            $del_stmt = $pdo->prepare("DELETE FROM nilai WHERE id = ? AND siswa_id = ?");
            $del_stmt->execute([$id, $siswa_id]);
            
            logActivity($pdo, 'Hapus Nilai', 'Menghapus nilai ' . $data['mata_pelajaran'] . ' untuk siswa: ' . $data['nama'] . ' (ID: ' . $siswa_id . ')');
            $_SESSION['success_message'] = 'Nilai pelajaran ' . htmlspecialchars($data['mata_pelajaran']) . ' berhasil dihapus.';
        } else {
            $_SESSION['error_message'] = 'Entry nilai tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal menghapus nilai: ' . $e->getMessage();
    }
    
    header("Location: detail.php?id=" . $siswa_id . "#transkrip");
    exit();
} else {
    $_SESSION['error_message'] = 'Parameter tidak valid.';
    header("Location: index.php");
    exit();
}
?>
