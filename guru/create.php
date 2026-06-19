<?php
$path_prefix = '../';
$page_title = 'Tambah Data Guru';
$active_menu = 'guru';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama']);
    $mata_pelajaran = trim($_POST['mata_pelajaran']);
    $jabatan = trim($_POST['jabatan']);
    $pendidikan_terakhir = trim($_POST['pendidikan_terakhir']);
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    
    // Normalize empty NIP to NULL
    $nip_db = ($nip !== '') ? $nip : null;
    $foto_db_path = null;

    // Server-side validation
    if (empty($nama) || empty($mata_pelajaran) || empty($jabatan) || empty($pendidikan_terakhir) || empty($no_hp) || empty($alamat)) {
        $error = 'Harap isi seluruh kolom yang wajib ditandai bintang (*).';
    } else {
        try {
            // Check NIP uniqueness if NIP is provided
            $nip_exists = false;
            if ($nip_db !== null) {
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ?");
                $check_stmt->execute([$nip_db]);
                if ($check_stmt->fetchColumn() > 0) {
                    $nip_exists = true;
                }
            }

            if ($nip_exists) {
                $error = 'NIP sudah terdaftar di sistem.';
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
                        $upload_dir = '../uploads/guru/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $ident = ($nip_db !== null) ? $nip_db : time();
                        $new_file_name = 'photo_' . $ident . '_' . time() . '.' . $file_ext;
                        $dest_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $dest_path)) {
                            $foto_db_path = 'uploads/guru/' . $new_file_name;
                        } else {
                            $error = 'Gagal mengupload foto profil.';
                        }
                    }
                }

                if (empty($error)) {
                    // Execute database INSERT
                    $stmt = $pdo->prepare("INSERT INTO guru 
                        (nip, nama, mata_pelajaran, jabatan, pendidikan_terakhir, no_hp, email, alamat, foto) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $nip_db, $nama, $mata_pelajaran, $jabatan, $pendidikan_terakhir, 
                        $no_hp, $email, $alamat, $foto_db_path
                    ]);
                    
                    $guru_id = $pdo->lastInsertId();
                    logActivity($pdo, 'Tambah Guru', 'Menambahkan guru: ' . $nama . ' (NIP: ' . ($nip_db ?? '-') . ', ID: ' . $guru_id . ')');
                    
                    $_SESSION['success_message'] = 'Data guru ' . htmlspecialchars($nama) . ' berhasil disimpan.';
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
    <h4 class="fw-bold mb-0">Tambah Data Guru</h4>
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
                            <label for="nip" class="form-label fw-semibold small">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Masukkan NIP (kosongkan jika tidak ada)" value="<?php echo isset($_POST['nip']) ? htmlspecialchars($_POST['nip']) : ''; ?>">
                            <div class="form-text" style="font-size: 11px;">Kosongkan jika Guru Honorer / belum memiliki NIP.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nama" class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama lengkap beserta gelar" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="mata_pelajaran" class="form-label fw-semibold small">Mata Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="mata_pelajaran" name="mata_pelajaran" placeholder="Contoh: Matematika, Fisika" value="<?php echo isset($_POST['mata_pelajaran']) ? htmlspecialchars($_POST['mata_pelajaran']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="jabatan" class="form-label fw-semibold small">Jabatan Guru <span class="text-danger">*</span></label>
                            <select class="form-select" id="jabatan" name="jabatan" required>
                                <option value="" disabled selected>Pilih Jabatan...</option>
                                <option value="Kepala Sekolah" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Kepala Sekolah') ? 'selected' : ''; ?>>Kepala Sekolah</option>
                                <option value="Wakil Kepala Sekolah" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Wakil Kepala Sekolah') ? 'selected' : ''; ?>>Wakil Kepala Sekolah</option>
                                <option value="Guru Kelas" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Guru Kelas') ? 'selected' : ''; ?>>Guru Kelas</option>
                                <option value="Guru Mata Pelajaran" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Guru Mata Pelajaran') ? 'selected' : ''; ?>>Guru Mata Pelajaran</option>
                                <option value="Wali Kelas" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Wali Kelas') ? 'selected' : ''; ?>>Wali Kelas</option>
                                <option value="Guru Bimbingan Konseling" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Guru Bimbingan Konseling') ? 'selected' : ''; ?>>Guru Bimbingan Konseling (BK)</option>
                                <option value="Staff Tata Usaha" <?php echo (isset($_POST['jabatan']) && $_POST['jabatan'] === 'Staff Tata Usaha') ? 'selected' : ''; ?>>Staff Tata Usaha / Administrasi</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="pendidikan_terakhir" class="form-label fw-semibold small">Pendidikan Terakhir <span class="text-danger">*</span></label>
                            <select class="form-select" id="pendidikan_terakhir" name="pendidikan_terakhir" required>
                                <option value="" disabled selected>Pilih Pendidikan...</option>
                                <option value="SMA/SMK" <?php echo (isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] === 'SMA/SMK') ? 'selected' : ''; ?>>SMA/SMK</option>
                                <option value="D3" <?php echo (isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] === 'D3') ? 'selected' : ''; ?>>Diploma D3</option>
                                <option value="D4 / S1" <?php echo (isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] === 'D4 / S1') ? 'selected' : ''; ?>>Sarjana S1 / D4</option>
                                <option value="S2" <?php echo (isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] === 'S2') ? 'selected' : ''; ?>>Magister S2</option>
                                <option value="S3" <?php echo (isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] === 'S3') ? 'selected' : ''; ?>>Doktor S3</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="alamat" class="form-label fw-semibold small">Alamat Lengkap <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="4" placeholder="Alamat domisili lengkap saat ini" required><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Meta Column -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Foto Profil</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="profile-img-container mb-3 bg-light d-flex align-items-center justify-content-center border" style="width: 130px; height: 130px;">
                        <i class="bi bi-person-badge text-secondary" style="font-size: 4rem;"></i>
                    </div>
                    <label for="foto" class="form-label fw-semibold small">Pilih Foto</label>
                    <input class="form-control form-control-sm" type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png">
                    <div class="form-text" style="font-size: 11px;">Format JPG/PNG. Maksimal 2MB.</div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Kontak Kepegawaian</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="no_hp" class="form-label fw-semibold small">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="no_hp" name="no_hp" placeholder="Contoh: 0812XXXXXXXX" value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-0">
                        <label for="email" class="form-label fw-semibold small">Alamat Email Resmi</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Contoh: nama.guru@sekolah.sch.id" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                <i class="bi bi-save me-2"></i> Simpan Data Guru
            </button>
        </div>
    </div>
</form>

<?php include $path_prefix . 'includes/footer.php'; ?>
