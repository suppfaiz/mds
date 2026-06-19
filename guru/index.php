<?php
$path_prefix = '../';
$page_title = 'Data Guru';
$active_menu = 'guru';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Protect the page - All logged in users can see this
checkLogin();

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Search query variable
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build SQL query
$query = "SELECT * FROM guru WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (nama LIKE ? OR nip LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) AS total", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_rows / $limit);
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal menghitung jumlah data: ' . $e->getMessage();
    $total_rows = 0;
    $total_pages = 1;
}

// Add LIMIT & OFFSET for pagination
$query .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat data guru: ' . $e->getMessage();
    $teachers = [];
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pusat Data Guru</h4>
        <p class="text-muted mb-0 small">Kelola data kepegawaian guru, jabatan, mata pelajaran, serta berkas sertifikasi.</p>
    </div>
    
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="export_excel.php?search=<?php echo urlencode($search); ?>" class="btn btn-outline-success d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
        </a>
        <?php if (hasPermission(['super_admin', 'operator'])): ?>
            <a href="create.php" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-person-plus"></i> Tambah Guru
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search Form Card -->
<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-12 col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Cari berdasarkan Nama atau NIP..." value="<?php echo htmlspecialchars($search); ?>">
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

<!-- Data Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3" style="width: 70px;">No</th>
                        <th class="py-3" style="width: 80px;">Foto</th>
                        <th class="py-3">NIP</th>
                        <th class="py-3">Nama Lengkap</th>
                        <th class="py-3">Mata Pelajaran</th>
                        <th class="py-3">Jabatan</th>
                        <th class="py-3">Pendidikan</th>
                        <th class="py-3">Nomor HP</th>
                        <th class="px-4 py-3 text-end" style="width: 240px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Data guru tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($teachers as $teacher): 
                        ?>
                            <tr>
                                <td class="px-4"><?php echo $no++; ?></td>
                                <td>
                                    <div class="rounded-circle overflow-hidden bg-light d-flex align-items-center justify-content-center border" style="width: 45px; height: 45px;">
                                        <?php if (!empty($teacher['foto']) && file_exists('../' . $teacher['foto'])): ?>
                                            <img src="../<?php echo htmlspecialchars($teacher['foto']); ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="bi bi-person-badge text-secondary fs-4"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-secondary-emphasis">
                                        <?php echo !empty($teacher['nip']) ? htmlspecialchars($teacher['nip']) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($teacher['nama']); ?></div>
                                    <span class="text-muted small" style="font-size: 11px;"><?php echo htmlspecialchars($teacher['email']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['mata_pelajaran']); ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <?php echo htmlspecialchars($teacher['jabatan']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['pendidikan_terakhir']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['no_hp']); ?></td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="detail.php?id=<?php echo encryptId($teacher['id']); ?>" class="btn btn-sm btn-outline-info" title="Detail Profil & Dokumen">
                                            <i class="bi bi-eye"></i> Profil
                                        </a>
                                        <?php if (hasPermission(['super_admin', 'operator'])): ?>
                                            <a href="edit.php?id=<?php echo encryptId($teacher['id']); ?>" class="btn btn-sm btn-outline-primary" title="Edit Data">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo encryptId($teacher['id']); ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus data guru ini? Semua file dan dokumen yang terkait akan dihapus secara permanen.')" title="Hapus Data">
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
        
        <!-- Pagination controls -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-transparent border-0 py-3 d-flex justify-content-between align-items-center no-print">
                <div class="text-muted small">
                    Menampilkan <strong><?php echo count($teachers); ?></strong> dari <strong><?php echo $total_rows; ?></strong> guru
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm m-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Sebelumnya</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
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
