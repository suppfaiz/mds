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
        $pdo->beginTransaction();
        
        // 1. Fetch teacher details for logging and unlinking photo
        $stmt = $pdo->prepare("SELECT nama, foto FROM guru WHERE id = ?");
        $stmt->execute([$id]);
        $guru = $stmt->fetch();
        
        if ($guru) {
            // 2. Fetch and delete digital documents from filesystem
            $doc_stmt = $pdo->prepare("SELECT lokasi_file FROM dokumen WHERE tipe_data = 'guru' AND data_id = ?");
            $doc_stmt->execute([$id]);
            $documents = $doc_stmt->fetchAll();
            
            foreach ($documents as $doc) {
                $file_path = '../' . $doc['lokasi_file'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            // Delete documents from database
            $del_docs = $pdo->prepare("DELETE FROM dokumen WHERE tipe_data = 'guru' AND data_id = ?");
            $del_docs->execute([$id]);
            
            // 3. Delete teacher profile photo from filesystem
            if (!empty($guru['foto'])) {
                $photo_path = '../' . $guru['foto'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }
            
            // 4. Delete payroll records associated with this teacher
            $del_payroll = $pdo->prepare("DELETE FROM payroll WHERE tipe_penerima = 'guru' AND penerima_id = ?");
            $del_payroll->execute([$id]);

            // Delete attendance records associated with this teacher
            $del_presensi = $pdo->prepare("DELETE FROM presensi_pegawai WHERE tipe_penerima = 'guru' AND penerima_id = ?");
            $del_presensi->execute([$id]);
            
            // 5. Delete teacher record from DB
            $del_guru = $pdo->prepare("DELETE FROM guru WHERE id = ?");
            $del_guru->execute([$id]);
            
            $pdo->commit();
            
            logActivity($pdo, 'Hapus Guru', 'Menghapus data guru: ' . $guru['nama'] . ' (ID: ' . $id . ')');
            $_SESSION['success_message'] = 'Data guru ' . htmlspecialchars($guru['nama']) . ' beserta dokumen terkait berhasil dihapus.';
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Guru tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Gagal menghapus data: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID Guru tidak valid.';
}

header("Location: index.php");
exit();
?>
