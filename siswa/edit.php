<?php
$path_prefix = '../';
$page_title = 'Edit Data Siswa';
$active_menu = 'siswa';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$siswa = null;

// Get student ID
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
        $stmt->execute([$id]);
        $siswa = $stmt->fetch();
        
        if (!$siswa) {
            $_SESSION['error_message'] = 'Siswa tidak ditemukan.';
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal memuat data siswa: ' . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = 'ID Siswa tidak valid.';
    header("Location: index.php");
    exit();
}

// Load classes for selector dropdown
try {
    $kelas_stmt = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");
    $classes = $kelas_stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat daftar kelas: ' . $e->getMessage();
    $classes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $nis = trim($_POST['nis']);
    $nisn = trim($_POST['nisn']);
    $nama = trim($_POST['nama']);
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $tempat_lahir = trim($_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
    $alamat = trim($_POST['alamat']);
    $agama = trim($_POST['agama']);
    $kelas_id = !empty($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : null;
    $tahun_masuk = !empty($_POST['tahun_masuk']) ? (int)$_POST['tahun_masuk'] : date('Y');
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);
    $nama_ayah = trim($_POST['nama_ayah']);
    $nama_ibu = trim($_POST['nama_ibu']);
    $pekerjaan_ayah = trim($_POST['pekerjaan_ayah'] ?? '');
    $pekerjaan_ibu = trim($_POST['pekerjaan_ibu'] ?? '');
    $no_hp_ortu = trim($_POST['no_hp_ortu'] ?? '');
    $alamat_ortu = trim($_POST['alamat_ortu'] ?? '');
    $nik_ayah = trim($_POST['nik_ayah'] ?? '');
    $nik_ibu = trim($_POST['nik_ibu'] ?? '');
    
    $foto_db_path = $siswa['foto'];

    // Validation
    if (empty($nis) || empty($nisn) || empty($nama) || empty($jenis_kelamin) || empty($tempat_lahir) || empty($tanggal_lahir) || empty($alamat) || empty($agama) || empty($no_hp)) {
        $error = 'Harap isi seluruh kolom yang wajib ditandai bintang (*).';
    } else {
        try {
            // Check uniqueness of NIS and NISN except for current record
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE (nis = ? OR nisn = ?) AND id != ?");
            $check_stmt->execute([$nis, $nisn, $id]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = 'NIS atau NISN sudah terdaftar di siswa lain.';
            } else {
                // Check if new photo uploaded
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
                        $upload_dir = '../uploads/siswa/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_file_name = 'photo_' . $nis . '_' . time() . '.' . $file_ext;
                        $dest_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $dest_path)) {
                            // Delete old photo if exists
                            if (!empty($siswa['foto']) && file_exists('../' . $siswa['foto'])) {
                                unlink('../' . $siswa['foto']);
                            }
                            $foto_db_path = 'uploads/siswa/' . $new_file_name;
                        } else {
                            $error = 'Gagal mengunggah foto profil baru.';
                        }
                    }
                }

                if (empty($error)) {
                    // Update DB record
                    $stmt = $pdo->prepare("UPDATE siswa SET 
                        nis = ?, nisn = ?, nama = ?, jenis_kelamin = ?, tempat_lahir = ?, 
                        tanggal_lahir = ?, alamat = ?, agama = ?, kelas_id = ?, tahun_masuk = ?, 
                        no_hp = ?, email = ?, nama_ayah = ?, nama_ibu = ?, pekerjaan_ayah = ?, pekerjaan_ibu = ?, no_hp_ortu = ?, alamat_ortu = ?, nik_ayah = ?, nik_ibu = ?, foto = ? 
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $nis, $nisn, $nama, $jenis_kelamin, $tempat_lahir, 
                        $tanggal_lahir, $alamat, $agama, $kelas_id, $tahun_masuk, 
                        $no_hp, $email, $nama_ayah, $nama_ibu, $pekerjaan_ayah, $pekerjaan_ibu, $no_hp_ortu, $alamat_ortu, $nik_ayah, $nik_ibu, $foto_db_path, $id
                    ]);
                    
                    logActivity($pdo, 'Edit Siswa', 'Mengupdate data siswa: ' . $nama . ' (NIS: ' . $nis . ', ID: ' . $id . ')');
                    
                    $_SESSION['success_message'] = 'Data siswa ' . htmlspecialchars($nama) . ' berhasil diupdate.';
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
    <h4 class="fw-bold mb-0">Edit Data Siswa</h4>
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
                    <h5 class="fw-bold mb-0">Informasi Pribadi & Akademik</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nis" class="form-label fw-semibold small">NIS <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nis" name="nis" value="<?php echo htmlspecialchars($siswa['nis']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nisn" class="form-label fw-semibold small">NISN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nisn" name="nisn" value="<?php echo htmlspecialchars($siswa['nisn']); ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label for="nama" class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" value="<?php echo htmlspecialchars($siswa['nama']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="jenis_kelamin" class="form-label fw-semibold small">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="L" <?php echo $siswa['jenis_kelamin'] === 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo $siswa['jenis_kelamin'] === 'P' ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="agama" class="form-label fw-semibold small">Agama <span class="text-danger">*</span></label>
                            <select class="form-select" id="agama" name="agama" required>
                                <option value="Islam" <?php echo $siswa['agama'] === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                <option value="Kristen" <?php echo $siswa['agama'] === 'Kristen' ? 'selected' : ''; ?>>Kristen</option>
                                <option value="Katolik" <?php echo $siswa['agama'] === 'Katolik' ? 'selected' : ''; ?>>Katolik</option>
                                <option value="Hindu" <?php echo $siswa['agama'] === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                <option value="Buddha" <?php echo $siswa['agama'] === 'Buddha' ? 'selected' : ''; ?>>Buddha</option>
                                <option value="Khonghucu" <?php echo $siswa['agama'] === 'Khonghucu' ? 'selected' : ''; ?>>Khonghucu</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="tempat_lahir" class="form-label fw-semibold small">Tempat Lahir <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($siswa['tempat_lahir']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="tanggal_lahir" class="form-label fw-semibold small">Tanggal Lahir <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo $siswa['tanggal_lahir']; ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="kelas_id" class="form-label fw-semibold small">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" id="kelas_id" name="kelas_id" required>
                                <option value="" disabled>Pilih Kelas...</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $siswa['kelas_id'] == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="tahun_masuk" class="form-label fw-semibold small">Tahun Masuk <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tahun_masuk" name="tahun_masuk" value="<?php echo htmlspecialchars($siswa['tahun_masuk']); ?>" min="2000" max="<?php echo date('Y')+1; ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label for="alamat" class="form-label fw-semibold small">Alamat Lengkap <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?php echo htmlspecialchars($siswa['alamat']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Informasi Orang Tua / Wali</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nama_ayah" class="form-label fw-semibold small">Nama Ayah</label>
                            <input type="text" class="form-control" id="nama_ayah" name="nama_ayah" value="<?php echo htmlspecialchars($siswa['nama_ayah'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nik_ayah" class="form-label fw-semibold small">NIK Ayah</label>
                            <input type="text" class="form-control" id="nik_ayah" name="nik_ayah" placeholder="NIK (KTP) Ayah" value="<?php echo htmlspecialchars($siswa['nik_ayah'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="pekerjaan_ayah" class="form-label fw-semibold small">Pekerjaan Ayah</label>
                            <input type="text" class="form-control" id="pekerjaan_ayah" name="pekerjaan_ayah" value="<?php echo htmlspecialchars($siswa['pekerjaan_ayah'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nama_ibu" class="form-label fw-semibold small">Nama Ibu</label>
                            <input type="text" class="form-control" id="nama_ibu" name="nama_ibu" value="<?php echo htmlspecialchars($siswa['nama_ibu'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="nik_ibu" class="form-label fw-semibold small">NIK Ibu</label>
                            <input type="text" class="form-control" id="nik_ibu" name="nik_ibu" placeholder="NIK (KTP) Ibu" value="<?php echo htmlspecialchars($siswa['nik_ibu'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="pekerjaan_ibu" class="form-label fw-semibold small">Pekerjaan Ibu</label>
                            <input type="text" class="form-control" id="pekerjaan_ibu" name="pekerjaan_ibu" value="<?php echo htmlspecialchars($siswa['pekerjaan_ibu'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="no_hp_ortu" class="form-label fw-semibold small">Nomor HP Orang Tua</label>
                            <input type="text" class="form-control" id="no_hp_ortu" name="no_hp_ortu" value="<?php echo htmlspecialchars($siswa['no_hp_ortu'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="alamat_ortu" class="form-label fw-semibold small">Alamat Orang Tua</label>
                            <textarea class="form-control" id="alamat_ortu" name="alamat_ortu" rows="2" placeholder="Tulis alamat jika berbeda dengan siswa"><?php echo htmlspecialchars($siswa['alamat_ortu'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Options Column -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Foto Profil</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="profile-img-container mb-3 bg-light d-flex align-items-center justify-content-center border" style="width: 130px; height: 130px;">
                        <?php if (!empty($siswa['foto']) && file_exists('../' . $siswa['foto'])): ?>
                            <img src="../<?php echo htmlspecialchars($siswa['foto']); ?>" alt="Foto" class="profile-img">
                        <?php else: ?>
                            <i class="bi bi-person text-secondary" style="font-size: 4rem;"></i>
                        <?php endif; ?>
                    </div>
                    <label for="foto" class="form-label fw-semibold small">Ganti Foto</label>
                    <input class="form-control form-control-sm" type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png">
                    <div class="form-text" style="font-size: 11px;">Hanya JPG/PNG. Maksimal 2MB. Kosongkan jika tidak diganti.</div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Kontak</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="no_hp" class="form-label fw-semibold small">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($siswa['no_hp']); ?>" required>
                    </div>
                    
                    <div class="mb-0">
                        <label for="email" class="form-label fw-semibold small">Alamat Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($siswa['email']); ?>">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                <i class="bi bi-save-fill me-2"></i> Perbarui Data Siswa
            </button>
        </div>
    </div>
</form>

<?php include $path_prefix . 'includes/footer.php'; ?>
