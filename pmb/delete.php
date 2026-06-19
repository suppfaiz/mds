<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth check: Block Guru & Kepala Sekolah (Only Admin & Operator can delete)
checkRole(['super_admin', 'operator']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['error_message'] = 'ID pendaftar tidak valid.';
    header("Location: index.php");
    exit();
}

if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    header("Location: index.php");
    exit();
}

try {
    // Fetch applicant details for unlinking files and logging
    $stmt = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();

    if ($p) {
        $pdo->beginTransaction();
        
        // Delete from database
        $stmt_del = $pdo->prepare("DELETE FROM pmb_pendaftar WHERE id = ?");
        $stmt_del->execute([$id]);
        
        $pdo->commit();

        // Check and delete physical file
        if (!empty($p['dokumen_bukti'])) {
            $file_path = $path_prefix . $p['dokumen_bukti'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        logActivity($pdo, 'Hapus PMB', "Menghapus calon pendaftar " . $p['nama'] . " (Registrasi: " . $p['no_pendaftaran'] . ")");
        $_SESSION['success_message'] = 'Data pendaftar PMB berhasil dihapus.';
    } else {
        $_SESSION['error_message'] = 'Data pendaftar tidak ditemukan.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = 'Gagal menghapus data: ' . $e->getMessage();
}

header("Location: index.php");
exit();
?>
