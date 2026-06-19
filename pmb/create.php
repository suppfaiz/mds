<?php
$path_prefix = '../';
$page_title = 'Tambah Pendaftar Offline';
$active_menu = 'pmb_data';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Only Super Admin and Operator can add applicants manually
checkRole(['super_admin', 'operator']);

$role = $_SESSION['role'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $nama = trim($_POST['nama']);
        $jenis_kelamin = trim($_POST['jenis_kelamin']);
        $tempat_lahir = trim($_POST['tempat_lahir']);
        $tanggal_lahir = trim($_POST['tanggal_lahir']);
        $asal_sekolah = trim($_POST['asal_sekolah']);
        $nama_ortu = trim($_POST['nama_ortu']);
        $no_hp = trim($_POST['no_hp']);
        $alamat = trim($_POST['alamat']);
        $status = trim($_POST['status']);
        
        // Validations
        if (empty($nama) || empty($jenis_kelamin) || empty($tempat_lahir) || empty($tanggal_lahir) || empty($asal_sekolah) || empty($nama_ortu) || empty($no_hp) || empty($alamat) || empty($status)) {
            $error = 'Semua data wajib diisi.';
        } elseif (!in_array($status, ['Pending', 'Diterima', 'Ditolak'])) {
            $error = 'Pilihan status pendaftaran tidak valid.';
        } else {
            // Document upload processing
            $uploaded_file = null;
            if (isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Kesalahan saat mengunggah dokumen bukti.';
                } else {
                    $file_name = $_FILES['dokumen']['name'];
                    $file_tmp = $_FILES['dokumen']['tmp_name'];
                    $file_size = $_FILES['dokumen']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file_ext, $allowed_exts)) {
                        $error = 'Format berkas tidak didukung. Hanya berkas PDF, JPG, dan PNG yang diperbolehkan.';
                    } elseif ($file_size > $max_size) {
                        $error = 'Ukuran berkas melebihi batas maksimum 5MB.';
                    } else {
                        // Secure upload name & directory
                        $secure_dir = $path_prefix . 'uploads/secure/';
                        if (!file_exists($secure_dir)) {
                            mkdir($secure_dir, 0755, true);
                        }
                        
                        $secure_name = 'pmb_doc_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $target_path = $secure_dir . $secure_name;
                        
                        if (move_uploaded_file($file_tmp, $target_path)) {
                            $uploaded_file = 'uploads/secure/' . $secure_name;
                        } else {
                            $error = 'Gagal menyimpan dokumen bukti di server.';
                        }
                    }
                }
            }
            
            // Save to Database
            if (empty($error)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Generate Unique Reg ID (PMB-YYYY-XXXX)
                    $year_prefix = 'PMB-' . date('Y') . '-';
                    $stmt_id = $pdo->prepare("
                        SELECT no_pendaftaran 
                        FROM pmb_pendaftar 
                        WHERE no_pendaftaran LIKE ? 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt_id->execute([$year_prefix . '%']);
                    $latest = $stmt_id->fetchColumn();
                    $counter = 1;
                    if ($latest) {
                        $parts = explode('-', $latest);
                        $counter = (int)end($parts) + 1;
                    }
                    $no_pendaftaran = $year_prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
                    
                    // Generate tracking token
                    $token = bin2hex(random_bytes(16));
                    
                    $catatan_panitia = isset($_POST['catatan_panitia']) ? trim($_POST['catatan_panitia']) : '';
                    
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO pmb_pendaftar (no_pendaftaran, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, asal_sekolah, nama_ortu, no_hp, alamat, status, dokumen_bukti, token, catatan_panitia)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_insert->execute([
                        $no_pendaftaran, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, 
                        $asal_sekolah, $nama_ortu, $no_hp, $alamat, $status, $uploaded_file, $token, $catatan_panitia
                    ]);
                    
                    $new_id = $pdo->lastInsertId();
                    $pdo->commit();
                    
                    logActivity($pdo, 'Tambah Pendaftar Offline', "Mendaftarkan pendaftar offline: $nama ($no_pendaftaran) dengan status $status");
                    
                    $_SESSION['success_message'] = "Calon pendaftar $nama ($no_pendaftaran) berhasil didaftarkan secara offline.";
                    header("Location: detail.php?id=" . $new_id);
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Pendaftaran gagal disimpan ke database: ' . $e->getMessage();
                }
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Tambah Pendaftar Offline (Manual)</h4>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-1 text-primary-emphasis"><i class="bi bi-person-plus-fill me-1 text-primary"></i> Formulir Registrasi Offline</h5>
        <p class="text-muted small">Registrasi manual ini digunakan untuk calon pendaftar yang datang langsung ke sekolah / jalur luar jaringan.</p>
    </div>
    
    <div class="card-body p-4 pt-2">
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-person-badge"></i> Data Pribadi Calon Siswa</h6>
                </div>

                <!-- Nama -->
                <div class="col-md-8">
                    <label for="nama" class="form-label">Nama Lengkap Murid <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama" name="nama" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" placeholder="Nama lengkap sesuai akta..." required>
                </div>

                <!-- Gender -->
                <div class="col-md-4">
                    <label for="jenis_kelamin" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                        <option value="">-- Pilih Gender --</option>
                        <option value="L" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] === 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="P" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] === 'P') ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                </div>

                <!-- Tempat Lahir -->
                <div class="col-md-4">
                    <label for="tempat_lahir" class="form-label">Tempat Lahir <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" value="<?php echo isset($_POST['tempat_lahir']) ? htmlspecialchars($_POST['tempat_lahir']) : ''; ?>" placeholder="Contoh: Bandung" required>
                </div>

                <!-- Tanggal Lahir -->
                <div class="col-md-4">
                    <label for="tanggal_lahir" class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo isset($_POST['tanggal_lahir']) ? htmlspecialchars($_POST['tanggal_lahir']) : ''; ?>" required>
                </div>

                <!-- Asal Sekolah -->
                <div class="col-md-4">
                    <label for="asal_sekolah" class="form-label">Asal Sekolah (SMP/MTs) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="asal_sekolah" name="asal_sekolah" value="<?php echo isset($_POST['asal_sekolah']) ? htmlspecialchars($_POST['asal_sekolah']) : ''; ?>" placeholder="Sekolah asal..." required>
                </div>

                <div class="col-12 mt-4">
                    <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-people-fill"></i> Kontak & Orang Tua</h6>
                </div>

                <!-- Orang Tua -->
                <div class="col-md-6">
                    <label for="nama_ortu" class="form-label">Nama Orang Tua / Wali <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_ortu" name="nama_ortu" value="<?php echo isset($_POST['nama_ortu']) ? htmlspecialchars($_POST['nama_ortu']) : ''; ?>" placeholder="Nama Ayah/Ibu/Wali..." required>
                </div>

                <!-- No HP -->
                <div class="col-md-6">
                    <label for="no_hp" class="form-label">Nomor Kontak WhatsApp / HP <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control font-monospace" id="no_hp" name="no_hp" value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" placeholder="Contoh: 08123456789" required>
                </div>

                <!-- Alamat -->
                <div class="col-12">
                    <label for="alamat" class="form-label">Alamat Domisili Lengkap <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Alamat lengkap tinggal..." required><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                </div>

                <div class="col-12 mt-4">
                    <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-gear-fill"></i> Administrasi & Evaluasi</h6>
                </div>

                <!-- Initial Status -->
                <div class="col-md-6">
                    <label for="status" class="form-label">Status Awal Pendaftar <span class="text-danger">*</span></label>
                    <select class="form-select border-primary bg-primary bg-opacity-10 text-primary-emphasis fw-semibold" id="status" name="status" required>
                        <option value="Pending" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'Pending') ? 'selected' : ''; ?>>Pending (Menunggu Verifikasi)</option>
                        <option value="Diterima" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Diterima') ? 'selected' : ''; ?>>Diterima (Lulus Seleksi langsung)</option>
                        <option value="Ditolak" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Ditolak') ? 'selected' : ''; ?>>Ditolak (Gugur seleksi)</option>
                    </select>
                </div>

                <!-- Dokumen Lampiran -->
                <div class="col-md-6">
                    <label for="dokumen" class="form-label">Upload Berkas Pendukung (Opsional)</label>
                    <input type="file" class="form-control" id="dokumen" name="dokumen" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="text-muted mt-1 d-block" style="font-size: 11px;">Mendukung PDF, JPG, PNG (maksimal 5MB).</small>
                </div>

                <!-- Catatan Panitia -->
                <div class="col-12">
                    <label for="catatan_panitia" class="form-label">Catatan / Keterangan Tambahan</label>
                    <textarea class="form-control" id="catatan_panitia" name="catatan_panitia" rows="3" placeholder="Masukkan catatan awal, instruksi, atau keterangan berkas pendaftar..."><?php echo isset($_POST['catatan_panitia']) ? htmlspecialchars($_POST['catatan_panitia']) : ''; ?></textarea>
                </div>
            </div>

            <hr class="my-4">
            
            <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-plus-circle me-1"></i> Daftarkan Calon Siswa</button>
        </form>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
