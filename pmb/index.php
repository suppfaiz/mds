<?php
$path_prefix = '../';
$page_title = 'Data Pendaftar PMB';
$active_menu = 'pmb';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth: Block Guru
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$role = $_SESSION['role'];
$error = '';
$success = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(nama LIKE :search OR no_pendaftaran LIKE :search OR asal_sekolah LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($status)) {
    $where_clauses[] = "status = :status";
    $params['status'] = $status;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$applicants = [];
$total_records = 0;

try {
    // Count total filtered
    $count_sql = "SELECT COUNT(*) FROM pmb_pendaftar $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();

    // Fetch records
    $query_sql = "SELECT * FROM pmb_pendaftar $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt_query = $pdo->prepare($query_sql);
    
    $stmt_query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_query->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_query->bindValue(':' . $key, $val);
    }
    
    $stmt_query->execute();
    $applicants = $stmt_query->fetchAll();

    // Counts for stats badges at header
    $stmt_pending = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar WHERE status = 'Pending'");
    $cnt_pending = $stmt_pending->fetchColumn();
    
    $stmt_diterima = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar WHERE status = 'Diterima'");
    $cnt_diterima = $stmt_diterima->fetchColumn();

    $stmt_total = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar");
    $cnt_total = $stmt_total->fetchColumn();

    // Fetch school settings for schedule/quota blocks
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();

} catch (PDOException $e) {
    $error = 'Gagal memuat data pendaftar: ' . $e->getMessage();
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Penerimaan Murid Baru (PMB)</h4>
    <div class="d-flex gap-2">
        <a href="export_excel.php?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="btn btn-outline-success shadow-sm btn-sm fw-semibold"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
            <a href="create.php" class="btn btn-success shadow-sm btn-sm fw-semibold"><i class="bi bi-plus-circle-fill"></i> Tambah Pendaftar Offline</a>
        <?php endif; ?>
        <a href="daftar.php" target="_blank" class="btn btn-primary shadow-sm btn-sm fw-semibold"><i class="bi bi-link-45deg"></i> Buka Form Publik</a>
    </div>
</div>

<!-- Settings Status Summary Banner -->
<?php if ($settings): ?>
    <?php 
    $pmb_active = $settings['pmb_status'] === 'Buka';
    $today = date('Y-m-d');
    $is_early = (!empty($settings['pmb_mulai']) && $today < $settings['pmb_mulai']);
    $is_late = (!empty($settings['pmb_selesai']) && $today > $settings['pmb_selesai']);
    $is_quota_full = ($cnt_total >= ($settings['pmb_kuota'] ?? 100));
    $registration_blocked = !$pmb_active || $is_early || $is_late || $is_quota_full;
    ?>
    <div class="alert <?php echo $registration_blocked ? 'alert-warning' : 'alert-success'; ?> py-2 px-3 mb-4 shadow-sm border-0 d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-radius: 12px; font-size: 13px;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi <?php echo $registration_blocked ? 'bi-exclamation-triangle-fill text-warning' : 'bi-check-circle-fill text-success'; ?> fs-5"></i>
            <div>
                <strong class="text-dark-emphasis">Status Portal PMB:</strong> 
                <?php if (!$pmb_active): ?>
                    <span class="badge bg-danger rounded-pill">Tutup</span>
                <?php elseif ($is_early): ?>
                    <span class="badge bg-warning text-dark rounded-pill">Menunggu Jadwal</span> (Mulai: <?php echo date('d-m-Y', strtotime($settings['pmb_mulai'])); ?>)
                <?php elseif ($is_late): ?>
                    <span class="badge bg-danger rounded-pill">Jadwal Berakhir</span> (Selesai: <?php echo date('d-m-Y', strtotime($settings['pmb_selesai'])); ?>)
                <?php elseif ($is_quota_full): ?>
                    <span class="badge bg-danger rounded-pill">Kuota Penuh</span> (Terisi: <?php echo $cnt_total; ?>/<?php echo $settings['pmb_kuota']; ?>)
                <?php else: ?>
                    <span class="badge bg-success rounded-pill">Aktif / Buka</span>
                    <?php if (!empty($settings['pmb_selesai'])): ?>
                        s/d <?php echo date('d-m-Y', strtotime($settings['pmb_selesai'])); ?>
                    <?php endif; ?>
                    (Kuota: <?php echo $cnt_total; ?>/<?php echo $settings['pmb_kuota']; ?>)
                <?php endif; ?>
            </div>
        </div>
        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
            <a href="pengaturan.php" class="btn btn-xs btn-outline-primary py-1 px-3 rounded-pill fw-bold" style="font-size: 11px;"><i class="bi bi-gear-fill"></i> Atur Jadwal & Kuota</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Quick Stat Badges -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="card shadow-sm border-0 bg-light p-3 text-center">
            <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 11px;">Total Pendaftar</span>
            <h4 class="fw-bold m-0"><?php echo $cnt_total; ?> Calon Siswa</h4>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="card shadow-sm border-0 bg-warning bg-opacity-10 p-3 text-center border-start border-warning border-3">
            <span class="text-warning-emphasis small fw-semibold text-uppercase d-block mb-1" style="font-size: 11px;">Menunggu Verifikasi</span>
            <h4 class="fw-bold m-0 text-warning-emphasis"><?php echo $cnt_pending; ?> Calon Siswa</h4>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="card shadow-sm border-0 bg-success bg-opacity-10 p-3 text-center border-start border-success border-3">
            <span class="text-success-emphasis small fw-semibold text-uppercase d-block mb-1" style="font-size: 11px;">Telah Diterima</span>
            <h4 class="fw-bold m-0 text-success"><?php echo $cnt_diterima; ?> Calon Siswa</h4>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <!-- Keyword search -->
            <div class="col-12 col-md-6">
                <label for="search" class="form-label small fw-semibold">Cari Nama / No Pendaftaran / Sekolah Asal</label>
                <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Masukkan kata kunci pencarian...">
            </div>

            <!-- Status filter -->
            <div class="col-12 col-md-4">
                <label for="status" class="form-label small fw-semibold">Status Pendaftaran</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending (Menunggu Verifikasi)</option>
                    <option value="Diterima" <?php echo $status === 'Diterima' ? 'selected' : ''; ?>>Diterima (Lulus Seleksi)</option>
                    <option value="Ditolak" <?php echo $status === 'Ditolak' ? 'selected' : ''; ?>>Ditolak (Gugur)</option>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold"><i class="bi bi-filter"></i> Saring</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Applicants Ledger Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($applicants)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-people fs-1 d-block mb-3"></i>
                <h6>Tidak ada calon pendaftar yang ditemukan.</h6>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;" class="text-center">No</th>
                            <th style="width: 140px;">No. Registrasi</th>
                            <th>Nama Lengkap</th>
                            <th style="width: 100px;" class="text-center">Gender</th>
                            <th>Asal Sekolah</th>
                            <th style="width: 140px;">No. Kontak Ortu</th>
                            <th style="width: 130px;" class="text-center">Status</th>
                            <th style="width: 130px;" class="text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicants as $index => $a): ?>
                            <tr>
                                <td class="text-center text-muted small"><?php echo $offset + $index + 1; ?></td>
                                <td class="fw-bold font-monospace text-primary" style="font-size: 12.5px;">
                                    <?php echo htmlspecialchars($a['no_pendaftaran']); ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($a['nama']); ?></div>
                                    <div class="text-muted small" style="font-size: 10.5px;">Daftar: <?php echo date('d-m-Y H:i', strtotime($a['created_at'])); ?></div>
                                </td>
                                <td class="text-center small">
                                    <?php echo $a['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?>
                                </td>
                                <td class="small fw-medium text-secondary-emphasis"><?php echo htmlspecialchars($a['asal_sekolah']); ?></td>
                                <td class="font-monospace text-muted small"><?php echo htmlspecialchars($a['no_hp']); ?></td>
                                <td class="text-center">
                                    <?php if ($a['status'] === 'Diterima'): ?>
                                        <span class="badge bg-success-subtle text-success py-1 px-3" style="font-size: 10.5px; border-radius: 20px;">
                                            Diterima
                                        </span>
                                    <?php elseif ($a['status'] === 'Ditolak'): ?>
                                        <span class="badge bg-danger-subtle text-danger py-1 px-3" style="font-size: 10.5px; border-radius: 20px;">
                                            Ditolak
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis py-1 px-3" style="font-size: 10.5px; border-radius: 20px;">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="detail.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Lihat Profil Detail">
                                            <i class="bi bi-file-earmark-person" style="font-size: 11px;"></i> Detail
                                        </a>
                                        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                                            <a href="delete.php?id=<?php echo $a['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus data calon pendaftar ini?')" title="Hapus Berkas PMB">
                                                <i class="bi bi-trash" style="font-size: 11px;"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php include $path_prefix . 'includes/footer.php'; ?>
