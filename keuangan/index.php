<?php
$path_prefix = '../';
$page_title = 'Buku Kas Umum';
$active_menu = 'keuangan';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth check: Block Guru
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$role = $_SESSION['role'];
$error = '';
$success = '';

// Retrieve alert messages from session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// 1. Calculate General Statistics
try {
    // Total SPP Income (Lunas)
    $stmt_spp = $pdo->query("SELECT SUM(jumlah_bayar) AS total FROM spp_pembayaran WHERE status_bayar = 'Lunas'");
    $spp_income = (float)($stmt_spp->fetchColumn() ?: 0.0);

    // Total Manual Incomes (Pemasukan)
    $stmt_manual_in = $pdo->query("SELECT SUM(nominal) AS total FROM keuangan_transaksi WHERE tipe = 'Pemasukan'");
    $manual_income = (float)($stmt_manual_in->fetchColumn() ?: 0.0);
    
    $total_income = $spp_income + $manual_income;

    // Total Payroll Expense (Dibayar)
    $stmt_pay = $pdo->query("SELECT SUM(gaji_bersih) AS total FROM payroll WHERE status_bayar = 'Dibayar'");
    $payroll_expense = (float)($stmt_pay->fetchColumn() ?: 0.0);

    // Total Manual Expenses (Pengeluaran)
    $stmt_manual_ex = $pdo->query("SELECT SUM(nominal) AS total FROM keuangan_transaksi WHERE tipe = 'Pengeluaran'");
    $manual_expense = (float)($stmt_manual_ex->fetchColumn() ?: 0.0);

    $total_expense = $payroll_expense + $manual_expense;
    
    $net_balance = $total_income - $total_expense;

} catch (PDOException $e) {
    $error = 'Gagal memuat ringkasan kas: ' . $e->getMessage();
}

// 2. Filters & Paginated Transaction List
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tipe = isset($_GET['tipe']) ? trim($_GET['tipe']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(keterangan LIKE :search OR kategori LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($tipe)) {
    $where_clauses[] = "tipe = :tipe";
    $params['tipe'] = $tipe;
}
if (!empty($kategori)) {
    $where_clauses[] = "kategori = :kategori";
    $params['kategori'] = $kategori;
}
if (!empty($start_date)) {
    $where_clauses[] = "tanggal >= :start_date";
    $params['start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "tanggal <= :end_date";
    $params['end_date'] = $end_date;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Pagination parameters
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$transactions = [];
$total_records = 0;

try {
    // Count total filtered
    $count_sql = "SELECT COUNT(*) FROM keuangan_transaksi $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();

    // Fetch records
    $query_sql = "SELECT * FROM keuangan_transaksi $where_sql ORDER BY tanggal DESC, id DESC LIMIT :limit OFFSET :offset";
    $stmt_query = $pdo->prepare($query_sql);
    
    // Bind integer pagination variables manually
    $stmt_query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_query->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_query->bindValue(':' . $key, $val);
    }
    
    $stmt_query->execute();
    $transactions = $stmt_query->fetchAll();

    // Fetch unique categories for dropdown filter
    $categories = $pdo->query("SELECT DISTINCT kategori FROM keuangan_transaksi ORDER BY kategori ASC")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error = 'Gagal memuat daftar transaksi: ' . $e->getMessage();
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Buku Kas Umum</h4>
    <div class="d-flex gap-2">
        <a href="laporan.php" class="btn btn-outline-primary shadow-sm"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Laba Rugi</a>
        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
            <a href="create.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle"></i> Tambah Transaksi</a>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary Widgets -->
<div class="row g-3 mb-4">
    <!-- Total Pendapatan Card -->
    <div class="col-12 col-md-4">
        <div class="card stat-card border-start border-success border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Penerimaan Kas</span>
                    <h3 class="mt-1 mb-0 fw-bold text-success">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></h3>
                    <small class="text-muted" style="font-size: 10px;">SPP: Rp <?php echo number_format($spp_income, 0, ',', '.'); ?> &bull; Lain: Rp <?php echo number_format($manual_income, 0, ',', '.'); ?></small>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">
                    <i class="bi bi-arrow-down-left-square fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Pengeluaran Card -->
    <div class="col-12 col-md-4">
        <div class="card stat-card border-start border-danger border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Pengeluaran Kas</span>
                    <h3 class="mt-1 mb-0 fw-bold text-danger">Rp <?php echo number_format($total_expense, 0, ',', '.'); ?></h3>
                    <small class="text-muted" style="font-size: 10px;">Payroll: Rp <?php echo number_format($payroll_expense, 0, ',', '.'); ?> &bull; Lain: Rp <?php echo number_format($manual_expense, 0, ',', '.'); ?></small>
                </div>
                <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-2">
                    <i class="bi bi-arrow-up-right-square fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Saldo Kas Card -->
    <div class="col-12 col-md-4">
        <?php $balance_color = $net_balance >= 0 ? 'text-primary' : 'text-danger'; ?>
        <?php $border_color = $net_balance >= 0 ? 'border-primary' : 'border-danger'; ?>
        <?php $bg_badge = $net_balance >= 0 ? 'bg-primary' : 'bg-danger'; ?>
        <div class="card stat-card border-start <?php echo $border_color; ?> border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Saldo Kas Akhir</span>
                    <h3 class="mt-1 mb-0 fw-bold <?php echo $balance_color; ?>">Rp <?php echo number_format($net_balance, 0, ',', '.'); ?></h3>
                    <small class="text-muted" style="font-size: 10px;">Net Surplus / Defisit kas berjalan</small>
                </div>
                <div class="<?php echo $bg_badge; ?> bg-opacity-10 <?php echo $balance_color; ?> rounded-3 p-2">
                    <i class="bi bi-safe fs-3"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <!-- Text Search -->
            <div class="col-12 col-md-3">
                <label for="search" class="form-label small fw-semibold">Cari Deskripsi / Kategori</label>
                <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ketik kata kunci...">
            </div>
            
            <!-- Type Filter -->
            <div class="col-12 col-sm-6 col-md-2">
                <label for="tipe" class="form-label small fw-semibold">Tipe Kas</label>
                <select class="form-select form-select-sm" id="tipe" name="tipe">
                    <option value="">Semua Tipe</option>
                    <option value="Pemasukan" <?php echo $tipe === 'Pemasukan' ? 'selected' : ''; ?>>Pemasukan</option>
                    <option value="Pengeluaran" <?php echo $tipe === 'Pengeluaran' ? 'selected' : ''; ?>>Pengeluaran</option>
                </select>
            </div>

            <!-- Category Filter -->
            <div class="col-12 col-sm-6 col-md-2">
                <label for="kategori" class="form-label small fw-semibold">Kategori</label>
                <select class="form-select form-select-sm" id="kategori" name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $kategori === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date range start -->
            <div class="col-12 col-sm-6 col-md-2">
                <label for="start_date" class="form-label small fw-semibold">Mulai Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>

            <!-- Date range end -->
            <div class="col-12 col-sm-6 col-md-2">
                <label for="end_date" class="form-label small fw-semibold">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>

            <!-- Filter Buttons -->
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold"><i class="bi bi-filter"></i></button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Ledger Card -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex align-items-center justify-content-between">
        <div>
            <h6 class="fw-bold mb-0">Daftar Transaksi Arus Kas</h6>
            <small class="text-muted">Ditemukan <?php echo $total_records; ?> entri laporan</small>
        </div>
        <a href="export_excel.php?search=<?php echo urlencode($search); ?>&tipe=<?php echo urlencode($tipe); ?>&kategori=<?php echo urlencode($kategori); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-sm btn-outline-success fw-semibold">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-wallet2 fs-1 d-block mb-3"></i>
                <h6>Belum ada transaksi kas yang dicatat.</h6>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;" class="text-center">No</th>
                            <th style="width: 130px;">Tanggal</th>
                            <th style="width: 120px;" class="text-center">Tipe</th>
                            <th style="width: 160px;">Kategori</th>
                            <th style="width: 160px;" class="text-end">Nominal</th>
                            <th>Keterangan</th>
                            <th style="width: 120px;" class="text-center">Pencatat</th>
                            <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                                <th style="width: 130px;" class="text-center">Tindakan</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $index => $t): ?>
                            <tr>
                                <td class="text-center text-muted small"><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($t['tanggal'])); ?></td>
                                <td class="text-center">
                                    <?php if ($t['tipe'] === 'Pemasukan'): ?>
                                        <span class="badge bg-success-subtle text-success py-1 px-3" style="font-size: 11px;">
                                            Pemasukan
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger py-1 px-3" style="font-size: 11px;">
                                            Pengeluaran
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($t['kategori']); ?></td>
                                <td class="text-end fw-bold font-monospace text-secondary-emphasis">
                                    Rp <?php echo number_format($t['nominal'], 0, ',', '.'); ?>
                                </td>
                                <td class="small text-muted text-wrap" style="max-width: 250px;">
                                    <?php echo htmlspecialchars($t['keterangan'] ?: '-'); ?>
                                </td>
                                <td class="text-center small"><?php echo htmlspecialchars($t['pencatat']); ?></td>
                                
                                <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Transaksi">
                                                <i class="bi bi-pencil-square" style="font-size: 11px;"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $t['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus transaksi ini?')" title="Hapus Transaksi">
                                                <i class="bi bi-trash" style="font-size: 11px;"></i>
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination Footer -->
<?php if ($total_pages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
        <ul class="pagination pagination-sm mb-0">
            <!-- Prev page -->
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&tipe=<?php echo urlencode($tipe); ?>&kategori=<?php echo urlencode($kategori); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            
            <!-- Pages -->
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&tipe=<?php echo urlencode($tipe); ?>&kategori=<?php echo urlencode($kategori); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <!-- Next page -->
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&tipe=<?php echo urlencode($tipe); ?>&kategori=<?php echo urlencode($kategori); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php include $path_prefix . 'includes/footer.php'; ?>
