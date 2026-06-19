<?php
$path_prefix = '../';
$page_title = 'Layanan Rapor Digital';
$active_menu = 'rapor';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth check
checkRole(['super_admin', 'operator', 'guru', 'kepala_sekolah']);

$role = $_SESSION['role'];
$error = '';
$success = '';

// Check if Guru is a Wali Kelas
$my_wali_kelas_id = null;
$my_wali_kelas_name = '';

if ($role === 'guru') {
    try {
        $stmt_wk = $pdo->prepare("
            SELECT wk.kelas_id, k.nama_kelas 
            FROM wali_kelas wk
            INNER JOIN kelas k ON wk.kelas_id = k.id
            INNER JOIN guru g ON wk.guru_id = g.id
            WHERE LOWER(g.nama) = LOWER(?)
        ");
        $stmt_wk->execute([$_SESSION['nama_lengkap'] ?? '']);
        $wk_row = $stmt_wk->fetch();
        if ($wk_row) {
            $my_wali_kelas_id = (int)$wk_row['kelas_id'];
            $my_wali_kelas_name = $wk_row['nama_kelas'];
        } else {
            $_SESSION['error_message'] = 'Akses ditolak! Anda tidak terdaftar sebagai Wali Kelas.';
            header("Location: ../index.php");
            exit();
        }
    } catch (PDOException $e) {
        $error = 'Kesalahan memeriksa wali kelas: ' . $e->getMessage();
    }
}

// Get Selected Class
$kelas_id = 0;
if ($role === 'guru') {
    $kelas_id = $my_wali_kelas_id;
} else {
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
}

// Fetch all classes for filters (Admins/Principals/Operators only)
$classes = [];
if ($role !== 'guru') {
    try {
        $classes = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
        if (!$kelas_id && !empty($classes)) {
            $kelas_id = (int)$classes[0]['id'];
        }
    } catch (PDOException $e) {
        $error = 'Gagal memuat kelas: ' . $e->getMessage();
    }
}

// Fetch class name & student list
$students = [];
$class_name = '';
if ($kelas_id) {
    try {
        $c_stmt = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $c_stmt->execute([$kelas_id]);
        $class_name = $c_stmt->fetchColumn() ?: '';

        $s_stmt = $pdo->prepare("SELECT id, nama, nis, nisn, jenis_kelamin FROM siswa WHERE kelas_id = ? ORDER BY nama ASC");
        $s_stmt->execute([$kelas_id]);
        $students = $s_stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Gagal memuat siswa: ' . $e->getMessage();
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Rapor Digital</h4>
    <?php if ($role === 'guru'): ?>
        <span class="badge bg-primary fs-6 py-2 px-3 shadow-sm">
            Wali Kelas: <?php echo htmlspecialchars($my_wali_kelas_name); ?>
        </span>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Class Selector for Administrators and Principals -->
<?php if ($role !== 'guru' && !empty($classes)): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-12 col-md-9">
                    <label for="kelas_id" class="form-label fw-semibold small">Pilih Kelas</label>
                    <select class="form-select" id="kelas_id" name="kelas_id" onchange="this.form.submit()" required>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $kelas_id === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-filter"></i> Tampilkan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Students Grid List -->
<?php if ($kelas_id): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-0 pt-3 px-3">
            <h6 class="fw-bold mb-0">Daftar Rapor Siswa Kelas <?php echo htmlspecialchars($class_name ?: $my_wali_kelas_name); ?></h6>
            <small class="text-muted">Total: <?php echo count($students); ?> Siswa terdaftar</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-people fs-1 d-block mb-3"></i>
                    <h6>Belum ada siswa terdaftar di kelas ini.</h6>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;" class="text-center">No</th>
                                <th>NIS/NISN</th>
                                <th>Nama Lengkap</th>
                                <th style="width: 120px;" class="text-center">Gender</th>
                                <th style="width: 280px;" class="text-end px-4">Tindakan Rapor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $s): ?>
                                <tr>
                                    <td class="text-center text-muted small"><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="fw-semibold small text-secondary-emphasis"><?php echo htmlspecialchars($s['nis']); ?></div>
                                        <div class="text-muted" style="font-size: 11px;">NISN: <?php echo htmlspecialchars($s['nisn']); ?></div>
                                    </td>
                                    <td class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($s['nama']); ?></td>
                                    <td class="text-center small">
                                        <?php echo $s['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?>
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <!-- Manage Rapor (only super_admin, operator, and wali kelas) -->
                                            <?php if (hasPermission(['super_admin', 'operator']) || ($role === 'guru' && $my_wali_kelas_id === $kelas_id)): ?>
                                                <a href="input.php?siswa_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                                    <i class="bi bi-pencil-square"></i> Kelola Catatan
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Print Rapor (available for all roles including Kepala Sekolah) -->
                                            <a href="print.php?siswa_id=<?php echo $s['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold">
                                                <i class="bi bi-printer"></i> Cetak Rapor
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include $path_prefix . 'includes/footer.php'; ?>
