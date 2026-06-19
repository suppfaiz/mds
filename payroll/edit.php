<?php
$path_prefix = '../';
$page_title = 'Edit Data Payroll / Gaji';
$active_menu = 'payroll';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';
$payroll = null;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT p.*, 
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.nama 
                        ELSE k.nama 
                     END AS nama_penerima,
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.jabatan 
                        ELSE k.jabatan 
                     END AS jabatan_penerima,
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.nip 
                        ELSE k.nik 
                     END AS identitas_penerima
              FROM payroll p 
              LEFT JOIN guru g ON p.tipe_penerima = 'guru' AND p.penerima_id = g.id
              LEFT JOIN karyawan k ON p.tipe_penerima = 'karyawan' AND p.penerima_id = k.id
              WHERE p.id = ?");
        $stmt->execute([$id]);
        $payroll = $stmt->fetch();
        
        if (!$payroll) {
            $_SESSION['error_message'] = 'Data payroll tidak ditemukan.';
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        die("Gagal memuat data: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = 'ID Payroll tidak valid.';
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $gaji_pokok = isset($_POST['gaji_pokok']) ? (float)$_POST['gaji_pokok'] : 0.0;
    $tunjangan = isset($_POST['tunjangan']) ? (float)$_POST['tunjangan'] : 0.0;
    $potongan = isset($_POST['potongan']) ? (float)$_POST['potongan'] : 0.0;
    $gaji_bersih = $gaji_pokok + $tunjangan - $potongan;
    
    $status_bayar = $_POST['status_bayar'] ?? 'Belum Dibayar';
    $tanggal_bayar = !empty($_POST['tanggal_bayar']) ? $_POST['tanggal_bayar'] : null;
    $catatan = trim($_POST['catatan'] ?? '');

    // Server-side validation
    if ($gaji_pokok < 0) {
        $error = 'Gaji pokok tidak boleh kurang dari 0.';
    } else {
        try {
            // Adjust payment date logic
            if ($status_bayar === 'Dibayar' && $tanggal_bayar === null) {
                $tanggal_bayar = date('Y-m-d');
            }
            if ($status_bayar === 'Belum Dibayar') {
                $tanggal_bayar = null;
            }

            // Update payroll
            $stmt = $pdo->prepare("UPDATE payroll SET 
                gaji_pokok = ?, tunjangan = ?, potongan = ?, gaji_bersih = ?, 
                status_bayar = ?, tanggal_bayar = ?, catatan = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $gaji_pokok, $tunjangan, $potongan, $gaji_bersih,
                $status_bayar, $tanggal_bayar, $catatan, $id
            ]);
            
            logActivity($pdo, 'Edit Payroll', 'Mengedit gaji ' . $payroll['nama_penerima'] . ' (' . $payroll['tipe_penerima'] . ') periode ' . $payroll['bulan'] . '/' . $payroll['tahun'] . ', Bersih: Rp' . number_format($gaji_bersih, 0, ',', '.') . ' (ID: ' . $id . ')');
            
            $_SESSION['success_message'] = 'Gaji untuk ' . htmlspecialchars($payroll['nama_penerima']) . ' berhasil diperbarui.';
            header("Location: index.php");
            exit();
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

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <h4 class="fw-bold mb-0">Edit Data Gaji</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form action="" method="POST" id="payrollForm">
    <?php echo csrf_field(); ?>
    <div class="row g-4">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Rincian Komponen Gaji</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Read-only metadata info block -->
                    <div class="row g-3 bg-light p-3 rounded mb-4">
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted small d-block">Tipe Penerima</span>
                            <span class="fw-bold text-dark-emphasis text-capitalize"><?php echo $payroll['tipe_penerima'] === 'guru' ? 'Guru (Pendidik)' : 'Karyawan (Staf)'; ?></span>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <span class="text-muted small d-block">Nama Lengkap</span>
                            <span class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($payroll['nama_penerima'] ?? 'Tidak Dikenal'); ?></span>
                            <small class="text-muted d-block" style="font-size: 10px;"><?php echo htmlspecialchars($payroll['jabatan_penerima'] ?? '-'); ?></small>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-muted small d-block">Identitas (NIP/NIK)</span>
                            <span class="small font-monospace"><?php echo htmlspecialchars($payroll['identitas_penerima'] ?? '-'); ?></span>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <span class="text-muted small d-block">Periode</span>
                            <span class="fw-bold text-primary"><?php echo $month_names[$payroll['bulan']]; ?> <?php echo $payroll['tahun']; ?></span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Salary components -->
                        <div class="col-md-4">
                            <label for="gaji_pokok" class="form-label fw-semibold small text-primary">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control fw-bold text-primary" id="gaji_pokok" name="gaji_pokok" value="<?php echo (int)$payroll['gaji_pokok']; ?>" oninput="calculateNet()" required>
                        </div>

                        <div class="col-md-4">
                            <label for="tunjangan" class="form-label fw-semibold small text-success">Total Tunjangan (Rp)</label>
                            <input type="number" step="1" min="0" class="form-control fw-bold text-success" id="tunjangan" name="tunjangan" value="<?php echo (int)$payroll['tunjangan']; ?>" oninput="calculateNet()">
                        </div>

                        <div class="col-md-4">
                            <label for="potongan" class="form-label fw-semibold small text-danger">Total Potongan (Rp)</label>
                            <input type="number" step="1" min="0" class="form-control fw-bold text-danger" id="potongan" name="potongan" value="<?php echo (int)$payroll['potongan']; ?>" oninput="calculateNet()">
                        </div>

                        <!-- Catatan -->
                        <div class="col-12 mt-4">
                            <label for="catatan" class="form-label fw-semibold small">Catatan Pembayaran / Slip</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="2" placeholder="Masukkan rincian tambahan (misal: rincian tunjangan, potongan absensi, dll)"><?php echo htmlspecialchars($payroll['catatan']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Column -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4 bg-light">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 text-center">
                    <h5 class="fw-bold mb-0">Total Gaji Bersih</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <!-- Net pay Display -->
                    <h2 class="fw-bold text-dark-emphasis my-3" id="gaji_bersih_display">Rp <?php echo number_format($payroll['gaji_bersih'], 0, ',', '.'); ?></h2>
                    <input type="hidden" id="gaji_bersih" name="gaji_bersih" value="<?php echo (int)$payroll['gaji_bersih']; ?>">
                    <div class="text-muted small">Pokok + Tunjangan - Potongan</div>
                </div>
            </div>

            <!-- Payment Details Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold mb-0">Status Pembayaran</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="status_bayar" class="form-label fw-semibold small">Status</label>
                        <select class="form-select" id="status_bayar" name="status_bayar" onchange="togglePayDate()" required>
                            <option value="Belum Dibayar" <?php echo $payroll['status_bayar'] === 'Belum Dibayar' ? 'selected' : ''; ?>>Belum Dibayar (Pending)</option>
                            <option value="Dibayar" <?php echo $payroll['status_bayar'] === 'Dibayar' ? 'selected' : ''; ?>>Dibayar (Paid)</option>
                        </select>
                    </div>

                    <div class="mb-3 <?php echo $payroll['status_bayar'] === 'Dibayar' ? '' : 'd-none'; ?>" id="tanggal_bayar_container">
                        <label for="tanggal_bayar" class="form-label fw-semibold small">Tanggal Pembayaran</label>
                        <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar" value="<?php echo $payroll['tanggal_bayar'] ?: date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 mb-4 p-3 bg-light">
                <button type="submit" class="btn btn-primary w-100 fw-bold mb-2"><i class="bi bi-save"></i> Perbarui Data Gaji</button>
                <a href="index.php" class="btn btn-outline-secondary w-100 fw-bold">Batal</a>
            </div>
        </div>
    </div>
</form>

<script>
function calculateNet() {
    const pokok = parseFloat(document.getElementById('gaji_pokok').value) || 0;
    const tunjangan = parseFloat(document.getElementById('tunjangan').value) || 0;
    const potongan = parseFloat(document.getElementById('potongan').value) || 0;
    
    const bersih = pokok + tunjangan - potongan;
    
    document.getElementById('gaji_bersih').value = bersih;
    document.getElementById('gaji_bersih_display').innerText = formatRupiah(bersih);
}

function formatRupiah(angka) {
    if (angka < 0) return '-Rp ' + formatRupiah(Math.abs(angka)).substring(3);
    var reverse = angka.toString().split('').reverse().join(''),
        ribuan = reverse.match(/\d{1,3}/g);
    ribuan = ribuan.join('.').split('').reverse().join('');
    return 'Rp ' + ribuan;
}

function togglePayDate() {
    const status = document.getElementById('status_bayar').value;
    const container = document.getElementById('tanggal_bayar_container');
    
    if (status === 'Dibayar') {
        container.classList.remove('d-none');
    } else {
        container.classList.add('d-none');
    }
}
</script>

<?php include $path_prefix . 'includes/footer.php'; ?>
