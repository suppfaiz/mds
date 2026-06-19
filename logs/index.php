<?php
$path_prefix = '../';
$page_title = 'Audit Log Aktivitas';
$active_menu = 'logs';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Protect the page - Only Super Admin & Kepala Sekolah can access
checkRole(['super_admin', 'kepala_sekolah']);

// Pagination setup
$limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Search query variable
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build SQL query
$query = "SELECT * FROM audit_log WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (username LIKE ? OR aktivitas LIKE ? OR detail LIKE ? OR ip_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Get total count
$count_query = str_replace("SELECT *", "SELECT COUNT(*) AS total", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_rows / $limit);
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal menghitung log: ' . $e->getMessage();
    $total_rows = 0;
    $total_pages = 1;
}

// Order & Paginate
$query .= " ORDER BY tanggal_akses DESC LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat log aktivitas: ' . $e->getMessage();
    $logs = [];
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Audit Log Aktivitas</h4>
        <p class="text-muted mb-0 small">Rekam jejak digital aktivitas pengguna di dalam sistem untuk menjaga keamanan data.</p>
    </div>
</div>

<!-- Search Form Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-12 col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Cari berdasarkan User, Aktivitas, Detail, atau IP Address..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Cari</button>
                <?php if ($search !== ''): ?>
                    <a href="index.php" class="btn btn-light border"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3" style="width: 70px;">No</th>
                        <th class="py-3" style="width: 170px;">Waktu Akses</th>
                        <th class="py-3" style="width: 120px;">Username</th>
                        <th class="py-3" style="width: 150px;">Aktivitas</th>
                        <th class="py-3">Detail Deskripsi</th>
                        <th class="px-4 py-3" style="width: 130px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada log aktivitas tercatat.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($logs as $log): 
                            $badge_class = 'bg-secondary';
                            if (strpos($log['aktivitas'], 'Gagal') !== false || strpos($log['aktivitas'], 'Hapus') !== false) {
                                $badge_class = 'bg-danger';
                            } elseif (strpos($log['aktivitas'], 'Tambah') !== false) {
                                $badge_class = 'bg-success';
                            } elseif (strpos($log['aktivitas'], 'Edit') !== false) {
                                $badge_class = 'bg-warning text-dark';
                            } elseif (strpos($log['aktivitas'], 'Login') !== false) {
                                $badge_class = 'bg-primary';
                            }
                        ?>
                            <tr>
                                <td class="px-4"><?php echo $no++; ?></td>
                                <td><code><?php echo date('d-m-Y H:i:s', strtotime($log['tanggal_akses'])); ?></code></td>
                                <td>
                                    <span class="fw-semibold text-secondary-emphasis">
                                        <?php echo htmlspecialchars($log['username'] ?? 'Sistem'); ?>
                                    </span>
                                    <div class="text-muted" style="font-size: 10px;">ID User: <?php echo htmlspecialchars($log['user_id'] ?? '-'); ?></div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?> font-monospace">
                                        <?php echo htmlspecialchars($log['aktivitas']); ?>
                                    </span>
                                </td>
                                <td class="text-wrap" style="font-size: 13px; max-width: 400px;">
                                    <?php echo htmlspecialchars($log['detail']); ?>
                                </td>
                                <td class="px-4">
                                    <small class="text-muted font-monospace"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination controls -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Menampilkan <strong><?php echo count($logs); ?></strong> dari <strong><?php echo $total_rows; ?></strong> log aktivitas
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm m-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Sebelumnya</a>
                        </li>
                        
                        <?php 
                        // Render pagination numbers (max 5 visible page items)
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
