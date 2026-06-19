<?php
$path_prefix = '../';
$page_title = 'Pengaturan Sekolah';
$active_menu = 'pengaturan';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Only Super Admin can access school settings
checkRole(['super_admin']);

$error = '';
$success = '';

// Fetch active settings
try {
    $stmt = $pdo->query("SELECT * FROM pengaturan WHERE id = 1");
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Fallback initialize if not seeded
        $pdo->query("INSERT INTO pengaturan (id, nama_sekolah, alamat_sekolah, no_telp, email_sekolah, website, nama_kepsek, nip_kepsek, nama_bendahara, nip_bendahara) 
                     VALUES (1, 'SMA NEGERI NUSANTARA', 'Jl. Pendidikan No. 1, Kota Mandiri', '08123456789', 'info@smanusantara.sch.id', 'www.smanusantara.sch.id', 'Drs. H. Mulyadi, M.Pd.', '19700512 199503 1 002', 'Indah Permata, S.E.', '19881024 201212 2 003')");
        $stmt = $pdo->query("SELECT * FROM pengaturan WHERE id = 1");
        $settings = $stmt->fetch();
    }
} catch (PDOException $e) {
    die("Gagal memuat pengaturan database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $nama_sekolah = strtoupper(trim($_POST['nama_sekolah']));
        $alamat_sekolah = trim($_POST['alamat_sekolah']);
        $no_telp = trim($_POST['no_telp']);
        $email_sekolah = trim($_POST['email_sekolah']);
        $website = trim($_POST['website']);
        $nama_kepsek = trim($_POST['nama_kepsek']);
        $nip_kepsek = trim($_POST['nip_kepsek']);
        $nama_bendahara = trim($_POST['nama_bendahara']);
        $nip_bendahara = trim($_POST['nip_bendahara']);
        $nama_bank = trim($_POST['nama_bank']);
        $nama_rekening = trim($_POST['nama_rekening']);
        $nomor_rekening = trim($_POST['nomor_rekening']);
        
        $logo_db_path = $settings['logo']; // fallback to current logo

        if (empty($nama_sekolah) || empty($alamat_sekolah) || empty($no_telp) || empty($email_sekolah) || empty($nama_kepsek) || empty($nip_kepsek) || empty($nama_bendahara) || empty($nip_bendahara) || empty($nama_bank) || empty($nama_rekening) || empty($nomor_rekening)) {
            $error = 'Harap isi seluruh kolom wajib yang ditandai bintang (*).';
        } else {
            // Process logo upload if set
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['logo']['tmp_name'];
                $file_name = $_FILES['logo']['name'];
                $file_size = $_FILES['logo']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_exts = ['jpg', 'jpeg', 'png'];
                
                if (!in_array($file_ext, $allowed_exts)) {
                    $error = 'Format file logo tidak diizinkan. Hanya JPG, JPEG, dan PNG.';
                } elseif ($file_size > 2 * 1024 * 1024) {
                    $error = 'Ukuran berkas logo maksimal 2MB.';
                } else {
                    $upload_dir = '../uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_file_name = 'school_logo_' . time() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        // Delete old logo file if exists
                        if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])) {
                            unlink('../' . $settings['logo']);
                        }
                        $logo_db_path = 'uploads/' . $new_file_name;
                    } else {
                        $error = 'Gagal mengunggah berkas logo sekolah.';
                    }
                }
            }

            if (empty($error)) {
                try {
                    $update_stmt = $pdo->prepare("UPDATE pengaturan SET 
                        nama_sekolah = ?, alamat_sekolah = ?, no_telp = ?, email_sekolah = ?, 
                        website = ?, logo = ?, nama_kepsek = ?, nip_kepsek = ?, 
                        nama_bendahara = ?, nip_bendahara = ?, nama_bank = ?, nama_rekening = ?, nomor_rekening = ? 
                        WHERE id = 1");
                    
                    $update_stmt->execute([
                        $nama_sekolah, $alamat_sekolah, $no_telp, $email_sekolah,
                        $website, $logo_db_path, $nama_kepsek, $nip_kepsek,
                        $nama_bendahara, $nip_bendahara, $nama_bank, $nama_rekening, $nomor_rekening
                    ]);
                    
                    logActivity($pdo, 'Edit Pengaturan', 'Mengubah pengaturan identitas sekolah: ' . $nama_sekolah);
                    
                    $_SESSION['success_message'] = 'Pengaturan identitas sekolah berhasil diperbarui.';
                    header("Location: index.php");
                    exit();
                } catch (PDOException $e) {
                    $error = 'Kesalahan database: ' . $e->getMessage();
                }
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pengaturan Identitas Sekolah</h4>
        <p class="text-muted mb-0 small">Atur informasi kop surat, email, logo, tanda tangan kepala sekolah, dan bendahara sekolah.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="row g-4">
        <!-- Main Form Settings Column -->
        <div class="col-12 col-lg-8">
            <!-- School profile fields card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Profil Utama & Kontak Sekolah</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="nama_sekolah" class="form-label fw-semibold small">Nama Sekolah (Huruf Kapital) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" id="nama_sekolah" name="nama_sekolah" placeholder="Contoh: SMA NEGERI 1 BANDUNG" value="<?php echo htmlspecialchars($settings['nama_sekolah']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="no_telp" class="form-label fw-semibold small">Nomor Telepon Sekolah <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="no_telp" name="no_telp" placeholder="Masukkan nomor telepon" value="<?php echo htmlspecialchars($settings['no_telp']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="website" class="form-label fw-semibold small">Website Sekolah</label>
                            <input type="text" class="form-control" id="website" name="website" placeholder="Contoh: www.sekolah.sch.id" value="<?php echo htmlspecialchars($settings['website']); ?>">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="email_sekolah" class="form-label fw-semibold small">Alamat Email Sekolah <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email_sekolah" name="email_sekolah" placeholder="Contoh: info@sekolah.sch.id" value="<?php echo htmlspecialchars($settings['email_sekolah']); ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="alamat_sekolah" class="form-label fw-semibold small">Alamat Lengkap Sekolah <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="alamat_sekolah" name="alamat_sekolah" rows="3" placeholder="Masukkan alamat lengkap sekolah..." required><?php echo htmlspecialchars($settings['alamat_sekolah']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signatures configuration card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Pejabat Penandatangan Dokumen</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Principal credentials -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3 small"><i class="bi bi-person-check"></i> Kepala Sekolah (Principal)</h6>
                            <div class="mb-3">
                                <label for="nama_kepsek" class="form-label fw-semibold small">Nama Kepala Sekolah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_kepsek" name="nama_kepsek" placeholder="Masukkan nama lengkap beserta gelar" value="<?php echo htmlspecialchars($settings['nama_kepsek']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="nip_kepsek" class="form-label fw-semibold small">NIP Kepala Sekolah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nip_kepsek" name="nip_kepsek" placeholder="Masukkan NIP" value="<?php echo htmlspecialchars($settings['nip_kepsek']); ?>" required>
                            </div>
                        </div>

                        <!-- Treasurer credentials -->
                        <div class="col-md-6 ps-md-4">
                            <h6 class="fw-bold text-success mb-3 small"><i class="bi bi-wallet-fill"></i> Bendahara Sekolah (Treasurer)</h6>
                            <div class="mb-3">
                                <label for="nama_bendahara" class="form-label fw-semibold small">Nama Bendahara <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_bendahara" name="nama_bendahara" placeholder="Masukkan nama lengkap beserta gelar" value="<?php echo htmlspecialchars($settings['nama_bendahara']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="nip_bendahara" class="form-label fw-semibold small">NIP Bendahara <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nip_bendahara" name="nip_bendahara" placeholder="Masukkan NIP" value="<?php echo htmlspecialchars($settings['nip_bendahara']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank account configuration card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Rekening Bank Sekolah (Untuk SPP)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="nama_bank" class="form-label fw-semibold small">Nama Bank <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_bank" name="nama_bank" placeholder="Contoh: Bank Mandiri" value="<?php echo htmlspecialchars($settings['nama_bank'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nama_rekening" class="form-label fw-semibold small">Nama Pemilik Rekening <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_rekening" name="nama_rekening" placeholder="Contoh: SMA NEGERI NUSANTARA" value="<?php echo htmlspecialchars($settings['nama_rekening'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nomor_rekening" class="form-label fw-semibold small">Nomor Rekening <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nomor_rekening" name="nomor_rekening" placeholder="Contoh: 1234567890" value="<?php echo htmlspecialchars($settings['nomor_rekening'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Upload Logo Column -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4 text-center">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0">Logo Sekolah</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="mx-auto bg-light rounded border d-flex align-items-center justify-content-center overflow-hidden mb-3" style="width: 150px; height: 150px;" id="logoPreviewContainer">
                            <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
                                <i class="bi bi-image text-secondary d-none" style="font-size: 60px;" id="placeholderIcon"></i>
                                <img id="logoPreview" src="../<?php echo htmlspecialchars($settings['logo']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-mortarboard text-secondary" style="font-size: 60px;" id="placeholderIcon"></i>
                                <img id="logoPreview" class="d-none" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo" class="form-label fw-semibold small">Ganti Logo Sekolah</label>
                        <input class="form-control form-control-sm" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png" onchange="previewLogo(this)">
                        <div class="form-text" style="font-size: 11px;">Format: JPG, JPEG, PNG. Ukuran maks: 2MB.</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 p-3 bg-light">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-check-circle"></i> Simpan Perubahan</button>
            </div>
        </div>
    </div>
</form>

<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
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
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            preview.src = '../<?php echo htmlspecialchars($settings['logo']); ?>';
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
