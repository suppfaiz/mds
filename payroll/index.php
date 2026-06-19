<?php
$path_prefix = '../';
$page_title = 'Payroll & Gaji';
$active_menu = 'payroll';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

checkLogin();

$role = $_SESSION['role'];
$is_guru = ($role === 'guru');

// Find teacher ID if logged in as guru
$teacher_id = 0;
if ($is_guru) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM guru WHERE LOWER(REPLACE(nama, ' ', '')) = LOWER(REPLACE(?, ' ', ''))");
        $stmt->execute([$_SESSION['nama_lengkap']]);
        $teacher = $stmt->fetch();
        $teacher_id = $teacher ? $teacher['id'] : 0;
    } catch (PDOException $e) {
        $teacher_id = 0;
    }
}

// Retrieve filters from GET
$filter_month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$filter_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$filter_type = isset($_GET['tipe_penerima']) ? $_GET['tipe_penerima'] : '';
$filter_status = isset($_GET['status_bayar']) ? $_GET['status_bayar'] : '';

// Build query
$query = "SELECT p.*, 
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
          WHERE 1=1";
$params = [];

if ($is_guru) {
    // Restrict teachers to their own slips
    $query .= " AND p.tipe_penerima = 'guru' AND p.penerima_id = ?";
    $params[] = $teacher_id;
} else {
    // Apply normal filters for admins/ops/kepsek
    if ($filter_month > 0) {
        $query .= " AND p.bulan = ?";
        $params[] = $filter_month;
    }
    if ($filter_year > 0) {
        $query .= " AND p.tahun = ?";
        $params[] = $filter_year;
    }
    if ($filter_type !== '') {
        $query .= " AND p.tipe_penerima = ?";
        $params[] = $filter_type;
    }
    if ($filter_status !== '') {
        $query .= " AND p.status_bayar = ?";
        $params[] = $filter_status;
    }
}

$query .= " ORDER BY p.tahun DESC, p.bulan DESC, nama_penerima ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payrolls = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat data payroll: ' . $e->getMessage();
    $payrolls = [];
}

// Calculate summary stats (only for admins/operators/kepsek)
$stat_total = 0;
$stat_paid = 0;
$stat_unpaid = 0;
foreach ($payrolls as $p) {
    $stat_total += $p['gaji_bersih'];
    if ($p['status_bayar'] === 'Dibayar') {
        $stat_paid += $p['gaji_bersih'];
    } else {
        $stat_unpaid += $p['gaji_bersih'];
    }
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><?php echo $is_guru ? 'Slip Gaji Saya' : 'Manajemen Payroll & Gaji'; ?></h4>
        <p class="text-muted mb-0 small"><?php echo $is_guru ? 'Lihat riwayat dan unduh slip gaji bulanan Anda.' : 'Kelola penggajian bulanan, cetak slip gaji, dan monitoring pengeluaran gaji sekolah.'; ?></p>
    </div>
    
    <?php if (!$is_guru): ?>
        <div class="d-flex flex-wrap gap-2 no-print">
            <a href="export_excel.php?bulan=<?php echo $filter_month; ?>&tahun=<?php echo $filter_year; ?>&tipe_penerima=<?php echo urlencode($filter_type); ?>&status_bayar=<?php echo urlencode($filter_status); ?>" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Laporan Excel
            </a>
            <?php if (hasPermission(['super_admin', 'operator'])): ?>
                <a href="create.php" class="btn btn-primary d-flex align-items-center gap-2">
                    <i class="bi bi-wallet2"></i> Tambah / Input Gaji
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$is_guru): ?>
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body py-3 px-4">
                    <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Total Anggaran Gaji</span>
                    <h5 class="fw-bold m-0 text-primary">Rp <?php echo number_format($stat_total, 0, ',', '.'); ?></h5>
                    <small class="text-muted" style="font-size: 11px;">Periode Terfilter</small>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body py-3 px-4">
                    <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Gaji Telah Dibayar</span>
                    <h5 class="fw-bold m-0 text-success">Rp <?php echo number_format($stat_paid, 0, ',', '.'); ?></h5>
                    <small class="text-muted" style="font-size: 11px;"><?php echo count(array_filter($payrolls, fn($x) => $x['status_bayar'] === 'Dibayar')); ?> transaksi</small>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body py-3 px-4">
                    <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Gaji Belum Dibayar</span>
                    <h5 class="fw-bold m-0 text-warning">Rp <?php echo number_format($stat_unpaid, 0, ',', '.'); ?></h5>
                    <small class="text-muted" style="font-size: 11px;"><?php echo count(array_filter($payrolls, fn($x) => $x['status_bayar'] === 'Belum Dibayar')); ?> pending</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-info border-4">
                <div class="card-body py-3 px-4">
                    <span class="text-muted small text-uppercase fw-semibold d-block mb-1">Total Penerima Gaji</span>
                    <h5 class="fw-bold m-0 text-info"><?php echo count($payrolls); ?> Orang</h5>
                    <small class="text-muted" style="font-size: 11px;">Dalam list terfilter</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label for="bulan" class="form-label small fw-semibold">Bulan</label>
                    <select class="form-select" id="bulan" name="bulan">
                        <?php foreach ($month_names as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $filter_month === $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-sm-6 col-md-2">
                    <label for="tahun" class="form-label small fw-semibold">Tahun</label>
                    <select class="form-select" id="tahun" name="tahun">
                        <?php for ($y = date('Y')-2; $y <= date('Y')+1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $filter_year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-12 col-sm-6 col-md-3">
                    <label for="tipe_penerima" class="form-label small fw-semibold">Tipe Penerima</label>
                    <select class="form-select" id="tipe_penerima" name="tipe_penerima">
                        <option value="">Semua Tipe...</option>
                        <option value="guru" <?php echo $filter_type === 'guru' ? 'selected' : ''; ?>>Guru (Pengajar)</option>
                        <option value="karyawan" <?php echo $filter_type === 'karyawan' ? 'selected' : ''; ?>>Karyawan (Staf)</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <label for="status_bayar" class="form-label small fw-semibold">Status Bayar</label>
                    <select class="form-select" id="status_bayar" name="status_bayar">
                        <option value="">Semua Status...</option>
                        <option value="Dibayar" <?php echo $filter_status === 'Dibayar' ? 'selected' : ''; ?>>Dibayar</option>
                        <option value="Belum Dibayar" <?php echo $filter_status === 'Belum Dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                    <?php if (isset($_GET['bulan']) || isset($_GET['tahun']) || isset($_GET['tipe_penerima']) || isset($_GET['status_bayar'])): ?>
                        <a href="index.php" class="btn btn-light border"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Payroll Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3" style="width: 70px;">No</th>
                        <th class="py-3">Penerima</th>
                        <th class="py-3">Identitas (NIP/NIK)</th>
                        <th class="py-3">Periode</th>
                        <th class="py-3 text-end">Gaji Pokok</th>
                        <th class="py-3 text-end">Tunjangan</th>
                        <th class="py-3 text-end">Potongan</th>
                        <th class="py-3 text-end">Gaji Bersih</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-end" style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payrolls)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <?php echo $is_guru ? 'Anda belum memiliki riwayat slip gaji.' : 'Data payroll tidak ditemukan untuk filter saat ini.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($payrolls as $payroll): 
                        ?>
                            <tr>
                                <td class="px-4"><?php echo $no++; ?></td>
                                <td>
                                    <div class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($payroll['nama_penerima'] ?? 'Tidak Dikenal'); ?></div>
                                    <span class="badge bg-light text-muted border" style="font-size: 10px;">
                                        <?php echo $payroll['tipe_penerima'] === 'guru' ? 'Guru' : 'Karyawan'; ?> - <?php echo htmlspecialchars($payroll['jabatan_penerima'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted font-monospace"><?php echo htmlspecialchars($payroll['identitas_penerima'] ?? '-'); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo $month_names[$payroll['bulan']]; ?> <?php echo $payroll['tahun']; ?></strong>
                                </td>
                                <td class="text-end">Rp <?php echo number_format($payroll['gaji_pokok'], 0, ',', '.'); ?></td>
                                <td class="text-end text-success">+Rp <?php echo number_format($payroll['tunjangan'], 0, ',', '.'); ?></td>
                                <td class="text-end text-danger">-Rp <?php echo number_format($payroll['potongan'], 0, ',', '.'); ?></td>
                                <td class="text-end fw-bold text-dark-emphasis">Rp <?php echo number_format($payroll['gaji_bersih'], 0, ',', '.'); ?></td>
                                <td class="text-center">
                                    <?php if ($payroll['status_bayar'] === 'Dibayar'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Paid
                                        </span>
                                        <div class="text-muted small" style="font-size: 10px; margin-top: 2px;">
                                            <?php echo date('d/m/Y', strtotime($payroll['tanggal_bayar'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-clock-history me-1"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="print.php?id=<?php echo $payroll['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Slip Gaji">
                                            <i class="bi bi-printer"></i> Slip
                                        </a>
                                        <?php if (hasPermission(['super_admin', 'operator'])): ?>
                                            <a href="edit.php?id=<?php echo $payroll['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Data Gaji">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $payroll['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus data payroll ini?')" title="Hapus Data Gaji">
                                                <i class="bi bi-trash"></i>
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

<?php include $path_prefix . 'includes/footer.php'; ?>
