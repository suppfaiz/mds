<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check roles - Admins, Operators, and Teachers can delete (with ownership check for teachers)
checkRole(['super_admin', 'operator', 'guru']);

if (isset($_GET['id'])) {
    $siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $karyawan_id = isset($_GET['karyawan_id']) ? (int)$_GET['karyawan_id'] : 0;
    
    $redirect_url = '../index.php';
    if ($siswa_id > 0) {
        $redirect_url = '../siswa/detail.php?id=' . $siswa_id;
    } elseif ($guru_id > 0) {
        $redirect_url = '../guru/detail.php?id=' . $guru_id;
    } elseif ($karyawan_id > 0) {
        $redirect_url = '../karyawan/detail.php?id=' . $karyawan_id;
    }

    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        header("Location: " . $redirect_url);
        exit();
    }
    $id = (int)$_GET['id'];

    try {
        // Fetch document info
        $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if ($doc) {
            // Teacher permission lock: ensure a teacher can only delete documents belonging to their own profile
            if ($_SESSION['role'] === 'guru') {
                if ($doc['tipe_data'] !== 'guru' || $doc['data_id'] != $guru_id) {
                    $_SESSION['error_message'] = 'Anda tidak memiliki hak akses untuk menghapus dokumen ini.';
                    header("Location: " . $redirect_url);
                    exit();
                }
                
                // Double check teacher profile name
                $t_stmt = $pdo->prepare("SELECT nama FROM guru WHERE id = ?");
                $t_stmt->execute([$guru_id]);
                $teacher = $t_stmt->fetch();
                if (!$teacher || strtolower(str_replace(' ', '', $teacher['nama'])) !== strtolower(str_replace(' ', '', $_SESSION['nama_lengkap']))) {
                    $_SESSION['error_message'] = 'Anda tidak memiliki hak akses untuk menghapus dokumen ini.';
                    header("Location: ../guru/index.php");
                    exit();
                }
            }

            // Delete file from disk
            $file_path = '../' . $doc['lokasi_file'];
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $file_deleted = true;
                } else {
                    $file_deleted = false;
                    error_log("Gagal menghapus file dari disk: " . $file_path);
                }
            } else {
                $file_deleted = true; // file already gone, continue DB delete
            }

            if ($file_deleted) {
                // Delete row from DB
                $del_stmt = $pdo->prepare("DELETE FROM dokumen WHERE id = ?");
                $del_stmt->execute([$id]);

                logActivity($pdo, 'Hapus Dokumen', 'Menghapus dokumen ' . $doc['kategori'] . ' (' . $doc['nama_file'] . ') untuk ' . $doc['tipe_data'] . ' ID: ' . $doc['data_id']);
                $_SESSION['success_message'] = 'Dokumen ' . htmlspecialchars($doc['kategori']) . ' berhasil dihapus.';
            } else {
                $_SESSION['error_message'] = 'Gagal menghapus berkas fisik dari server. Penghapusan database dibatalkan.';
            }
        } else {
            $_SESSION['error_message'] = 'Dokumen tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal memproses penghapusan dokumen: ' . $e->getMessage();
    }

    header("Location: " . $redirect_url);
    exit();
} else {
    header("Location: ../index.php");
    exit();
}
?>
