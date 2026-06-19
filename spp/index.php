<?php
$path_prefix = '../';
$page_title = 'Keuangan SPP';
$active_menu = 'spp';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkLogin();

$role = $_SESSION['role'];
$is_admin_or_op = hasPermission(['super_admin', 'operator']);

// Filters
$search = trim($_GET['search'] ?? '');
$kelas_id = $_GET['kelas_id'] ?? '';
$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');
$status = $_GET['status'] ?? '';

// Build Query
$query = "
    SELECT s.id AS spp_id, s.bulan, s.tahun, s.jumlah_bayar, s.tanggal_bayar, s.status_bayar, s.penerima_oleh, s.catatan, s.invoice_token,
           sw.nama AS nama_siswa, sw.nis, sw.nisn, sw.no_hp, sw.no_hp_ortu, k.nama_kelas, k.tarif_spp
    FROM spp_pembayaran s
    JOIN siswa sw ON s.siswa_id = sw.id
    LEFT JOIN kelas k ON sw.kelas_id = k.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (sw.nama LIKE ? OR sw.nis LIKE ? OR sw.nisn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kelas_id)) {
    $query .= " AND sw.kelas_id = ?";
    $params[] = (int)$kelas_id;
}

if (!empty($bulan)) {
    $query .= " AND s.bulan = ?";
    $params[] = (int)$bulan;
}

if (!empty($tahun)) {
    $query .= " AND s.tahun = ?";
    $params[] = (int)$tahun;
}

if (!empty($status)) {
    $query .= " AND s.status_bayar = ?";
    $params[] = $status;
}

$query .= " ORDER BY s.tanggal_bayar DESC, s.id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Fetch classes for filter
    $stmt_kelas = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
    $classes = $stmt_kelas->fetchAll();

    // Stats calculations
    // 1. Total SPP collected this month
    $curr_month = (int)date('m');
    $curr_year = (int)date('Y');
    $stmt_stats = $pdo->prepare("SELECT SUM(jumlah_bayar) AS total FROM spp_pembayaran WHERE status_bayar = 'Lunas'");
    $stmt_stats->execute();
    $total_all_revenue = $stmt_stats->fetch()['total'] ?? 0;

    $stmt_stats_month = $pdo->prepare("SELECT SUM(jumlah_bayar) AS total FROM spp_pembayaran WHERE bulan = ? AND tahun = ? AND status_bayar = 'Lunas'");
    $stmt_stats_month->execute([$curr_month, $curr_year]);
    $total_monthly_revenue = $stmt_stats_month->fetch()['total'] ?? 0;

    // 2. Count of payments this month
    $stmt_count_month = $pdo->prepare("SELECT COUNT(*) AS total FROM spp_pembayaran WHERE bulan = ? AND tahun = ?");
    $stmt_count_month->execute([$curr_month, $curr_year]);
    $count_monthly_transactions = $stmt_count_month->fetch()['total'] ?? 0;

    // 3. Fetch settings for WhatsApp bank details
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat data SPP: ' . $e->getMessage();
    $payments = [];
    $classes = [];
    $total_all_revenue = 0;
    $total_monthly_revenue = 0;
    $count_monthly_transactions = 0;
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include $path_prefix . 'includes/header.php';
?>

<!-- Info Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success py-2 mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<!-- Stats Widgets -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card stat-card border-start border-primary border-4 shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Penerimaan SPP</span>
                    <h4 class="mt-1 mb-0 fw-bold text-primary">Rp <?php echo number_format($total_all_revenue, 0, ',', '.'); ?></h4>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                    <i class="bi bi-bank fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-4">
        <div class="card stat-card border-start border-success border-4 shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Penerimaan Bulan Ini (<?php echo $month_names[$curr_month] . ' ' . $curr_year; ?>)</span>
                    <h4 class="mt-1 mb-0 fw-bold text-success">Rp <?php echo number_format($total_monthly_revenue, 0, ',', '.'); ?></h4>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card stat-card border-start border-info border-4 shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Transaksi Bulan Ini</span>
                    <h4 class="mt-1 mb-0 fw-bold text-info"><?php echo number_format($count_monthly_transactions); ?> Transaksi</h4>
                </div>
                <div class="bg-info bg-opacity-10 text-info rounded-3 p-2">
                    <i class="bi bi-arrow-left-right fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <h5 class="fw-bold mb-0">Pembayaran Uang SPP</h5>
        <div class="d-flex gap-2">
            <?php if ($is_admin_or_op): ?>
                <a href="create.php" class="btn btn-primary btn-sm fw-bold"><i class="bi bi-plus-circle me-1"></i> Input Pembayaran SPP</a>
            <?php endif; ?>
            <a href="export_excel.php?search=<?php echo urlencode($search); ?>&kelas_id=<?php echo urlencode($kelas_id); ?>&bulan=<?php echo urlencode($bulan); ?>&tahun=<?php echo urlencode($tahun); ?>&status=<?php echo urlencode($status); ?>" class="btn btn-success btn-sm fw-bold"><i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel</a>
        </div>
    </div>
    
    <div class="card-body p-4">
        <!-- Filter Form -->
        <form method="GET" action="" class="row g-3 mb-4 bg-light p-3 rounded border">
            <div class="col-12 col-md-3">
                <label for="search" class="form-label small fw-semibold">Nama / NIS / NISN</label>
                <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Cari nama atau nomor..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-12 col-md-2">
                <label for="kelas_id" class="form-label small fw-semibold">Kelas</label>
                <select class="form-select form-select-sm" id="kelas_id" name="kelas_id">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $kelas_id == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-2">
                <label for="bulan" class="form-label small fw-semibold">Bulan SPP</label>
                <select class="form-select form-select-sm" id="bulan" name="bulan">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($month_names as $m_num => $m_name): ?>
                        <option value="<?php echo $m_num; ?>" <?php echo $bulan == $m_num ? 'selected' : ''; ?>>
                            <?php echo $m_name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label for="tahun" class="form-label small fw-semibold">Tahun SPP</label>
                <input type="number" class="form-control form-control-sm" id="tahun" name="tahun" value="<?php echo htmlspecialchars($tahun); ?>" placeholder="Contoh: 2026">
            </div>

            <div class="col-12 col-md-2">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="Lunas" <?php echo $status === 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                    <option value="Belum Lunas" <?php echo $status === 'Belum Lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                </select>
            </div>

            <div class="col-12 col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary btn-sm w-100 fw-bold"><i class="bi bi-filter"></i></button>
            </div>
        </form>

        <!-- Listing Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>NIS/NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Periode SPP</th>
                        <th>Jumlah Bayar</th>
                        <th>Tanggal Bayar</th>
                        <th>Status</th>
                        <th>Penerima</th>
                        <th class="text-end" style="width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">Tidak ditemukan data pembayaran SPP.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($payments as $p): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <span class="font-monospace small"><?php echo htmlspecialchars($p['nis']); ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($p['nama_siswa']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                        <?php echo htmlspecialchars($p['nama_kelas'] ?? 'Belum Diatur'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo $month_names[$p['bulan']] . ' ' . $p['tahun']; ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary">Rp <?php echo number_format($p['jumlah_bayar'], 0, ',', '.'); ?></span>
                                </td>
                                <td>
                                    <span class="small text-muted"><?php echo date('d/m/Y', strtotime($p['tanggal_bayar'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($p['status_bayar'] === 'Lunas'): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> Lunas</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-clock"></i> Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted"><?php echo htmlspecialchars($p['penerima_oleh'] ?? '-'); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php 
                                        // Build WA Link
                                        $phone = $p['no_hp_ortu'] ?: $p['no_hp'];
                                        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
                                        if (strpos($phone_clean, '0') === 0) {
                                            $phone_clean = '62' . substr($phone_clean, 1);
                                        }

                                        // Protocol & Host
                                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                        $host = $_SERVER['HTTP_HOST'];
                                        // Determine paths dynamically to remain clean
                                        $invoice_url = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/invoice.php?token=' . ($p['invoice_token'] ?? '');

                                        $wa_text = "Halo Bapak/Ibu Orang Tua/Wali dari " . $p['nama_siswa'] . ".\n\nBerikut adalah info tagihan SPP untuk periode " . $month_names[$p['bulan']] . " " . $p['tahun'] . " sebesar *Rp " . number_format($p['jumlah_bayar'], 0, ',', '.') . "*.\n\nPembayaran dapat ditransfer ke rekening:\nBank: " . ($settings['nama_bank'] ?? '-') . "\nNomor Rekening: " . ($settings['nomor_rekening'] ?? '-') . "\na.n. " . ($settings['nama_rekening'] ?? '-') . "\n\nDetail tagihan online:\n" . $invoice_url;

                                        $wa_link = "https://api.whatsapp.com/send?phone=" . urlencode($phone_clean) . "&text=" . urlencode($wa_text);
                                        ?>
                                        <a href="<?php echo $wa_link; ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Kirim WA Orang Tua">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                        <a href="print.php?id=<?php echo encryptId($p['spp_id']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Kuitansi">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if ($is_admin_or_op): ?>
                                            <a href="edit.php?id=<?php echo encryptId($p['spp_id']); ?>" class="btn btn-sm btn-outline-warning text-dark border-warning" title="Edit Transaksi">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo encryptId($p['spp_id']); ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan pembayaran SPP ini?')" title="Hapus Transaksi">
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
