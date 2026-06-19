<?php
$path_prefix = '../';
$page_title = 'Presensi Harian Pegawai';
$active_menu = 'presensi_pegawai';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth checks - Guru cannot record employee attendance, they can only view their own in their profile details
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$can_edit = hasPermission(['super_admin', 'operator']);

$error = '';
$success = '';

// Get filter date
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Fetch all employees (Union of Guru and Karyawan)
$employees = [];
$attendance = [];

try {
    // UNION query to retrieve both guru and karyawan in one sweep
    $stmt = $pdo->query("
        SELECT 'guru' AS tipe, id, nip AS identifier, nama, jabatan, 'Guru/Pendidik' AS tipe_label 
        FROM guru
        UNION
        SELECT 'karyawan' AS tipe, id, nik AS identifier, nama, jabatan, 'Staf/Karyawan' AS tipe_label 
        FROM karyawan
        ORDER BY nama ASC
    ");
    $employees = $stmt->fetchAll();

    // Fetch existing attendance for this date
    $stmt_att = $pdo->prepare("SELECT tipe_penerima, penerima_id, status, keterangan FROM presensi_pegawai WHERE tanggal = ?");
    $stmt_att->execute([$tanggal]);
    $att_rows = $stmt_att->fetchAll();
    foreach ($att_rows as $row) {
        $key = $row['tipe_penerima'] . '_' . $row['penerima_id'];
        $attendance[$key] = [
            'status' => $row['status'],
            'keterangan' => $row['keterangan']
        ];
    }
} catch (PDOException $e) {
    $error = 'Kesalahan database: ' . $e->getMessage();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $posted_tanggal = $_POST['tanggal'];
        $status_data = isset($_POST['status']) ? $_POST['status'] : [];
        $keterangan_data = isset($_POST['keterangan']) ? $_POST['keterangan'] : [];

        if (empty($posted_tanggal)) {
            $error = 'Tanggal harus ditentukan.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt_upsert = $pdo->prepare("
                    INSERT INTO presensi_pegawai (tipe_penerima, penerima_id, tanggal, status, keterangan)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)
                ");

                foreach ($status_data as $key => $status_val) {
                    // Key format is: {tipe}_{id}
                    $parts = explode('_', $key);
                    if (count($parts) === 2) {
                        $tipe = $parts[0];
                        $id = (int)$parts[1];
                        $ket_val = isset($keterangan_data[$key]) ? trim($keterangan_data[$key]) : '';
                        $stmt_upsert->execute([$tipe, $id, $posted_tanggal, $status_val, $ket_val]);
                    }
                }

                logActivity($pdo, 'Input Presensi Pegawai', "Mencatat presensi pegawai (guru & karyawan) tanggal " . date('d-m-Y', strtotime($posted_tanggal)));
                
                $pdo->commit();
                $_SESSION['success_message'] = "Presensi pegawai berhasil disimpan untuk tanggal " . date('d-m-Y', strtotime($posted_tanggal));
                header("Location: pegawai.php?tanggal=$posted_tanggal");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal menyimpan data presensi: ' . $e->getMessage();
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Presensi Harian Pegawai</h4>
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
            <div class="col-12 col-md-9">
                <label for="tanggal" class="form-label fw-semibold small">Pilih Tanggal Presensi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>" onchange="this.form.submit()" required>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-calendar-check"></i> Load Presensi</button>
            </div>
        </form>
    </div>
</div>

<!-- Checklist Grid -->
<?php if (!empty($employees)): ?>
    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>">

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0">Daftar Kehadiran Guru & Karyawan</h6>
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
                                <th style="width: 180px;">NIP / NIK</th>
                                <th>Nama Lengkap</th>
                                <th style="width: 140px;">Kategori</th>
                                <th style="width: 180px;">Jabatan</th>
                                <th style="width: 320px;" class="text-center">Status Kehadiran</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $index => $e): ?>
                                <?php 
                                $key = $e['tipe'] . '_' . $e['id'];
                                $curr_att = isset($attendance[$key]) ? $attendance[$key]['status'] : 'Hadir';
                                $curr_ket = isset($attendance[$key]) ? $attendance[$key]['keterangan'] : '';
                                
                                $cat_badge = ($e['tipe'] === 'guru') ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info-emphasis';
                                ?>
                                <tr>
                                    <td class="text-center text-muted small"><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="fw-semibold small text-secondary-emphasis">
                                            <?php echo htmlspecialchars($e['identifier'] ?: '-'); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($e['nama']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $cat_badge; ?>" style="font-size: 11px;">
                                            <?php echo $e['tipe_label']; ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($e['jabatan']); ?></td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <!-- Hadir -->
                                            <input type="radio" class="btn-check btn-status-hadir" 
                                                   name="status[<?php echo $key; ?>]" 
                                                   id="status_h_<?php echo $key; ?>" 
                                                   value="Hadir" 
                                                   <?php echo $curr_att === 'Hadir' ? 'checked' : ''; ?>
                                                   <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                            <label class="btn btn-sm btn-outline-success px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_h_<?php echo $key; ?>">
                                                Hadir
                                            </label>

                                            <!-- Sakit -->
                                            <input type="radio" class="btn-check btn-status-sakit" 
                                                   name="status[<?php echo $key; ?>]" 
                                                   id="status_s_<?php echo $key; ?>" 
                                                   value="Sakit" 
                                                   <?php echo $curr_att === 'Sakit' ? 'checked' : ''; ?>
                                                   <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                            <label class="btn btn-sm btn-outline-primary px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_s_<?php echo $key; ?>">
                                                Sakit
                                            </label>

                                            <!-- Izin -->
                                            <input type="radio" class="btn-check btn-status-izin" 
                                                   name="status[<?php echo $key; ?>]" 
                                                   id="status_i_<?php echo $key; ?>" 
                                                   value="Izin" 
                                                   <?php echo $curr_att === 'Izin' ? 'checked' : ''; ?>
                                                   <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                            <label class="btn btn-sm btn-outline-warning px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_i_<?php echo $key; ?>">
                                                Izin
                                            </label>

                                            <!-- Alpa -->
                                            <input type="radio" class="btn-check btn-status-alpa" 
                                                   name="status[<?php echo $key; ?>]" 
                                                   id="status_a_<?php echo $key; ?>" 
                                                   value="Alpa" 
                                                   <?php echo $curr_att === 'Alpa' ? 'checked' : ''; ?>
                                                   <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                            <label class="btn btn-sm btn-outline-danger px-2 py-1" style="font-size: 11px; min-width: 55px;" for="status_a_<?php echo $key; ?>">
                                                Alpa
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="keterangan[<?php echo $key; ?>]" 
                                               value="<?php echo htmlspecialchars($curr_ket); ?>" 
                                               placeholder="Catatan..."
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
<?php else: ?>
    <div class="alert alert-warning border-0 shadow-sm" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> Belum ada data guru atau karyawan terdaftar.
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
