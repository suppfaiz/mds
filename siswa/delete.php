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
        
        // 1. Fetch student info for logging and deleting photo
        $stmt = $pdo->prepare("SELECT nama, foto FROM siswa WHERE id = ?");
        $stmt->execute([$id]);
        $siswa = $stmt->fetch();
        
        if ($siswa) {
            // 2. Fetch and delete all digital documents from filesystem
            $doc_stmt = $pdo->prepare("SELECT lokasi_file FROM dokumen WHERE tipe_data = 'siswa' AND data_id = ?");
            $doc_stmt->execute([$id]);
            $documents = $doc_stmt->fetchAll();
            
            foreach ($documents as $doc) {
                $file_path = '../' . $doc['lokasi_file'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            // Delete document rows from database
            $del_docs = $pdo->prepare("DELETE FROM dokumen WHERE tipe_data = 'siswa' AND data_id = ?");
            $del_docs->execute([$id]);
            
            // 3. Delete student profile photo from filesystem
            if (!empty($siswa['foto'])) {
                $photo_path = '../' . $siswa['foto'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }
            
            // 4. Delete student record from DB
            $del_siswa = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
            $del_siswa->execute([$id]);
            
            $pdo->commit();
            
            logActivity($pdo, 'Hapus Siswa', 'Menghapus data siswa: ' . $siswa['nama'] . ' (ID: ' . $id . ')');
            $_SESSION['success_message'] = 'Data siswa ' . htmlspecialchars($siswa['nama']) . ' beserta dokumen terkait berhasil dihapus.';
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Siswa tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Gagal menghapus data: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID Siswa tidak valid.';
}

header("Location: index.php");
exit();
?>
