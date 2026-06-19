<?php
$path_prefix = '../';
$page_title = 'Edit Pembayaran SPP';
$active_menu = 'spp';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';
$payment = null;

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = 'ID Transaksi tidak ditentukan.';
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT s.*, sw.nama AS nama_siswa, sw.nis, k.nama_kelas
        FROM spp_pembayaran s
        JOIN siswa sw ON s.siswa_id = sw.id
        LEFT JOIN kelas k ON sw.kelas_id = k.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $_SESSION['error_message'] = 'Transaksi pembayaran tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Kesalahan database: ' . $e->getMessage();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $bulan = (int)$_POST['bulan'];
    $tahun = (int)$_POST['tahun'];
    $jumlah_bayar = (float)$_POST['jumlah_bayar'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $status_bayar = $_POST['status_bayar'];
    $catatan = trim($_POST['catatan']);

    if (empty($bulan) || empty($tahun) || empty($jumlah_bayar) || empty($tanggal_bayar)) {
        $error = 'Harap isi seluruh kolom wajib yang bertanda bintang (*).';
    } else {
        try {
            // Check if another record already exists for this student and month/year (excluding this transaction)
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM spp_pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ? AND id != ?");
            $check_stmt->execute([$payment['siswa_id'], $bulan, $tahun, $id]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Gagal! Pembayaran SPP untuk siswa tersebut pada periode $bulan/$tahun sudah pernah dicatat dalam transaksi lain.";
            } else {
                $update_stmt = $pdo->prepare("
                    UPDATE spp_pembayaran 
                    SET bulan = ?, tahun = ?, jumlah_bayar = ?, tanggal_bayar = ?, status_bayar = ?, catatan = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([$bulan, $tahun, $jumlah_bayar, $tanggal_bayar, $status_bayar, $catatan, $id]);

                logActivity($pdo, 'Edit SPP', "Mengubah transaksi SPP ID $id untuk siswa {$payment['nama_siswa']} periode $bulan/$tahun senilai Rp " . number_format($jumlah_bayar, 0, ',', '.'));

                $_SESSION['success_message'] = 'Catatan pembayaran SPP berhasil diperbarui.';
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
    <h4 class="fw-bold mb-0">Edit Pembayaran SPP</h4>
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
                <h5 class="fw-bold mb-0 text-warning-emphasis"><i class="bi bi-pencil-square me-2"></i> Ubah Rincian Pembayaran</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <!-- Student profile read-only -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Siswa</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($payment['nama_siswa']); ?> (NIS: <?php echo htmlspecialchars($payment['nis']); ?> - <?php echo htmlspecialchars($payment['nama_kelas'] ?? 'Belum Diatur'); ?>)" readonly>
                        </div>
                        
                        <!-- Month Period -->
                        <div class="col-md-6">
                            <label for="bulan" class="form-label fw-semibold small">Bulan SPP <span class="text-danger">*</span></label>
                            <select class="form-select" id="bulan" name="bulan" required>
                                <option value="">-- Pilih Bulan --</option>
                                <?php foreach ($month_names as $m_num => $m_name): ?>
                                    <option value="<?php echo $m_num; ?>" <?php echo (int)$payment['bulan'] === $m_num ? 'selected' : ''; ?>>
                                        <?php echo $m_name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Period -->
                        <div class="col-md-6">
                            <label for="tahun" class="form-label fw-semibold small">Tahun SPP <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tahun" name="tahun" value="<?php echo htmlspecialchars($payment['tahun']); ?>" min="2000" max="2100" required>
                        </div>

                        <!-- Amount to pay -->
                        <div class="col-md-6">
                            <label for="jumlah_bayar" class="form-label fw-semibold small">Jumlah Bayar (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control fw-bold" id="jumlah_bayar" name="jumlah_bayar" value="<?php echo (int)$payment['jumlah_bayar']; ?>" min="0" required>
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="col-md-6">
                            <label for="tanggal_bayar" class="form-label fw-semibold small">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar" value="<?php echo htmlspecialchars($payment['tanggal_bayar']); ?>" required>
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <label for="status_bayar" class="form-label fw-semibold small">Status Pembayaran <span class="text-danger">*</span></label>
                            <select class="form-select" id="status_bayar" name="status_bayar" required>
                                <option value="Lunas" <?php echo $payment['status_bayar'] === 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                                <option value="Belum Lunas" <?php echo $payment['status_bayar'] === 'Belum Lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="catatan" class="form-label fw-semibold small">Catatan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3" placeholder="Masukkan catatan opsional..."><?php echo htmlspecialchars($payment['catatan'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-warning text-dark fw-bold"><i class="bi bi-check-circle"></i> Perbarui Pembayaran</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
