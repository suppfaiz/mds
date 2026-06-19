<?php
$path_prefix = '../';
$page_title = 'Penugasan Wali Kelas';
$active_menu = 'wali_kelas';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';

// 1. DELETE ASSIGNMENT HANDLER (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Fetch assignment details for audit log
        $stmt_get = $pdo->prepare("
            SELECT wk.kelas_id, k.nama_kelas, g.nama AS nama_guru 
            FROM wali_kelas wk
            INNER JOIN kelas k ON wk.kelas_id = k.id
            INNER JOIN guru g ON wk.guru_id = g.id
            WHERE wk.id = ?
        ");
        $stmt_get->execute([$id]);
        $assignment = $stmt_get->fetch();

        if ($assignment) {
            $stmt_del = $pdo->prepare("DELETE FROM wali_kelas WHERE id = ?");
            $stmt_del->execute([$id]);

            logActivity($pdo, 'Hapus Wali Kelas', "Membatalkan penugasan wali kelas " . $assignment['nama_guru'] . " untuk kelas " . $assignment['nama_kelas']);
            $_SESSION['success_message'] = 'Penugasan Wali Kelas berhasil dihapus.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal menghapus penugasan: ' . $e->getMessage();
    }
    header("Location: wali.php");
    exit();
}

// 2. SAVE/ASSIGN HANDLER (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        header("Location: wali.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set') {
    $kelas_id = (int)$_POST['kelas_id'];
    $guru_id = (int)$_POST['guru_id'];

    if (empty($kelas_id) || empty($guru_id)) {
        $error = 'Kelas dan Guru harus ditentukan.';
    } else {
        try {
            // Check if teacher is already assigned to ANOTHER class
            $chk_guru = $pdo->prepare("SELECT k.nama_kelas FROM wali_kelas wk INNER JOIN kelas k ON wk.kelas_id = k.id WHERE wk.guru_id = ? AND wk.kelas_id != ?");
            $chk_guru->execute([$guru_id, $kelas_id]);
            $existing_class = $chk_guru->fetchColumn();

            if ($existing_class) {
                $error = 'Guru tersebut sudah ditugaskan menjadi wali kelas di ' . htmlspecialchars($existing_class) . '. Satu guru hanya boleh memegang satu kelas.';
            } else {
                // Insert or Update
                $stmt = $pdo->prepare("
                    INSERT INTO wali_kelas (kelas_id, guru_id)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE guru_id = VALUES(guru_id)
                ");
                $stmt->execute([$kelas_id, $guru_id]);

                // Fetch details for logging
                $c_stmt = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
                $c_stmt->execute([$kelas_id]);
                $kelas_name = $c_stmt->fetchColumn() ?: 'Unknown';

                $g_stmt = $pdo->prepare("SELECT nama FROM guru WHERE id = ?");
                $g_stmt->execute([$guru_id]);
                $guru_name = $g_stmt->fetchColumn() ?: 'Unknown';

                logActivity($pdo, 'Set Wali Kelas', "Menugaskan $guru_name sebagai Wali Kelas untuk kelas $kelas_name");
                $_SESSION['success_message'] = 'Wali kelas berhasil ditugaskan untuk ' . htmlspecialchars($kelas_name) . '.';
                header("Location: wali.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Kesalahan database: ' . $e->getMessage();
        }
    }
}

// 3. EDIT MODE TRIGGER
$selected_class = null;
$selected_kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
if ($selected_kelas_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT k.id, k.nama_kelas, wk.guru_id, wk.id AS wali_id 
            FROM kelas k 
            LEFT JOIN wali_kelas wk ON k.id = wk.kelas_id 
            WHERE k.id = ?
        ");
        $stmt->execute([$selected_kelas_id]);
        $selected_class = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Gagal memuat kelas terpilih: ' . $e->getMessage();
    }
}

// 4. FETCH DATA LISTS
try {
    // List classes with their advisor details
    $list_stmt = $pdo->query("
        SELECT k.id AS kelas_id, k.nama_kelas, wk.id AS wali_id, g.id AS guru_id, g.nama AS nama_guru, g.nip 
        FROM kelas k
        LEFT JOIN wali_kelas wk ON k.id = wk.kelas_id
        LEFT JOIN guru g ON wk.guru_id = g.id
        ORDER BY k.nama_kelas ASC
    ");
    $class_mappings = $list_stmt->fetchAll();

    // List teachers who are NOT currently assigned as wali kelas
    // If in edit mode, also include the teacher currently assigned to the selected class
    $current_assigned_guru_id = $selected_class ? (int)$selected_class['guru_id'] : 0;
    
    $avail_query = "SELECT id, nama, nip FROM guru WHERE id NOT IN (SELECT guru_id FROM wali_kelas)";
    if ($current_assigned_guru_id) {
        $avail_query .= " OR id = " . $current_assigned_guru_id;
    }
    $avail_query .= " ORDER BY nama ASC";
    
    $avail_teachers = $pdo->query($avail_query)->fetchAll();
} catch (PDOException $e) {
    $class_mappings = [];
    $avail_teachers = [];
    $error = 'Kesalahan database: ' . $e->getMessage();
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Penugasan Wali Kelas</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Class Advisory List -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-building text-primary me-2"></i> Pemetaan Kelas & Wali Kelas</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Nama Kelas</th>
                                <th>Wali Kelas</th>
                                <th>NIP</th>
                                <th style="width: 150px;" class="text-end px-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($class_mappings)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada data kelas terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($class_mappings as $index => $m): ?>
                                    <tr>
                                        <td class="text-center text-muted small"><?php echo $index + 1; ?></td>
                                        <td class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($m['nama_kelas']); ?></td>
                                        <td>
                                            <?php if ($m['nama_guru']): ?>
                                                <span class="fw-semibold text-primary-emphasis"><?php echo htmlspecialchars($m['nama_guru']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted italic small">Belum Ditugaskan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-secondary small font-monospace">
                                            <?php echo htmlspecialchars($m['nip'] ?: '-'); ?>
                                        </td>
                                        <td class="text-end px-3">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="?kelas_id=<?php echo $m['kelas_id']; ?>" class="btn btn-sm btn-outline-warning text-dark border-warning" title="Atur / Ubah Wali Kelas">
                                                    <i class="bi bi-pencil-square"></i> Atur
                                                </a>
                                                <?php if ($m['wali_id']): ?>
                                                    <a href="?action=delete&id=<?php echo $m['wali_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan penugasan wali kelas ini?')" title="Hapus Penugasan">
                                                        <i class="bi bi-x-circle"></i> Batal
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Interactive Assignment Form -->
    <div class="col-12 col-lg-4">
        <?php if ($selected_class): ?>
            <!-- Set Assignment Form -->
            <div class="card shadow-sm border-0 bg-light-subtle border-start border-warning border-3">
                <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-warning-emphasis"><i class="bi bi-person-workspace"></i> Pengaturan Wali Kelas</h6>
                    <a href="wali.php" class="btn-close" aria-label="Close" style="font-size: 10px;"></a>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="set">
                        <input type="hidden" name="kelas_id" value="<?php echo $selected_class['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Kelas Terpilih</label>
                            <input type="text" class="form-control form-control-sm bg-body-secondary fw-bold" value="<?php echo htmlspecialchars($selected_class['nama_kelas']); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="guru_id" class="form-label small fw-semibold">Wali Kelas <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="guru_id" name="guru_id" required>
                                <option value="" disabled selected>-- Pilih Guru --</option>
                                <?php foreach ($avail_teachers as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php echo (int)$selected_class['guru_id'] === (int)$g['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['nama']); ?> (NIP: <?php echo htmlspecialchars($g['nip'] ?: '-'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" style="font-size: 10px;">Hanya guru yang belum memegang kelas lain yang muncul di daftar ini.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                            <a href="wali.php" class="btn btn-sm btn-outline-secondary fw-semibold">Batal</a>
                            <button type="submit" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-save me-1"></i> Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Default Info Card -->
            <div class="card shadow-sm border-0 bg-light-subtle p-3">
                <div class="card-body text-center text-muted py-4">
                    <i class="bi bi-info-circle text-info fs-1 d-block mb-3"></i>
                    <h6 class="fw-bold text-dark-emphasis mb-1">Panduan Pengaturan</h6>
                    <p class="small text-muted mb-0">
                        Klik tombol <strong>Atur</strong> pada baris kelas di tabel sebelah kiri untuk menunjuk atau mengganti Wali Kelas untuk kelas tersebut.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
