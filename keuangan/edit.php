<?php
$path_prefix = '../';
$page_title = 'Ubah Transaksi Buku Kas';
$active_menu = 'keuangan';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth: Block Guru and Kepala Sekolah (Only Admin & Operator can modify)
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';

// Get ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['error_message'] = 'ID transaksi tidak valid.';
    header("Location: index.php");
    exit();
}

// Fetch existing transaction
try {
    $stmt = $pdo->prepare("SELECT * FROM keuangan_transaksi WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    
    if (!$t) {
        $_SESSION['error_message'] = 'Transaksi tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $tanggal = trim($_POST['tanggal']);
        $tipe = trim($_POST['tipe']);
        $kategori = trim($_POST['kategori']);
        $nominal = (float)$_POST['nominal'];
        $keterangan = trim($_POST['keterangan']);
        $pencatat = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'System';

        if (empty($tanggal) || empty($tipe) || empty($kategori) || $nominal <= 0) {
            $error = 'Semua field wajib diisi dan nominal harus lebih besar dari 0.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE keuangan_transaksi 
                    SET tanggal = ?, tipe = ?, kategori = ?, nominal = ?, keterangan = ?, pencatat = ?
                    WHERE id = ?
                ");
                $stmt->execute([$tanggal, $tipe, $kategori, $nominal, $keterangan, $pencatat, $id]);

                logActivity($pdo, 'Ubah Kas', "Mengubah transaksi ID $id menjadi tipe $tipe sebesar Rp " . number_format($nominal, 0, ',', '.') . " kategori $kategori");
                $_SESSION['success_message'] = 'Transaksi arus kas berhasil diperbarui.';
                header("Location: index.php");
                exit();
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui transaksi: ' . $e->getMessage();
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Ubah Transaksi Arus Kas</h4>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <!-- Tanggal -->
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label small">Tanggal Transaksi</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($t['tanggal']); ?>" required>
                        </div>

                        <!-- Tipe Kas -->
                        <div class="col-md-6">
                            <label class="form-label small d-block">Tipe Kas</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_pemasukan" value="Pemasukan" <?php echo $t['tipe'] === 'Pemasukan' ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-success fw-bold small" for="tipe_pemasukan">
                                        <i class="bi bi-arrow-down-left-square-fill"></i> Pemasukan (Cash In)
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_pengeluaran" value="Pengeluaran" <?php echo $t['tipe'] === 'Pengeluaran' ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-danger fw-bold small" for="tipe_pengeluaran">
                                        <i class="bi bi-arrow-up-right-square-fill"></i> Pengeluaran (Cash Out)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Kategori (datalist suggestions) -->
                        <div class="col-md-6">
                            <label for="kategori" class="form-label small">Kategori</label>
                            <input type="text" class="form-control" id="kategori" name="kategori" list="kategori_list" value="<?php echo htmlspecialchars($t['kategori']); ?>" placeholder="Pilih atau ketik kategori baru..." required autocomplete="off">
                            <datalist id="kategori_list">
                                <option value="Dana BOS">
                                <option value="Donasi">
                                <option value="Yayasan">
                                <option value="Sponsor">
                                <option value="Listrik & Air">
                                <option value="Alat Tulis Kantor">
                                <option value="Pemeliharaan Gedung">
                                <option value="Kegiatan Sekolah">
                                <option value="Transportasi">
                                <option value="Konsumsi">
                                <option value="Lain-lain">
                            </datalist>
                        </div>

                        <!-- Nominal -->
                        <div class="col-md-6">
                            <label for="nominal" class="form-label small">Nominal Transaksi (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted" style="font-size: 13px;">Rp</span>
                                <input type="number" step="0.01" class="form-control font-monospace fw-bold" id="nominal" name="nominal" min="0.01" value="<?php echo htmlspecialchars($t['nominal']); ?>" required>
                            </div>
                        </div>

                        <!-- Keterangan -->
                        <div class="col-12">
                            <label for="keterangan" class="form-label small">Keterangan / Deskripsi</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="4" placeholder="Detail deskripsi pemakaian kas..."><?php echo htmlspecialchars($t['keterangan']); ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">
                    <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Info Panel -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 bg-light bg-opacity-50">
            <div class="card-body p-4 text-secondary small" style="line-height: 1.6;">
                <h6 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-pencil-square me-2 text-primary"></i> Edit Log Transaksi</h6>
                <p>Mengubah data ini akan langsung mempengaruhi perhitungan kas berjalan sekolah (total penerimaan, pengeluaran, dan saldo kas akhir) secara real-time.</p>
                <p class="mb-0">Setiap pembaruan data akan terekam dalam log audit aktivitas admin.</p>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
