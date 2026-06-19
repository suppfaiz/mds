<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_parent = isset($_SESSION['pmb_parent_id']);
$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'operator', 'kepala_sekolah']);

if (!$is_parent && !$is_admin) {
    die("Akses Ditolak: Anda tidak memiliki wewenang untuk mengakses halaman ini.");
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Akses Ditolak: ID pendaftar tidak ditentukan.");
}

$id = (int)$_GET['id'];

try {
    // Fetch document details from pmb_pendaftar
    $stmt = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    
    if (!$p || empty($p['dokumen_bukti'])) {
        die("Akses Ditolak: Dokumen bukti tidak ditemukan.");
    }
    
    // Check parent ownership if not admin
    if ($is_parent && !$is_admin) {
        if ((int)$p['pmb_akun_id'] !== (int)$_SESSION['pmb_parent_id']) {
            die("Akses Ditolak: Anda tidak memiliki wewenang untuk melihat berkas ini.");
        }
    }
    
    $file_path = $path_prefix . $p['dokumen_bukti'];
    
    if (!file_exists($file_path)) {
        die("Kesalahan: Berkas fisik tidak ditemukan di server.");
    }
    
    // Clear buffer to avoid output corruption
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers to serve file safely
    $mime = mime_content_type($file_path);
    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $disposition = in_array($file_ext, ['pdf', 'png', 'jpg', 'jpeg']) ? 'inline' : 'attachment';
    
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="pmb_bukti_' . str_replace(' ', '_', $p['nama']) . '.' . $file_ext . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=3600');
    header('Pragma: public');
    
    // Stream file contents
    readfile($file_path);
    exit();
    
} catch (PDOException $e) {
    die("Kesalahan database: " . $e->getMessage());
}
?>
