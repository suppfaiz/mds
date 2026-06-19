<?php
$path_prefix = '../';
$page_title = 'Pengaturan PMB';
$active_menu = 'pmb_setting';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Only Super Admin and Operator can write to PMB settings
checkRole(['super_admin', 'operator']);

$role = $_SESSION['role'];
$error = '';
$success = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch active settings
try {
    $stmt = $pdo->query("SELECT * FROM pengaturan WHERE id = 1");
    $settings = $stmt->fetch();
    
    if (!$settings) {
        $error = 'Pengaturan sekolah tidak ditemukan.';
    }
} catch (PDOException $e) {
    $error = 'Gagal memuat pengaturan: ' . $e->getMessage();
}

// Fetch stats for PMB
$registered_count = 0;
$accepted_count = 0;
$pending_count = 0;
try {
    $registered_count = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar")->fetchColumn();
    $accepted_count = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar WHERE status = 'Diterima'")->fetchColumn();
    $pending_count = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar WHERE status = 'Pending'")->fetchColumn();
} catch (PDOException $e) {
    // Fail silently
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $pmb_status = trim($_POST['pmb_status']);
        $pmb_mulai = !empty($_POST['pmb_mulai']) ? trim($_POST['pmb_mulai']) : null;
        $pmb_selesai = !empty($_POST['pmb_selesai']) ? trim($_POST['pmb_selesai']) : null;
        $pmb_kuota = isset($_POST['pmb_kuota']) ? (int)$_POST['pmb_kuota'] : 100;
        
        if (!in_array($pmb_status, ['Buka', 'Tutup'])) {
            $error = 'Status PMB tidak valid.';
        } elseif ($pmb_kuota < 0) {
            $error = 'Kuota pendaftaran tidak boleh negatif.';
        } elseif (!empty($pmb_mulai) && !empty($pmb_selesai) && strtotime($pmb_mulai) > strtotime($pmb_selesai)) {
            $error = 'Tanggal mulai tidak boleh melebihi tanggal selesai.';
        } else {
            try {
                $update_stmt = $pdo->prepare("UPDATE pengaturan SET 
                    pmb_status = ?, pmb_mulai = ?, pmb_selesai = ?, pmb_kuota = ? 
                    WHERE id = 1");
                
                $update_stmt->execute([$pmb_status, $pmb_mulai, $pmb_selesai, $pmb_kuota]);
                
                logActivity($pdo, 'Ubah Pengaturan PMB', "Mengubah status: $pmb_status, Jadwal: " . ($pmb_mulai ?? '-') . " s/d " . ($pmb_selesai ?? '-') . ", Kuota: $pmb_kuota");
                
                $_SESSION['success_message'] = 'Pengaturan Penerimaan Murid Baru (PMB) berhasil diperbarui.';
                header("Location: pengaturan.php");
                exit();
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan ke database: ' . $e->getMessage();
            }
        }
    }
}

// Check schedule dates vs current date
$today = date('Y-m-d');
$schedule_status = 'Nonaktif';
$schedule_class = 'bg-secondary';

if ($settings['pmb_status'] === 'Buka') {
    if (empty($settings['pmb_mulai']) && empty($settings['pmb_selesai'])) {
        $schedule_status = 'Aktif (Tanpa Batas Jadwal)';
        $schedule_class = 'bg-success';
    } else {
        $start_ts = !empty($settings['pmb_mulai']) ? strtotime($settings['pmb_mulai']) : null;
        $end_ts = !empty($settings['pmb_selesai']) ? strtotime($settings['pmb_selesai']) : null;
        $today_ts = strtotime($today);
        
        if ($start_ts && $today_ts < $start_ts) {
            $schedule_status = 'Menunggu Jadwal Mulai';
            $schedule_class = 'bg-warning text-dark';
        } elseif ($end_ts && $today_ts > $end_ts) {
            $schedule_status = 'Jadwal Telah Berakhir';
            $schedule_class = 'bg-danger';
        } else {
            $schedule_status = 'Aktif';
            $schedule_class = 'bg-success';
        }
    }
}

// Calculate percentages for quota
$quota_limit = $settings['pmb_kuota'] ?? 100;
$quota_percent = $quota_limit > 0 ? round(($registered_count / $quota_limit) * 100) : 0;
$progress_class = 'bg-primary';
if ($quota_percent >= 90) {
    $progress_class = 'bg-danger';
} elseif ($quota_percent >= 75) {
    $progress_class = 'bg-warning';
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pengaturan Penerimaan Murid Baru (PMB)</h4>
        <p class="text-muted mb-0 small">Atur masa pendaftaran online, jadwal batas mulai-selesai, serta kapasitas kuota calon siswa.</p>
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
    <!-- Configuration Form Column -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0 text-primary-emphasis"><i class="bi bi-sliders me-1"></i> Form Pengaturan PMB</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
                    <?php echo csrf_field(); ?>
                    <!-- Status Toggle -->
                    <div class="mb-4">
                        <label for="pmb_status" class="form-label fw-semibold small">Status Portal PMB</label>
                        <select class="form-select" id="pmb_status" name="pmb_status" required>
                            <option value="Tutup" <?php echo $settings['pmb_status'] === 'Tutup' ? 'selected' : ''; ?>>Tutup (Formulir Pendaftaran Nonaktif/Hidden)</option>
                            <option value="Buka" <?php echo $settings['pmb_status'] === 'Buka' ? 'selected' : ''; ?>>Buka (Formulir Pendaftaran Aktif/Terbuka)</option>
                        </select>
                        <div class="form-text small" style="font-size: 11px;">Jika di-set 'Tutup', pendaftaran tidak akan menerima entri baru secara publik.</div>
                    </div>

                    <!-- Date Schedule Config -->
                    <div class="row g-3 mb-4">
                        <div class="col-12"><span class="fw-bold text-dark-emphasis small d-block mb-1"><i class="bi bi-calendar-range"></i> Jadwal Pendaftaran</span></div>
                        <div class="col-md-6">
                            <label for="pmb_mulai" class="form-label small">Tanggal Mulai Pendaftaran</label>
                            <input type="date" class="form-control" id="pmb_mulai" name="pmb_mulai" value="<?php echo htmlspecialchars($settings['pmb_mulai'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="pmb_selesai" class="form-label small">Tanggal Selesai Pendaftaran</label>
                            <input type="date" class="form-control" id="pmb_selesai" name="pmb_selesai" value="<?php echo htmlspecialchars($settings['pmb_selesai'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-text small mt-0" style="font-size: 11px;">Kosongkan kedua tanggal jika ingin pendaftaran berlangsung tanpa batas rentang waktu tertentu.</div>
                        </div>
                    </div>

                    <!-- Quota Limit Config -->
                    <div class="mb-4">
                        <label for="pmb_kuota" class="form-label fw-semibold small">Kuota Maksimal Pendaftar (Calon Siswa)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="pmb_kuota" name="pmb_kuota" min="1" value="<?php echo htmlspecialchars($settings['pmb_kuota'] ?? 100); ?>" required>
                            <span class="input-group-text bg-light">Calon Murid</span>
                        </div>
                        <div class="form-text small" style="font-size: 11px;">Formulir publik otomatis menutup dan menunjukkan status penuh apabila jumlah pendaftar melebihi batas kuota ini.</div>
                    </div>

                    <hr class="my-4">
                    
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Widgets Column -->
    <div class="col-12 col-lg-5">
        <!-- Live Status Display -->
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body p-4">
                <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Status Pendaftaran Saat Ini</span>
                
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?php if ($settings['pmb_status'] === 'Buka'): ?>
                        <span class="badge bg-success py-1.5 px-3 rounded-pill fw-bold" style="font-size: 11.5px;"><i class="bi bi-unlock-fill"></i> TERBUKA</span>
                    <?php else: ?>
                        <span class="badge bg-danger py-1.5 px-3 rounded-pill fw-bold" style="font-size: 11.5px;"><i class="bi bi-lock-fill"></i> TERTUTUP</span>
                    <?php endif; ?>
                    
                    <span class="badge <?php echo $schedule_class; ?> py-1.5 px-3 rounded-pill fw-bold" style="font-size: 11.5px;"><?php echo $schedule_status; ?></span>
                </div>

                <div class="border-top pt-3 mt-3">
                    <span class="text-muted small d-block mb-2">Batas Tanggal Pendaftaran:</span>
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <small class="text-muted d-block" style="font-size: 10px;">Tanggal Mulai</small>
                            <span class="fw-bold text-dark-emphasis small"><?php echo !empty($settings['pmb_mulai']) ? date('d-m-Y', strtotime($settings['pmb_mulai'])) : 'Tidak Dibatasi'; ?></span>
                        </div>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <div>
                            <small class="text-muted d-block" style="font-size: 10px;">Tanggal Selesai</small>
                            <span class="fw-bold text-dark-emphasis small"><?php echo !empty($settings['pmb_selesai']) ? date('d-m-Y', strtotime($settings['pmb_selesai'])) : 'Tidak Dibatasi'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quota Progress Indicator -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Pemakaian Kapasitas Kuota</span>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h3 class="fw-bold m-0 text-primary-emphasis"><?php echo $registered_count; ?></h3>
                    <span class="text-muted">/ <?php echo $quota_limit; ?> Pendaftar Terdaftar</span>
                </div>

                <!-- Progress Bar -->
                <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                    <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" style="width: <?php echo min($quota_percent, 100); ?>%;" aria-valuenow="<?php echo $registered_count; ?>" aria-valuemin="0" aria-valuemax="<?php echo $quota_limit; ?>"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center small text-muted mb-3">
                    <span>Terisi: <?php echo $quota_percent; ?>%</span>
                    <span>Sisa Kuota: <?php echo max(0, $quota_limit - $registered_count); ?></span>
                </div>

                <div class="bg-light p-3 rounded-3 border">
                    <div class="row g-2 text-center small">
                        <div class="col-6 border-end">
                            <span class="text-muted d-block" style="font-size: 10px;">Menunggu Verifikasi</span>
                            <span class="fw-bold text-warning-emphasis fs-6"><?php echo $pending_count; ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 10px;">Lulus Seleksi (Diterima)</span>
                            <span class="fw-bold text-success fs-6"><?php echo $accepted_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Public Links Quick access -->
        <div class="card shadow-sm border-0 bg-primary bg-opacity-10 border-start border-primary border-3">
            <div class="card-body p-3">
                <h6 class="fw-bold text-primary mb-1 small"><i class="bi bi-link-45deg"></i> Pintasan Akses Publik</h6>
                <p class="text-muted mb-2" style="font-size: 11.5px;">Gunakan tautan berikut untuk pendaftaran mandiri oleh calon murid.</p>
                <div class="d-flex gap-2">
                    <a href="daftar.php" target="_blank" class="btn btn-primary btn-xs py-1 px-3 fw-semibold small" style="font-size: 11px;"><i class="bi bi-plus-circle"></i> Form Registrasi</a>
                    <a href="status.php" target="_blank" class="btn btn-outline-primary btn-xs py-1 px-3 fw-semibold small" style="font-size: 11px;"><i class="bi bi-search"></i> Cek Status</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
