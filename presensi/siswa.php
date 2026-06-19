<?php
$path_prefix = '../';
$page_title = 'Presensi Harian Siswa';
$active_menu = 'presensi_siswa';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth checks
checkRole(['super_admin', 'operator', 'guru', 'kepala_sekolah']);

$can_edit = hasPermission(['super_admin', 'operator', 'guru']);

$error = '';
$success = '';

// Get filters
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Fetch all classes for filter dropdown
try {
    $classes = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
} catch (PDOException $e) {
    $classes = [];
    $error = 'Gagal memuat kelas: ' . $e->getMessage();
}

// Fetch students and current attendance if class is selected
$students = [];
$attendance = [];

if ($kelas_id) {
    try {
        // Fetch students
        $stmt = $pdo->prepare("SELECT id, nama, nis, nisn FROM siswa WHERE kelas_id = ? ORDER BY nama ASC");
        $stmt->execute([$kelas_id]);
        $students = $stmt->fetchAll();

        // Fetch attendance for this class on this date
        $stmt_att = $pdo->prepare("
            SELECT ps.siswa_id, ps.status, ps.keterangan 
            FROM presensi_siswa ps
            INNER JOIN siswa s ON ps.siswa_id = s.id
            WHERE s.kelas_id = ? AND ps.tanggal = ?
        ");
        $stmt_att->execute([$kelas_id, $tanggal]);
        $att_rows = $stmt_att->fetchAll();
        foreach ($att_rows as $row) {
            $attendance[$row['siswa_id']] = [
                'status' => $row['status'],
                'keterangan' => $row['keterangan']
            ];
        }
    } catch (PDOException $e) {
        $error = 'Gagal memuat data: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $posted_kelas_id = (int)$_POST['kelas_id'];
        $posted_tanggal = $_POST['tanggal'];
        $status_data = isset($_POST['status']) ? $_POST['status'] : [];
        $keterangan_data = isset($_POST['keterangan']) ? $_POST['keterangan'] : [];

        if (empty($posted_kelas_id) || empty($posted_tanggal)) {
            $error = 'Kelas dan tanggal harus ditentukan.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt_upsert = $pdo->prepare("
                    INSERT INTO presensi_siswa (siswa_id, tanggal, status, keterangan)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)
                ");

                foreach ($status_data as $siswa_id => $status_val) {
                    $ket_val = isset($keterangan_data[$siswa_id]) ? trim($keterangan_data[$siswa_id]) : '';
                    $stmt_upsert->execute([(int)$siswa_id, $posted_tanggal, $status_val, $ket_val]);
                }

                // Get class name for audit log
                $c_stmt = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
                $c_stmt->execute([$posted_kelas_id]);
                $kelas_name = $c_stmt->fetchColumn() ?: 'Unknown';

                logActivity($pdo, 'Input Presensi Siswa', "Mencatat presensi kelas $kelas_name tanggal " . date('d-m-Y', strtotime($posted_tanggal)));
                
                $pdo->commit();
                $_SESSION['success_message'] = "Presensi siswa kelas $kelas_name berhasil disimpan untuk tanggal " . date('d-m-Y', strtotime($posted_tanggal));
                header("Location: siswa.php?kelas_id=$posted_kelas_id&tanggal=$posted_tanggal");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Kesalahan database: ' . $e->getMessage();
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Presensi Harian Siswa</h4>
    <?php if ($kelas_id): ?>
        <a href="siswa_rekap.php?kelas_id=<?php echo $kelas_id; ?>" class="btn btn-outline-primary fw-semibold shadow-sm btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Rekap Presensi Bulan Ini
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Filter Panel -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label for="kelas_id" class="form-label fw-semibold small">Kelas <span class="text-danger">*</span></label>
                <select class="form-select" id="kelas_id" name="kelas_id" onchange="this.form.submit()" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $kelas_id === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-5">
                <label for="tanggal" class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>" onchange="this.form.submit()" required>
            </div>

            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-filter"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Checklist Grid -->
<?php if ($kelas_id): ?>
    <?php if (empty($students)): ?>
        <div class="alert alert-warning border-0 shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> Belum ada data siswa terdaftar di kelas yang dipilih.
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="kelas_id" value="<?php echo $kelas_id; ?>">
            <input type="hidden" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>">

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0">Checklist Kehadiran Siswa</h6>
                        <small class="text-muted">Tanggal: <?php echo date('d-m-Y', strtotime($tanggal)); ?></small>
                    </div>
                    <?php if ($can_edit): ?>
                        <button type="button" class="btn btn-sm btn-outline-success fw-semibold" id="btn-set-all-present">
                            <i class="bi bi-check-all"></i> Set Semua Hadir
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th style="width: 150px;">NIS/NISN</th>
                                    <th>Nama Siswa</th>
                                    <th style="width: 320px;" class="text-center">Status Kehadiran</th>
                                    <th>Keterangan / Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $s): ?>
                                    <?php 
                                    $curr_att = isset($attendance[$s['id']]) ? $attendance[$s['id']]['status'] : 'Hadir';
                                    $curr_ket = isset($attendance[$s['id']]) ? $attendance[$s['id']]['keterangan'] : '';
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted small"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="fw-semibold small text-secondary-emphasis"><?php echo htmlspecialchars($s['nis']); ?></div>
                                            <div class="text-muted" style="font-size: 11px;">NISN: <?php echo htmlspecialchars($s['nisn']); ?></div>
                                        </td>
                                        <td class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($s['nama']); ?></td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <!-- Hadir -->
                                                <input type="radio" class="btn-check btn-status-hadir" 
                                                       name="status[<?php echo $s['id']; ?>]" 
                                                       id="status_h_<?php echo $s['id']; ?>" 
                                                       value="Hadir" 
                                                       <?php echo $curr_att === 'Hadir' ? 'checked' : ''; ?>
                                                       <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <label class="btn btn-sm btn-outline-success px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_h_<?php echo $s['id']; ?>">
                                                    Hadir
                                                </label>

                                                <!-- Sakit -->
                                                <input type="radio" class="btn-check btn-status-sakit" 
                                                       name="status[<?php echo $s['id']; ?>]" 
                                                       id="status_s_<?php echo $s['id']; ?>" 
                                                       value="Sakit" 
                                                       <?php echo $curr_att === 'Sakit' ? 'checked' : ''; ?>
                                                       <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <label class="btn btn-sm btn-outline-primary px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_s_<?php echo $s['id']; ?>">
                                                    Sakit
                                                </label>

                                                <!-- Izin -->
                                                <input type="radio" class="btn-check btn-status-izin" 
                                                       name="status[<?php echo $s['id']; ?>]" 
                                                       id="status_i_<?php echo $s['id']; ?>" 
                                                       value="Izin" 
                                                       <?php echo $curr_att === 'Izin' ? 'checked' : ''; ?>
                                                       <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <label class="btn btn-sm btn-outline-warning px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_i_<?php echo $s['id']; ?>">
                                                    Izin
                                                </label>

                                                <!-- Alpa -->
                                                <input type="radio" class="btn-check btn-status-alpa" 
                                                       name="status[<?php echo $s['id']; ?>]" 
                                                       id="status_a_<?php echo $s['id']; ?>" 
                                                       value="Alpa" 
                                                       <?php echo $curr_att === 'Alpa' ? 'checked' : ''; ?>
                                                       <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                <label class="btn btn-sm btn-outline-danger px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_a_<?php echo $s['id']; ?>">
                                                    Alpa
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" 
                                                   name="keterangan[<?php echo $s['id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($curr_ket); ?>" 
                                                   placeholder="Keterangan (opsional)..."
                                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($can_edit): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Presensi</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary text-center small shadow-sm mb-0">
                    <i class="bi bi-lock-fill me-1"></i> Anda dalam Mode Lihat-Saja. Perubahan tidak dapat disimpan.
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-5 text-center text-muted">
            <i class="bi bi-calendar2-week fs-1 text-primary-emphasis mb-3 d-block"></i>
            <h5 class="fw-bold text-dark-emphasis mb-1">Silakan Pilih Kelas & Tanggal</h5>
            <p class="small text-muted mb-0">Gunakan filter di atas untuk menampilkan lembar kehadiran harian siswa.</p>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const btnSetAllPresent = document.getElementById("btn-set-all-present");
    if (btnSetAllPresent) {
        btnSetAllPresent.addEventListener("click", function() {
            const radioButtons = document.querySelectorAll(".btn-status-hadir");
            radioButtons.forEach(radio => {
                if (!radio.disabled) {
                    radio.checked = true;
                }
            });
        });
    }
});
</script>

<?php include $path_prefix . 'includes/footer.php'; ?>
