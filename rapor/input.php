<?php
$path_prefix = '../';
$page_title = 'Kelola Catatan Rapor';
$active_menu = 'rapor';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth Check
checkRole(['super_admin', 'operator', 'guru']);

$role = $_SESSION['role'];
$error = '';
$success = '';

// Get Student ID
$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
if (!$siswa_id) {
    $_SESSION['error_message'] = 'ID Siswa tidak valid.';
    header("Location: index.php");
    exit();
}

// Fetch Student details & Verify advisor rights
try {
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$siswa_id]);
    $siswa = $stmt->fetch();
    
    if (!$siswa) {
        $_SESSION['error_message'] = 'Siswa tidak ditemukan.';
        header("Location: index.php");
        exit();
    }

    // If teacher, check if they are the wali kelas of this student
    if ($role === 'guru') {
        $stmt_wk = $pdo->prepare("
            SELECT wk.kelas_id 
            FROM wali_kelas wk
            INNER JOIN guru g ON wk.guru_id = g.id
            WHERE LOWER(g.nama) = LOWER(?)
        ");
        $stmt_wk->execute([$_SESSION['nama_lengkap'] ?? '']);
        $my_kelas_id = $stmt_wk->fetchColumn();
        
        if ((int)$my_kelas_id !== (int)$siswa['kelas_id']) {
            $_SESSION['error_message'] = 'Akses ditolak! Anda bukan Wali Kelas dari siswa ini.';
            header("Location: index.php");
            exit();
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Kesalahan database: ' . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Get Year & Semester Period Filter
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'Ganjil';
if (!in_array($semester, ['Ganjil', 'Genap'])) {
    $semester = 'Ganjil';
}
$tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '2025/2026'; // Standard school year format

// Handle Saving/Upserting Rapor Notes (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $post_semester = $_POST['semester'];
    $post_tahun_ajaran = $_POST['tahun_ajaran'];
    $ekstrakurikuler = trim($_POST['ekstrakurikuler']);
    $kelakuan = $_POST['kelakuan'];
    $kerajinan = $_POST['kerajinan'];
    $kerapihan = $_POST['kerapihan'];
    $catatan = trim($_POST['catatan']);

    if (empty($post_semester) || empty($post_tahun_ajaran)) {
        $error = 'Periode Rapor harus ditentukan.';
    } else {
        try {
            $stmt_save = $pdo->prepare("
                INSERT INTO rapor_catatan (siswa_id, semester, tahun_ajaran, ekstrakurikuler, kelakuan, kerajinan, kerapihan, catatan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    ekstrakurikuler = VALUES(ekstrakurikuler),
                    kelakuan = VALUES(kelakuan),
                    kerajinan = VALUES(kerajinan),
                    kerapihan = VALUES(kerapihan),
                    catatan = VALUES(catatan)
            ");
            $stmt_save->execute([$siswa_id, $post_semester, $post_tahun_ajaran, $ekstrakurikuler, $kelakuan, $kerajinan, $kerapihan, $catatan]);

            logActivity($pdo, 'Kelola Rapor', "Memperbarui catatan rapor siswa " . $siswa['nama'] . " periode $post_semester/$post_tahun_ajaran");
            $_SESSION['success_message'] = 'Catatan perkembangan rapor berhasil disimpan.';
            header("Location: input.php?siswa_id=$siswa_id&semester=$post_semester&tahun_ajaran=$post_tahun_ajaran");
            exit();
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
    }
}

// Fetch existing Rapor Notes
$rapor = null;
try {
    $r_stmt = $pdo->prepare("SELECT * FROM rapor_catatan WHERE siswa_id = ? AND semester = ? AND tahun_ajaran = ?");
    $r_stmt->execute([$siswa_id, $semester, $tahun_ajaran]);
    $rapor = $r_stmt->fetch();
} catch (PDOException $e) {
    $error = 'Kesalahan memuat catatan rapor: ' . $e->getMessage();
}

// Fetch Academic Grades for selected period
$grades = [];
try {
    $g_stmt = $pdo->prepare("SELECT * FROM nilai WHERE siswa_id = ? AND semester = ? AND tahun_ajaran = ? ORDER BY mata_pelajaran ASC");
    $g_stmt->execute([$siswa_id, $semester, $tahun_ajaran]);
    $grades = $g_stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Kesalahan memuat transkrip nilai: ' . $e->getMessage();
}

// Calculate Attendance for selected period based on Date range
// Semester Ganjil runs July-Dec, Genap runs Jan-June
list($year_start, $year_end) = explode('/', $tahun_ajaran);
if ($semester === 'Ganjil') {
    $date_start = $year_start . '-07-01';
    $date_end = $year_start . '-12-31';
} else {
    $date_start = $year_end . '-01-01';
    $date_end = $year_end . '-06-30';
}

$att_summary = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
try {
    $att_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as jumlah 
        FROM presensi_siswa 
        WHERE siswa_id = ? AND tanggal BETWEEN ? AND ? 
        GROUP BY status
    ");
    $att_stmt->execute([$siswa_id, $date_start, $date_end]);
    $att_rows = $att_stmt->fetchAll();
    foreach ($att_rows as $ar) {
        $att_summary[$ar['status']] = (int)$ar['jumlah'];
    }
} catch (PDOException $e) {
    // Fail silently or log
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Catatan Rapor Siswa</h4>
    </div>
    <a href="print.php?siswa_id=<?php echo $siswa_id; ?>&semester=<?php echo $semester; ?>&tahun_ajaran=<?php echo urlencode($tahun_ajaran); ?>" target="_blank" class="btn btn-outline-primary shadow-sm btn-sm fw-semibold">
        <i class="bi bi-printer-fill me-1"></i> Cetak Lembar Rapor A4
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Filter Period Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="siswa_id" value="<?php echo $siswa_id; ?>">
            <div class="col-12 col-md-5">
                <label for="semester" class="form-label fw-semibold small">Semester</label>
                <select class="form-select form-select-sm" id="semester" name="semester" onchange="this.form.submit()" required>
                    <option value="Ganjil" <?php echo $semester === 'Ganjil' ? 'selected' : ''; ?>>Ganjil (Juli - Desember)</option>
                    <option value="Genap" <?php echo $semester === 'Genap' ? 'selected' : ''; ?>>Genap (Januari - Juni)</option>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label for="tahun_ajaran" class="form-label fw-semibold small">Tahun Ajaran</label>
                <select class="form-select form-select-sm" id="tahun_ajaran" name="tahun_ajaran" onchange="this.form.submit()" required>
                    <option value="2024/2025" <?php echo $tahun_ajaran === '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                    <option value="2025/2026" <?php echo $tahun_ajaran === '2025/2026' ? 'selected' : ''; ?>>2025/2026</option>
                    <option value="2026/2027" <?php echo $tahun_ajaran === '2026/2027' ? 'selected' : ''; ?>>2026/2027</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold"><i class="bi bi-arrow-repeat"></i> Load</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Left Panel: Input Personality & Remarks Form -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0 text-primary-emphasis"><i class="bi bi-journal-check me-1"></i> Form Penilaian Rapor</h5>
                <small class="text-muted">Nama: <?php echo htmlspecialchars($siswa['nama']); ?> &bull; Kelas: <?php echo htmlspecialchars($siswa['nama_kelas']); ?></small>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="semester" value="<?php echo $semester; ?>">
                    <input type="hidden" name="tahun_ajaran" value="<?php echo htmlspecialchars($tahun_ajaran); ?>">

                    <div class="row g-3">
                        <!-- Personality Traits -->
                        <div class="col-12">
                            <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2"><i class="bi bi-award me-1"></i> Aspek Kepribadian & Sikap</h6>
                        </div>
                        <div class="col-md-4">
                            <label for="kelakuan" class="form-label small fw-semibold">Kelakuan</label>
                            <select class="form-select form-select-sm" id="kelakuan" name="kelakuan" required>
                                <option value="A" <?php echo ($rapor && $rapor['kelakuan'] === 'A') ? 'selected' : ''; ?>>A (Sangat Baik)</option>
                                <option value="B" <?php echo (!$rapor || $rapor['kelakuan'] === 'B') ? 'selected' : ''; ?>>B (Baik)</option>
                                <option value="C" <?php echo ($rapor && $rapor['kelakuan'] === 'C') ? 'selected' : ''; ?>>C (Cukup)</option>
                                <option value="D" <?php echo ($rapor && $rapor['kelakuan'] === 'D') ? 'selected' : ''; ?>>D (Kurang)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="kerajinan" class="form-label small fw-semibold">Kerajinan</label>
                            <select class="form-select form-select-sm" id="kerajinan" name="kerajinan" required>
                                <option value="A" <?php echo ($rapor && $rapor['kerajinan'] === 'A') ? 'selected' : ''; ?>>A (Sangat Baik)</option>
                                <option value="B" <?php echo (!$rapor || $rapor['kerajinan'] === 'B') ? 'selected' : ''; ?>>B (Baik)</option>
                                <option value="C" <?php echo ($rapor && $rapor['kerajinan'] === 'C') ? 'selected' : ''; ?>>C (Cukup)</option>
                                <option value="D" <?php echo ($rapor && $rapor['kerajinan'] === 'D') ? 'selected' : ''; ?>>D (Kurang)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="kerapihan" class="form-label small fw-semibold">Kerapihan</label>
                            <select class="form-select form-select-sm" id="kerapihan" name="kerapihan" required>
                                <option value="A" <?php echo ($rapor && $rapor['kerapihan'] === 'A') ? 'selected' : ''; ?>>A (Sangat Baik)</option>
                                <option value="B" <?php echo (!$rapor || $rapor['kerapihan'] === 'B') ? 'selected' : ''; ?>>B (Baik)</option>
                                <option value="C" <?php echo ($rapor && $rapor['kerapihan'] === 'C') ? 'selected' : ''; ?>>C (Cukup)</option>
                                <option value="D" <?php echo ($rapor && $rapor['kerapihan'] === 'D') ? 'selected' : ''; ?>>D (Kurang)</option>
                            </select>
                        </div>

                        <!-- Extracurricular Activities -->
                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2"><i class="bi bi-activity me-1"></i> Kegiatan Ekstrakurikuler</h6>
                            <label for="ekstrakurikuler" class="form-label small text-muted">Format: Tulis nama ekskul diikuti nilai/keterangan (satu kegiatan per baris).</label>
                            <textarea class="form-control form-control-sm" id="ekstrakurikuler" name="ekstrakurikuler" rows="3" placeholder="Contoh:&#10;1. Pramuka: Sangat Baik&#10;2. Futsal: Baik"><?php echo $rapor ? htmlspecialchars($rapor['ekstrakurikuler']) : ''; ?></textarea>
                        </div>

                        <!-- Advisory Remarks -->
                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2"><i class="bi bi-chat-left-text me-1"></i> Catatan Wali Kelas</h6>
                            <textarea class="form-control" id="catatan" name="catatan" rows="4" placeholder="Ketik catatan perkembangan atau motivasi belajar untuk siswa..."><?php echo $rapor ? htmlspecialchars($rapor['catatan']) : ''; ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">
                    <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Catatan Rapor</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Panel: Academic Grades & Attendance Reference -->
    <div class="col-12 col-lg-5">
        <!-- Attendance Widget -->
        <div class="card shadow-sm border-0 mb-4 bg-light-subtle">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0 text-dark-emphasis"><i class="bi bi-calendar2-check text-info me-1"></i> Rangkuman Kehadiran (Semester Ini)</h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 text-center">
                    <div class="col-3">
                        <div class="border rounded p-2 bg-success bg-opacity-10 text-success fw-bold">
                            <span class="d-block small text-muted font-monospace" style="font-size: 10px;">H</span>
                            <?php echo $att_summary['Hadir']; ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="border rounded p-2 bg-primary bg-opacity-10 text-primary fw-bold">
                            <span class="d-block small text-muted font-monospace" style="font-size: 10px;">S</span>
                            <?php echo $att_summary['Sakit']; ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="border rounded p-2 bg-warning bg-opacity-10 text-warning fw-bold">
                            <span class="d-block small text-muted font-monospace" style="font-size: 10px;">I</span>
                            <?php echo $att_summary['Izin']; ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="border rounded p-2 bg-danger bg-opacity-10 text-danger fw-bold">
                            <span class="d-block small text-muted font-monospace" style="font-size: 10px;">A</span>
                            <?php echo $att_summary['Alpa']; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Grades Reference -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0 text-dark-emphasis"><i class="bi bi-journal-bookmark text-success me-1"></i> Transkrip Nilai Akademik</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($grades)): ?>
                    <div class="p-4 text-center text-muted small">
                        Belum ada nilai terdaftar untuk periode semester dan tahun ajaran terpilih.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th style="width: 70px;" class="text-center">Akhir</th>
                                    <th style="width: 70px;" class="text-center">Predikat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $g): 
                                    $final = (float)$g['nilai_akhir'];
                                    if ($final >= 85) { $badge = 'bg-success-subtle text-success'; $pdt = 'A'; }
                                    elseif ($final >= 75) { $badge = 'bg-primary-subtle text-primary'; $pdt = 'B'; }
                                    elseif ($final >= 60) { $badge = 'bg-warning-subtle text-warning-emphasis'; $pdt = 'C'; }
                                    else { $badge = 'bg-danger-subtle text-danger'; $pdt = 'D'; }
                                ?>
                                    <tr>
                                        <td class="fw-semibold text-secondary-emphasis py-2"><?php echo htmlspecialchars($g['mata_pelajaran']); ?></td>
                                        <td class="text-center font-monospace py-2"><?php echo number_format($final, 1); ?></td>
                                        <td class="text-center py-2">
                                            <span class="badge <?php echo $badge; ?>" style="font-size: 10px; padding: 2px 6px;">
                                                <?php echo $pdt; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
