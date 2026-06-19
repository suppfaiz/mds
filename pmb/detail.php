<?php
$path_prefix = '../';
$page_title = 'Detail Pendaftar PMB';
$active_menu = 'pmb';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth Check: Block Guru
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$role = $_SESSION['role'];
$error = '';
$success = '';

// Retrieve alerts from session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['error_message'] = 'ID pendaftar tidak valid.';
    header("Location: index.php");
    exit();
}

// Fetch applicant details
$pendaftar = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE id = ?");
    $stmt->execute([$id]);
    $pendaftar = $stmt->fetch();
    
    if (!$pendaftar) {
        $_SESSION['error_message'] = 'Calon pendaftar tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Fetch already linked student profile details if exists
$linked_student = null;
if (!empty($pendaftar['siswa_id'])) {
    try {
        $stmt_stud = $pdo->prepare("SELECT id, nama, nis, kelas_id FROM siswa WHERE id = ?");
        $stmt_stud->execute([$pendaftar['siswa_id']]);
        $linked_student = $stmt_stud->fetch();
    } catch (PDOException $e) {
        // Fail silently
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        // Handle Status Update form
        if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
            checkRole(['super_admin', 'operator']); // Guru & Principal cannot write
            $new_status = trim($_POST['status']);
            $catatan_panitia = trim($_POST['catatan_panitia']);
            
            if (!in_array($new_status, ['Pending', 'Diterima', 'Ditolak'])) {
                $error = 'Pilihan status tidak valid.';
            } else {
                try {
                    $stmt_update = $pdo->prepare("UPDATE pmb_pendaftar SET status = ?, catatan_panitia = ? WHERE id = ?");
                    $stmt_update->execute([$new_status, $catatan_panitia, $id]);
                    
                    logActivity($pdo, 'Ubah Status PMB', "Mengubah status pendaftaran " . $pendaftar['nama'] . " menjadi $new_status dan memperbarui catatan");
                    $_SESSION['success_message'] = 'Status dan catatan pendaftaran berhasil diperbarui.';
                    header("Location: detail.php?id=" . $id);
                    exit();
                } catch (PDOException $e) {
                    $error = 'Gagal memperbarui status: ' . $e->getMessage();
                }
            }
        }

        // Handle Student Conversion form
        if (isset($_POST['action']) && $_POST['action'] === 'konversi') {
            checkRole(['super_admin', 'operator']);
            
            $kelas_id = (int)$_POST['kelas_id'];
            $nis = trim($_POST['nis']);
            $nisn = trim($_POST['nisn']);
            $agama = trim($_POST['agama']);
            $tahun_masuk = trim($_POST['tahun_masuk']);
            
            if (empty($kelas_id) || empty($nis) || empty($nisn) || empty($agama) || empty($tahun_masuk)) {
                $error = 'Semua data konversi wajib diisi.';
            } else {
                // Resolve parent email if account is linked
                $parent_email = '';
                if (!empty($pendaftar['pmb_akun_id'])) {
                    try {
                        $stmt_email = $pdo->prepare("SELECT email FROM pmb_akun WHERE id = ?");
                        $stmt_email->execute([$pendaftar['pmb_akun_id']]);
                        $parent_email = $stmt_email->fetchColumn() ?: '';
                    } catch (PDOException $e) {
                        // Ignore
                    }
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Check uniqueness of NIS/NISN
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ? OR nisn = ?");
                    $stmt_check->execute([$nis, $nisn]);
                    if ($stmt_check->fetchColumn() > 0) {
                        $error = 'NIS atau NISN sudah terdaftar di sistem master siswa.';
                        $pdo->rollBack();
                    } else {
                        // Insert into main siswa table
                        $stmt_insert = $pdo->prepare("
                            INSERT INTO siswa (nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, agama, kelas_id, tahun_masuk, no_hp, email, nama_ayah, nama_ibu, no_hp_ortu, alamat_ortu)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt_insert->execute([
                            $nis, $nisn, $pendaftar['nama'], $pendaftar['jenis_kelamin'], 
                            $pendaftar['tempat_lahir'], $pendaftar['tanggal_lahir'], 
                            $pendaftar['alamat'], $agama, $kelas_id, $tahun_masuk, 
                            $pendaftar['no_hp'], $parent_email, $pendaftar['nama_ortu'], '-', $pendaftar['no_hp'], $pendaftar['alamat']
                        ]);
                        
                        $siswa_id = $pdo->lastInsertId();
                        
                        // If candidate has secure uploaded file, register it in the student locker
                        if (!empty($pendaftar['dokumen_bukti'])) {
                            $stmt_doc = $pdo->prepare("
                                INSERT INTO dokumen (tipe_data, data_id, kategori, nama_file, lokasi_file)
                                VALUES ('siswa', ?, 'Akta Kelahiran', ?, ?)
                            ");
                            $stmt_doc->execute([
                                $siswa_id, 
                                basename($pendaftar['dokumen_bukti']), 
                                $pendaftar['dokumen_bukti']
                            ]);
                        }
                        
                        // Update PMB record with linked siswa_id and set status to Diterima
                        $stmt_pmb = $pdo->prepare("UPDATE pmb_pendaftar SET siswa_id = ?, status = 'Diterima' WHERE id = ?");
                        $stmt_pmb->execute([$siswa_id, $id]);
                        
                        $pdo->commit();
                        
                        logActivity($pdo, 'Konversi PMB', "Mengonversi pendaftar PMB " . $pendaftar['nama'] . " menjadi siswa aktif (NIS: $nis)");
                        $_SESSION['success_message'] = 'Calon siswa berhasil dikonversi menjadi siswa aktif.';
                        header("Location: detail.php?id=" . $id);
                        exit();
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Gagal melakukan konversi: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all classes for the conversion select dropdown
$classes = [];
try {
    $classes = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
} catch (PDOException $e) {
    // Fail silently
}

// Calculate proposed NIS
$proposed_nis = '';
try {
    $stmt_max = $pdo->query("SELECT MAX(CAST(nis AS UNSIGNED)) FROM siswa WHERE nis REGEXP '^[0-9]+$'");
    $max_nis = $stmt_max->fetchColumn();
    $proposed_nis = $max_nis ? $max_nis + 1 : date('y') . '001';
} catch (PDOException $e) {
    $proposed_nis = date('y') . '001';
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Detail Berkas Pendaftar</h4>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Biodata Sheet -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1 text-primary-emphasis"><?php echo htmlspecialchars($pendaftar['nama']); ?></h5>
                        <span class="text-muted font-monospace small">Registrasi: <?php echo htmlspecialchars($pendaftar['no_pendaftaran']); ?></span>
                    </div>
                    <div>
                        <?php if ($pendaftar['status'] === 'Diterima'): ?>
                            <span class="badge bg-success-subtle text-success py-2 px-3 shadow-sm rounded-pill">Diterima</span>
                        <?php elseif ($pendaftar['status'] === 'Ditolak'): ?>
                            <span class="badge bg-danger-subtle text-danger py-2 px-3 shadow-sm rounded-pill">Ditolak</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis py-2 px-3 shadow-sm rounded-pill">Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4">
                <h6 class="fw-bold border-bottom pb-2 mb-3 text-secondary-emphasis small text-uppercase" style="letter-spacing: 0.5px;">Identitas Calon Siswa</h6>
                
                <table class="table table-sm table-borderless align-middle mb-4" style="font-size: 13.5px;">
                    <tr>
                        <td class="text-muted py-2" style="width: 150px;">Nama Lengkap</td>
                        <td style="width: 10px;">:</td>
                        <td class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($pendaftar['nama']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">Jenis Kelamin</td>
                        <td>:</td>
                        <td><?php echo $pendaftar['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">Tempat, Tanggal Lahir</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($pendaftar['tempat_lahir'] . ', ' . date('d F Y', strtotime($pendaftar['tanggal_lahir']))); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">Asal Sekolah (SMP)</td>
                        <td>:</td>
                        <td class="fw-semibold text-secondary-emphasis"><?php echo htmlspecialchars($pendaftar['asal_sekolah']); ?></td>
                    </tr>
                </table>

                <h6 class="fw-bold border-bottom pb-2 mb-3 text-secondary-emphasis small text-uppercase" style="letter-spacing: 0.5px;">Kontak & Orang Tua</h6>
                
                <table class="table table-sm table-borderless align-middle mb-4" style="font-size: 13.5px;">
                    <tr>
                        <td class="text-muted py-2" style="width: 150px;">Nama Orang Tua / Wali</td>
                        <td style="width: 10px;">:</td>
                        <td><?php echo htmlspecialchars($pendaftar['nama_ortu']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">No. HP / WhatsApp</td>
                        <td>:</td>
                        <td class="font-monospace text-primary fw-medium"><?php echo htmlspecialchars($pendaftar['no_hp']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">Alamat Domisili</td>
                        <td>:</td>
                        <td class="text-wrap"><?php echo htmlspecialchars($pendaftar['alamat']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-2">Waktu Registrasi</td>
                        <td>:</td>
                        <td class="text-muted"><?php echo date('d F Y H:i:s', strtotime($pendaftar['created_at'])); ?></td>
                    </tr>
                </table>

                <h6 class="fw-bold border-bottom pb-2 mb-3 text-secondary-emphasis small text-uppercase" style="letter-spacing: 0.5px;">Dokumen Lampiran</h6>
                <?php if (!empty($pendaftar['dokumen_bukti'])): ?>
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 border border-light-subtle">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-check-fill text-primary fs-3"></i>
                            <div>
                                <span class="d-block fw-semibold small text-dark-emphasis"><?php echo basename($pendaftar['dokumen_bukti']); ?></span>
                                <small class="text-muted" style="font-size: 11px;">Unggahan berkas pendaftaran calon siswa</small>
                            </div>
                        </div>
                        <a href="view_doc.php?id=<?php echo $pendaftar['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold"><i class="bi bi-eye"></i> Lihat Berkas</a>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-light rounded-3 text-center text-muted small border">
                        <i class="bi bi-file-earmark-x d-block mb-1 fs-5"></i> Tidak ada dokumen bukti terunggah.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Conversion Controls -->
    <div class="col-12 col-lg-5">
        <!-- 1. Status Evaluation Widget -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0 text-dark-emphasis"><i class="bi bi-sliders text-primary me-1"></i> Evaluasi Berkas PMB</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-3">
                        <label for="status" class="form-label small">Tentukan Status Seleksi</label>
                        <select class="form-select" id="status" name="status" <?php echo ($role !== 'super_admin' && $role !== 'operator') ? 'disabled' : ''; ?> required>
                            <option value="Pending" <?php echo $pendaftar['status'] === 'Pending' ? 'selected' : ''; ?>>Pending (Menunggu Verifikasi)</option>
                            <option value="Diterima" <?php echo $pendaftar['status'] === 'Diterima' ? 'selected' : ''; ?>>Diterima (Lulus Seleksi)</option>
                            <option value="Ditolak" <?php echo $pendaftar['status'] === 'Ditolak' ? 'selected' : ''; ?>>Ditolak (Gugur)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="catatan_panitia" class="form-label small">Catatan / Umpan Balik Panitia</label>
                        <textarea class="form-control" id="catatan_panitia" name="catatan_panitia" rows="3" placeholder="Masukkan catatan, instruksi daftar ulang, atau alasan penolakan berkas..." <?php echo ($role !== 'super_admin' && $role !== 'operator') ? 'disabled' : ''; ?>><?php echo htmlspecialchars($pendaftar['catatan_panitia'] ?? ''); ?></textarea>
                    </div>
                    <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                        <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold"><i class="bi bi-save"></i> Perbarui Status & Catatan</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- 2. Master Conversion Widget -->
        <?php if (!empty($pendaftar['siswa_id']) && $linked_student): ?>
            <!-- Already converted state -->
            <div class="card shadow-sm border-0 border-start border-success border-4 bg-success bg-opacity-5">
                <div class="card-body py-4 text-center">
                    <i class="bi bi-check-circle-fill text-success display-6 mb-3 d-block"></i>
                    <h6 class="fw-bold text-success mb-1">Sudah Terdaftar di Master Data</h6>
                    <p class="small text-muted mb-4">Calon murid ini sudah sukses dikonversi menjadi siswa aktif sekolah.</p>
                    <a href="../siswa/detail.php?id=<?php echo $linked_student['id']; ?>" class="btn btn-sm btn-success fw-bold px-4 shadow-sm">
                        <i class="bi bi-person-circle me-1"></i> Buka Profil Siswa
                    </a>
                </div>
            </div>
        <?php elseif ($pendaftar['status'] === 'Diterima'): ?>
            <!-- Convert now state -->
            <div class="card shadow-sm border-0 border-start border-primary border-4 bg-light bg-opacity-25">
                <div class="card-header bg-transparent border-0 pt-3 px-3">
                    <h6 class="fw-bold mb-0 text-primary-emphasis"><i class="bi bi-person-plus-fill me-1 text-primary"></i> Konversi Calon Siswa Baru</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3 small" style="font-size: 11.5px; line-height: 1.4;">
                        <i class="bi bi-info-circle-fill me-1 text-primary"></i> <strong>Pemberitahuan</strong>: Lulusan PMB yang diterima dapat diubah menjadi siswa aktif master. Dokumen terlampir otomatis dipindahkan ke berkas siswa.
                    </div>
                    
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="konversi">
                        
                        <!-- Target Class -->
                        <div class="mb-2">
                            <label for="kelas_id" class="form-label small">Tentukan Kelas / Rombel</label>
                            <select class="form-select form-select-sm" id="kelas_id" name="kelas_id" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nama_kelas']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Auto proposed NIS -->
                        <div class="mb-2">
                            <label for="nis" class="form-label small">NIS Master</label>
                            <input type="text" class="form-control form-control-sm font-monospace fw-bold" id="nis" name="nis" value="<?php echo htmlspecialchars($proposed_nis); ?>" required>
                        </div>

                        <!-- Proposed NISN -->
                        <div class="mb-2">
                            <label for="nisn" class="form-label small">NISN Master</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="nisn" name="nisn" placeholder="Contoh: 0102938495" required>
                        </div>

                        <!-- Religion -->
                        <div class="mb-2">
                            <label for="agama" class="form-label small">Agama</label>
                            <select class="form-select form-select-sm" id="agama" name="agama" required>
                                <option value="Islam" selected>Islam</option>
                                <option value="Kristen">Kristen</option>
                                <option value="Katolik">Katolik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Budha">Budha</option>
                                <option value="Khonghucu">Khonghucu</option>
                            </select>
                        </div>

                        <!-- Tahun Masuk -->
                        <div class="mb-3">
                            <label for="tahun_masuk" class="form-label small">Tahun Masuk</label>
                            <input type="text" class="form-control form-control-sm font-monospace text-center" id="tahun_masuk" name="tahun_masuk" value="<?php echo date('Y'); ?>" required>
                        </div>
                        
                        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold shadow-sm" onclick="return confirm('Apakah Anda yakin ingin memindahkan berkas calon murid ini ke database siswa master aktif?')"><i class="bi bi-person-plus"></i> Konversi Ke Siswa Aktif</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Normal reminder state -->
            <div class="card shadow-sm border-0 bg-light p-3 text-center text-muted small border">
                <i class="bi bi-info-circle d-block mb-1 fs-5"></i>
                Fitur konversi ke siswa master hanya muncul apabila status seleksi calon pendaftar sudah diubah ke <strong>Diterima</strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
