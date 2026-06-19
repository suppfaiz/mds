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
        // Fetch payroll record details for auditing
        $stmt = $pdo->prepare("SELECT p.*, 
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.nama 
                        ELSE k.nama 
                     END AS nama_penerima
              FROM payroll p
              LEFT JOIN guru g ON p.tipe_penerima = 'guru' AND p.penerima_id = g.id
              LEFT JOIN karyawan k ON p.tipe_penerima = 'karyawan' AND p.penerima_id = k.id
              WHERE p.id = ?");
        $stmt->execute([$id]);
        $payroll = $stmt->fetch();
        
        if ($payroll) {
            // Delete record
            $del_stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
            $del_stmt->execute([$id]);
            
            logActivity($pdo, 'Hapus Payroll', 'Menghapus data gaji ' . $payroll['nama_penerima'] . ' (' . $payroll['tipe_penerima'] . ') periode ' . $payroll['bulan'] . '/' . $payroll['tahun'] . ' (ID: ' . $id . ')');
            $_SESSION['success_message'] = 'Data gaji ' . htmlspecialchars($payroll['nama_penerima']) . ' berhasil dihapus.';
        } else {
            $_SESSION['error_message'] = 'Data payroll tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal menghapus data: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'ID Payroll tidak valid.';
}

header("Location: index.php");
exit();
?>
