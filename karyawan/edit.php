<?php
$path_prefix = '../';
$page_title = 'Edit Data Karyawan';
$active_menu = 'karyawan';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';
$employee = null;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM karyawan WHERE id = ?");
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        
        if (!$employee) {
            $_SESSION['error_message'] = 'Karyawan tidak ditemukan.';
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        die("Gagal memuat data: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = 'ID Karyawan tidak valid.';
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $nik = trim($_POST['nik']);
    $nama = trim($_POST['nama']);
    $jabatan = trim($_POST['jabatan']);
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    
    $foto_db_path = $employee['foto']; // fallback to old photo

    // Server-side validation
    if (empty($nik) || empty($nama) || empty($jabatan) || empty($no_hp) || empty($alamat)) {
        $error = 'Harap isi seluruh kolom yang wajib ditandai bintang (*).';
    } else {
        try {
            // Check NIK uniqueness (excluding self)
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM karyawan WHERE nik = ? AND id != ?");
            $check_stmt->execute([$nik, $id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $error = 'NIK sudah terdaftar pada karyawan lain.';
            } else {
                // Process photo upload if set
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['foto']['tmp_name'];
                    $file_name = $_FILES['foto']['name'];
                    $file_size = $_FILES['foto']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $allowed_exts = ['jpg', 'jpeg', 'png'];
                    
                    if (!in_array($file_ext, $allowed_exts)) {
                        $error = 'Format foto tidak diizinkan. Hanya JPG, JPEG, dan PNG.';
                    } elseif ($file_size > 2 * 1024 * 1024) {
                        $error = 'Ukuran foto maksimal 2MB.';
                    } else {
                        $upload_dir = '../uploads/karyawan/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_file_name = 'photo_' . $nik . '_' . time() . '.' . $file_ext;
                        $dest_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $dest_path)) {
                            // Delete old photo if exists
                            if (!empty($employee['foto']) && file_exists('../' . $employee['foto'])) {
                                unlink('../' . $employee['foto']);
                            }
                            $foto_db_path = 'uploads/karyawan/' . $new_file_name;
                        } else {
                            $error = 'Gagal mengupload foto profil baru.';
                        }
                    }
                }

                if (empty($error)) {
                    // Execute database UPDATE
                    $stmt = $pdo->prepare("UPDATE karyawan SET 
                        nik = ?, nama = ?, jabatan = ?, no_hp = ?, email = ?, alamat = ?, foto = ? 
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $nik, $nama, $jabatan, $no_hp, $email, $alamat, $foto_db_path, $id
                    ]);
                    
                    logActivity($pdo, 'Edit Karyawan', 'Mengubah data karyawan: ' . $nama . ' (NIK: ' . $nik . ', ID: ' . $id . ')');
                    
                    $_SESSION['success_message'] = 'Data karyawan ' . htmlspecialchars($nama) . ' berhasil diperbarui.';
                    header("Location: index.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = 'Kesalahan database: ' . $e->getMessage();
        }
    }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <h4 class="fw-bold mb-0">Edit Data Karyawan</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="row g-4">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Informasi Kepegawaian & Profil</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nik" class="form-label fw-semibold small">NIK (Nomor Induk Kependudukan) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nik" name="nik" placeholder="Masukkan 16 digit NIK" value="<?php echo htmlspecialchars($employee['nik']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nama" class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama lengkap" value="<?php echo htmlspecialchars($employee['nama']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="jabatan" class="form-label fw-semibold small">Jabatan / Peran <span class="text-danger">*</span></label>
                            <select class="form-select" id="jabatan" name="jabatan" required>
                                <option value="" disabled>Pilih Jabatan...</option>
                                <option value="Staf TU (Tata Usaha)" <?php echo ($employee['jabatan'] === 'Staf TU (Tata Usaha)') ? 'selected' : ''; ?>>Staf TU (Tata Usaha)</option>
                                <option value="Staf Perpustakaan" <?php echo ($employee['jabatan'] === 'Staf Perpustakaan') ? 'selected' : ''; ?>>Staf Perpustakaan</option>
                                <option value="Laboran" <?php echo ($employee['jabatan'] === 'Laboran') ? 'selected' : ''; ?>>Laboran</option>
                                <option value="Petugas Keamanan" <?php echo ($employee['jabatan'] === 'Petugas Keamanan') ? 'selected' : ''; ?>>Petugas Keamanan</option>
                                <option value="Petugas Kebersihan" <?php echo ($employee['jabatan'] === 'Petugas Kebersihan') ? 'selected' : ''; ?>>Petugas Kebersihan</option>
                                <option value="Supir" <?php echo ($employee['jabatan'] === 'Supir') ? 'selected' : ''; ?>>Supir</option>
                                <option value="Lainnya" <?php echo ($employee['jabatan'] === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="no_hp" class="form-label fw-semibold small">Nomor HP / WA <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" placeholder="Contoh: 08123456789" value="<?php echo htmlspecialchars($employee['no_hp']); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label for="email" class="form-label fw-semibold small">Alamat Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Contoh: nama@domain.com" value="<?php echo htmlspecialchars($employee['email']); ?>">
                        </div>
                        
                        <div class="col-12">
                            <label for="alamat" class="form-label fw-semibold small">Alamat Tinggal <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap rumah..." required><?php echo htmlspecialchars($employee['alamat']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Upload Photo Column -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4 text-center">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0">Foto Profil</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="mx-auto bg-light rounded border d-flex align-items-center justify-content-center overflow-hidden mb-3" style="width: 150px; height: 180px;" id="photoPreviewContainer">
                            <?php if (!empty($employee['foto']) && file_exists('../' . $employee['foto'])): ?>
                                <i class="bi bi-person text-secondary d-none" style="font-size: 80px;" id="placeholderIcon"></i>
                                <img id="photoPreview" src="../<?php echo htmlspecialchars($employee['foto']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person text-secondary" style="font-size: 80px;" id="placeholderIcon"></i>
                                <img id="photoPreview" class="d-none" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="foto" class="form-label fw-semibold small">Ganti Foto Profil</label>
                        <input class="form-control form-control-sm" type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png" onchange="previewImage(this)">
                        <div class="form-text" style="font-size: 11px;">Format: JPG, JPEG, PNG. Ukuran maks: 2MB.</div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 mb-4 p-3 bg-light">
                <button type="submit" class="btn btn-primary w-100 fw-bold mb-2"><i class="bi bi-save"></i> Perbarui Data</button>
                <a href="index.php" class="btn btn-outline-secondary w-100 fw-bold">Batal</a>
            </div>
        </div>
    </div>
</form>

<script>
function previewImage(input) {
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('placeholderIcon');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) {
                placeholder.classList.add('d-none');
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        <?php if (!empty($employee['foto']) && file_exists('../' . $employee['foto'])): ?>
            preview.src = '../<?php echo htmlspecialchars($employee['foto']); ?>';
            preview.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
        <?php else: ?>
            preview.src = '';
            preview.classList.add('d-none');
            if (placeholder) placeholder.classList.remove('d-none');
        <?php endif; ?>
    }
}
</script>

<?php include $path_prefix . 'includes/footer.php'; ?>
