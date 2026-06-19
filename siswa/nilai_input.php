<?php
$path_prefix = '../';
$page_title = 'Input Nilai Siswa';
$active_menu = 'siswa';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page - Only Admin, Operator, and Guru can input grades
checkRole(['super_admin', 'operator', 'guru']);

$error = '';
$success = '';
$siswa_id = 0;
$nilai_id = 0;
$siswa = null;
$nilai = null;

// Determine if we are adding or editing
if (isset($_GET['siswa_id'])) {
    $siswa_id = (int)$_GET['siswa_id'];
}
if (isset($_GET['id'])) {
    $nilai_id = (int)$_GET['id'];
}

// Load student details
if ($siswa_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
        $stmt->execute([$siswa_id]);
        $siswa = $stmt->fetch();
        
        if (!$siswa) {
            $_SESSION['error_message'] = 'Siswa tidak ditemukan.';
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal memuat profil: ' . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = 'ID Siswa tidak valid.';
    header("Location: index.php");
    exit();
}

// If editing, load current grade entry
if ($nilai_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id = ? AND siswa_id = ?");
        $stmt->execute([$nilai_id, $siswa_id]);
        $nilai = $stmt->fetch();
        
        if (!$nilai) {
            $_SESSION['error_message'] = 'Entry nilai tidak ditemukan.';
            header("Location: detail.php?id=" . $siswa_id);
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal memuat nilai: ' . $e->getMessage();
        header("Location: detail.php?id=" . $siswa_id);
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $mata_pelajaran = trim($_POST['mata_pelajaran']);
    $nilai_tugas = (float)$_POST['nilai_tugas'];
    $nilai_uts = (float)$_POST['nilai_uts'];
    $nilai_uas = (float)$_POST['nilai_uas'];
    $semester = $_POST['semester'] ?? '';
    $tahun_ajaran = trim($_POST['tahun_ajaran']);
    $keterangan = trim($_POST['keterangan']);

    // Form validation
    if (empty($mata_pelajaran) || empty($semester) || empty($tahun_ajaran)) {
        $error = 'Mata Pelajaran, Semester, dan Tahun Ajaran wajib diisi.';
    } elseif ($nilai_tugas < 0 || $nilai_tugas > 100 || $nilai_uts < 0 || $nilai_uts > 100 || $nilai_uas < 0 || $nilai_uas > 100) {
        $error = 'Nilai harus berada di rentang 0 sampai 100.';
    } else {
        // Calculate Nilai Akhir (Tugas 30%, UTS 30%, UAS 40%)
        $nilai_akhir = ($nilai_tugas * 0.30) + ($nilai_uts * 0.30) + ($nilai_uas * 0.40);
        
        try {
            if ($nilai_id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE nilai SET 
                    mata_pelajaran = ?, nilai_tugas = ?, nilai_uts = ?, 
                    nilai_uas = ?, nilai_akhir = ?, semester = ?, 
                    tahun_ajaran = ?, keterangan = ? 
                    WHERE id = ? AND siswa_id = ?");
                $stmt->execute([
                    $mata_pelajaran, $nilai_tugas, $nilai_uts, 
                    $nilai_uas, $nilai_akhir, $semester, 
                    $tahun_ajaran, $keterangan, $nilai_id, $siswa_id
                ]);
                
                logActivity($pdo, 'Edit Nilai', 'Mengupdate nilai ' . $mata_pelajaran . ' untuk siswa: ' . $siswa['nama'] . ' (ID: ' . $siswa_id . ')');
                $_SESSION['success_message'] = 'Nilai pelajaran ' . htmlspecialchars($mata_pelajaran) . ' berhasil diperbarui.';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO nilai 
                    (siswa_id, mata_pelajaran, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, semester, tahun_ajaran, keterangan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $siswa_id, $mata_pelajaran, $nilai_tugas, $nilai_uts, 
                    $nilai_uas, $nilai_akhir, $semester, $tahun_ajaran, $keterangan
                ]);
                
                logActivity($pdo, 'Tambah Nilai', 'Menginput nilai ' . $mata_pelajaran . ' untuk siswa: ' . $siswa['nama'] . ' (ID: ' . $siswa_id . ')');
                $_SESSION['success_message'] = 'Nilai pelajaran ' . htmlspecialchars($mata_pelajaran) . ' berhasil disimpan.';
            }
            
            header("Location: detail.php?id=" . $siswa_id . "#transkrip");
            exit();
        } catch (PDOException $e) {
            $error = 'Kesalahan database: ' . $e->getMessage();
        }
    }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="detail.php?id=<?php echo $siswa_id; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
            <h4 class="fw-bold mb-0"><?php echo $nilai_id > 0 ? 'Edit Nilai Akademik' : 'Input Nilai Akademik'; ?></h4>
        </div>

        <!-- Student Info Card -->
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="small text-muted d-block">Nama Lengkap Siswa</span>
                    <strong class="fs-5 text-dark-emphasis"><?php echo htmlspecialchars($siswa['nama']); ?></strong>
                </div>
                <div class="text-md-end">
                    <span class="small text-muted d-block">NIS / NISN</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($siswa['nis'] . ' / ' . $siswa['nisn']); ?></span>
                </div>
                <div>
                    <span class="small text-muted d-block">Kelas</span>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Diatur'); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="" method="POST" id="nilaiForm">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="mata_pelajaran" class="form-label small">Mata Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="mata_pelajaran" name="mata_pelajaran" placeholder="Contoh: Matematika, Fisika, B. Indonesia" value="<?php echo htmlspecialchars($nilai['mata_pelajaran'] ?? ($_POST['mata_pelajaran'] ?? '')); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="semester" class="form-label small">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="" disabled selected>Pilih...</option>
                                <option value="Ganjil" <?php echo (($nilai['semester'] ?? ($_POST['semester'] ?? '')) === 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                <option value="Genap" <?php echo (($nilai['semester'] ?? ($_POST['semester'] ?? '')) === 'Genap') ? 'selected' : ''; ?>>Genap</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="tahun_ajaran" class="form-label small">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2025/2026" value="<?php echo htmlspecialchars($nilai['tahun_ajaran'] ?? ($_POST['tahun_ajaran'] ?? (date('Y')-1) . '/' . date('Y'))); ?>" required>
                        </div>

                        <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>

                        <div class="col-md-4">
                            <label for="nilai_tugas" class="form-label small">Nilai Tugas (Bobot 30%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control marks-input" id="nilai_tugas" name="nilai_tugas" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($nilai['nilai_tugas'] ?? ($_POST['nilai_tugas'] ?? '0')); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="nilai_uts" class="form-label small">Nilai UTS (Bobot 30%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control marks-input" id="nilai_uts" name="nilai_uts" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($nilai['nilai_uts'] ?? ($_POST['nilai_uts'] ?? '0')); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="nilai_uas" class="form-label small">Nilai UAS (Bobot 40%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control marks-input" id="nilai_uas" name="nilai_uas" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($nilai['nilai_uas'] ?? ($_POST['nilai_uas'] ?? '0')); ?>" required>
                        </div>

                        <!-- Display Live Calculation of Nilai Akhir -->
                        <div class="col-12 mt-3">
                            <div class="bg-primary bg-opacity-10 rounded p-3 d-flex justify-content-between align-items-center border border-primary-subtle">
                                <div class="text-primary-emphasis fw-semibold">Estimasi Nilai Akhir:</div>
                                <h3 class="m-0 fw-bold text-primary" id="live-final-mark">0.00</h3>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="keterangan" class="form-label small">Catatan / Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Catatan tambahan prestasi atau perbaikan..."><?php echo htmlspecialchars($nilai['keterangan'] ?? ($_POST['keterangan'] ?? '')); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 mt-4 fw-bold shadow-sm">
                        <i class="bi bi-check-circle"></i> Simpan Transkrip Nilai
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
// JavaScript calculation trigger
$extra_js = '
<script>
document.addEventListener("DOMContentLoaded", () => {
    const inputs = document.querySelectorAll(".marks-input");
    const finalMarkText = document.getElementById("live-final-mark");
    
    function calculate() {
        const tugas = parseFloat(document.getElementById("nilai_tugas").value) || 0;
        const uts = parseFloat(document.getElementById("nilai_uts").value) || 0;
        const uas = parseFloat(document.getElementById("nilai_uas").value) || 0;
        
        const finalMark = (tugas * 0.30) + (uts * 0.30) + (uas * 0.40);
        finalMarkText.textContent = finalMark.toFixed(2);
        
        // Dynamic color changes based on mark threshold
        if (finalMark >= 75) {
            finalMarkText.className = "m-0 fw-bold text-success";
        } else if (finalMark >= 60) {
            finalMarkText.className = "m-0 fw-bold text-warning";
        } else {
            finalMarkText.className = "m-0 fw-bold text-danger";
        }
    }
    
    inputs.forEach(input => {
        input.addEventListener("input", calculate);
    });
    
    // Initial run
    calculate();
});
</script>
';
include $path_prefix . 'includes/footer.php'; 
?>
