<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Check if user is logged in
checkLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Akses Ditolak: ID dokumen tidak ditentukan.");
}

$id = (int)$_GET['id'];

try {
    // Fetch document details
    $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        die("Akses Ditolak: Dokumen tidak ditemukan di database.");
    }
    
    // Check authorization rules
    $is_authorized = false;
    $role = $_SESSION['role'];
    
    if (in_array($role, ['super_admin', 'operator', 'kepala_sekolah'])) {
        $is_authorized = true;
    } elseif ($role === 'guru') {
        if ($doc['tipe_data'] === 'siswa') {
            // Teachers can see student documents
            $is_authorized = true;
        } elseif ($doc['tipe_data'] === 'guru') {
            // Teachers can only see their own documents
            $t_stmt = $pdo->prepare("SELECT id FROM guru WHERE LOWER(REPLACE(nama, ' ', '')) = LOWER(REPLACE(?, ' ', ''))");
            $t_stmt->execute([$_SESSION['nama_lengkap']]);
            $my_teacher = $t_stmt->fetch();
            if ($my_teacher && $doc['data_id'] == $my_teacher['id']) {
                $is_authorized = true;
            }
        }
    }
    
    if (!$is_authorized) {
        die("Akses Ditolak: Anda tidak memiliki wewenang untuk melihat dokumen ini.");
    }
    
    $file_path = $path_prefix . $doc['lokasi_file'];
    
    if (!file_exists($file_path)) {
        die("Kesalahan: Berkas fisik tidak ditemukan di server.");
    }
    
    // Clear buffer to avoid output issues
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers to serve file safely
    $mime = mime_content_type($file_path);
    $file_ext = strtolower(pathinfo($doc['nama_file'], PATHINFO_EXTENSION));
    $disposition = in_array($file_ext, ['pdf', 'png', 'jpg', 'jpeg']) ? 'inline' : 'attachment';
    
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($doc['nama_file']) . '"');
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
