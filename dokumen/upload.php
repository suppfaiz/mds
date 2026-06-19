<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check roles - Admins, Operators, and Teachers can upload
checkRole(['super_admin', 'operator', 'guru']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        $tipe_data = $_POST['tipe_data'] ?? '';
        $data_id = isset($_POST['data_id']) ? (int)$_POST['data_id'] : 0;
        if (in_array($tipe_data, ['siswa', 'guru', 'karyawan']) && $data_id > 0) {
            header("Location: ../" . $tipe_data . "/detail.php?id=" . $data_id);
        } else {
            header("Location: ../index.php");
        }
        exit();
    }
    $tipe_data = $_POST['tipe_data'] ?? ''; // 'siswa', 'guru', or 'karyawan'
    $data_id = isset($_POST['data_id']) ? (int)$_POST['data_id'] : 0;
    $kategori = trim($_POST['kategori'] ?? '');

    // Basic parameters validation
    if (!in_array($tipe_data, ['siswa', 'guru', 'karyawan']) || $data_id <= 0 || empty($kategori)) {
        $_SESSION['error_message'] = 'Parameter unggah dokumen tidak lengkap atau tidak valid.';
        header("Location: ../index.php");
        exit();
    }

    // Teacher upload permission lock: ensure a teacher can only upload documents to their own profile
    if ($_SESSION['role'] === 'guru') {
        try {
            $stmt = $pdo->prepare("SELECT nama FROM guru WHERE id = ?");
            $stmt->execute([$data_id]);
            $owner = $stmt->fetch();
            if (!$owner || strtolower(str_replace(' ', '', $owner['nama'])) !== strtolower(str_replace(' ', '', $_SESSION['nama_lengkap']))) {
                $_SESSION['error_message'] = 'Anda tidak memiliki hak akses untuk mengunggah dokumen guru lain.';
                header("Location: ../guru/index.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Kesalahan otorisasi: ' . $e->getMessage();
            header("Location: ../guru/index.php");
            exit();
        }
    }

    // Handle file upload
    if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_dokumen']['tmp_name'];
        $file_original_name = $_FILES['file_dokumen']['name'];
        $file_size = $_FILES['file_dokumen']['size'];
        $file_ext = strtolower(pathinfo($file_original_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_file_size = 5 * 1024 * 1024; // 5MB limit

        if (!in_array($file_ext, $allowed_exts)) {
            $_SESSION['error_message'] = 'Format file tidak diizinkan. Hanya PDF, JPG, JPEG, dan PNG.';
        } elseif ($file_size > $max_file_size) {
            $_SESSION['error_message'] = 'Ukuran file terlalu besar. Maksimal 5MB.';
        } else {
            // Determine directory
            $upload_dir = '../uploads/secure/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Create unique file name
            $clean_kategori = strtolower(str_replace(' ', '_', $kategori));
            $new_file_name = 'doc_' . $tipe_data . '_' . $clean_kategori . '_' . $data_id . '_' . time() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $db_location = 'uploads/secure/' . $new_file_name;

                try {
                    $stmt = $pdo->prepare("INSERT INTO dokumen (tipe_data, data_id, kategori, nama_file, lokasi_file) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$tipe_data, $data_id, $kategori, $file_original_name, $db_location]);

                    logActivity($pdo, 'Upload Dokumen', 'Mengunggah ' . $kategori . ' untuk ' . $tipe_data . ' ID: ' . $data_id . ' (' . $file_original_name . ')');
                    $_SESSION['success_message'] = 'Dokumen ' . htmlspecialchars($kategori) . ' berhasil diunggah.';
                } catch (PDOException $e) {
                    // Remove file if database write fails
                    if (file_exists($dest_path)) {
                        unlink($dest_path);
                    }
                    $_SESSION['error_message'] = 'Gagal menyimpan data ke database: ' . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = 'Gagal memindahkan file ke direktori tujuan.';
            }
        }
    } else {
        $_SESSION['error_message'] = 'Tidak ada berkas yang diunggah atau terjadi kesalahan pengiriman berkas.';
    }

    // Redirect back to detail page
    header("Location: ../" . $tipe_data . "/detail.php?id=" . $data_id);
    exit();
} else {
    header("Location: ../index.php");
    exit();
}
?>
