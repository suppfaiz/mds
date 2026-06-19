<?php
$path_prefix = '../';
$page_title = 'Input Pembayaran SPP';
$active_menu = 'spp';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';

// Fetch all active students with their class and SPP tariff
try {
    $stmt = $pdo->query("
        SELECT s.id, s.nama, s.nis, k.nama_kelas, k.tarif_spp
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        ORDER BY s.nama ASC
    ");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
    $error = 'Gagal memuat data siswa: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $siswa_id = (int)$_POST['siswa_id'];
    $bulan = (int)$_POST['bulan'];
    $tahun = (int)$_POST['tahun'];
    $jumlah_bayar = (float)$_POST['jumlah_bayar'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $status_bayar = $_POST['status_bayar'];
    $catatan = trim($_POST['catatan']);
    $penerima_oleh = $_SESSION['username'];

    if (empty($siswa_id) || empty($bulan) || empty($tahun) || empty($jumlah_bayar) || empty($tanggal_bayar)) {
        $error = 'Harap isi seluruh kolom wajib yang bertanda bintang (*).';
    } else {
        try {
            // Check if record already exists for this student and month/year
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM spp_pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ?");
            $check_stmt->execute([$siswa_id, $bulan, $tahun]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Gagal! Pembayaran SPP untuk siswa tersebut pada bulan $bulan tahun $tahun sudah pernah dicatat sebelumnya.";
            } else {
                $invoice_token = bin2hex(random_bytes(16));
                $insert_stmt = $pdo->prepare("
                    INSERT INTO spp_pembayaran (siswa_id, bulan, tahun, jumlah_bayar, tanggal_bayar, status_bayar, penerima_oleh, catatan, invoice_token)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$siswa_id, $bulan, $tahun, $jumlah_bayar, $tanggal_bayar, $status_bayar, $penerima_oleh, $catatan, $invoice_token]);
                
                // Get student name for audit details
                $stud_stmt = $pdo->prepare("SELECT nama FROM siswa WHERE id = ?");
                $stud_stmt->execute([$siswa_id]);
                $student_name = $stud_stmt->fetchColumn();

                logActivity($pdo, 'Input SPP', "Mencatat pembayaran SPP siswa $student_name periode $bulan/$tahun senilai Rp " . number_format($jumlah_bayar, 0, ',', '.'));

                $_SESSION['success_message'] = 'Catatan pembayaran SPP berhasil disimpan.';
                header("Location: index.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Kesalahan database: ' . $e->getMessage();
        }
    }
    }
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <h4 class="fw-bold mb-0">Input Pembayaran SPP</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-wallet2 text-primary me-2"></i> Rincian Pembayaran</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST" id="sppForm">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <!-- Student picker -->
                        <div class="col-12">
                            <label for="siswa_id" class="form-label fw-semibold small">Siswa <span class="text-danger">*</span></label>
                            <select class="form-select" id="siswa_id" name="siswa_id" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" data-tariff="<?php echo (int)($s['tarif_spp'] ?? 500000); ?>">
                                        <?php echo htmlspecialchars($s['nama']); ?> (NIS: <?php echo htmlspecialchars($s['nis']); ?> - <?php echo htmlspecialchars($s['nama_kelas'] ?? 'Belum Diatur'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Month Period -->
                        <div class="col-md-6">
                            <label for="bulan" class="form-label fw-semibold small">Bulan SPP <span class="text-danger">*</span></label>
                            <select class="form-select" id="bulan" name="bulan" required>
                                <option value="">-- Pilih Bulan --</option>
                                <?php foreach ($month_names as $m_num => $m_name): ?>
                                    <option value="<?php echo $m_num; ?>" <?php echo (int)date('m') === $m_num ? 'selected' : ''; ?>>
                                        <?php echo $m_name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Period -->
                        <div class="col-md-6">
                            <label for="tahun" class="form-label fw-semibold small">Tahun SPP <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tahun" name="tahun" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
                        </div>

                        <!-- Amount to pay -->
                        <div class="col-md-6">
                            <label for="jumlah_bayar" class="form-label fw-semibold small">Jumlah Bayar (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control fw-bold" id="jumlah_bayar" name="jumlah_bayar" placeholder="0" min="0" required>
                            </div>
                            <div class="form-text" style="font-size: 11px;">Nominal akan terisi otomatis mengikuti tarif kelas siswa terpilih.</div>
                        </div>

                        <!-- Date -->
                        <div class="col-md-6">
                            <label for="tanggal_bayar" class="form-label fw-semibold small">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar" value="<?php echo date('Y-md'); ?>" required>
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <label for="status_bayar" class="form-label fw-semibold small">Status Pembayaran <span class="text-danger">*</span></label>
                            <select class="form-select" id="status_bayar" name="status_bayar" required>
                                <option value="Lunas" selected>Lunas</option>
                                <option value="Belum Lunas">Belum Lunas</option>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="catatan" class="form-label fw-semibold small">Catatan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3" placeholder="Masukkan catatan opsional..."></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save"></i> Simpan Pembayaran</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 bg-light-subtle">
            <div class="card-body p-4 text-center">
                <i class="bi bi-info-circle text-info fs-1 mb-2"></i>
                <h6 class="fw-bold text-info-emphasis">Informasi Auto-tarif</h6>
                <p class="small text-muted mb-0">
                    Sistem akan memuat nominal biaya bulanan berdasarkan tarif yang terdaftar pada kelas siswa. 
                    Anda tetap dapat mengubah nominal secara manual jika siswa menerima potongan khusus atau dispensasi biaya.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const studentSelect = document.getElementById("siswa_id");
    const amountInput = document.getElementById("jumlah_bayar");
    const dateInput = document.getElementById("tanggal_bayar");

    // Set today date as default in local timezone
    const today = new Date().toISOString().split('T')[0];
    dateInput.value = today;

    studentSelect.addEventListener("change", function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.value !== "") {
            const tariff = selectedOption.getAttribute("data-tariff");
            amountInput.value = tariff;
        } else {
            amountInput.value = "";
        }
    });
});
</script>

<?php include $path_prefix . 'includes/footer.php'; ?>
