<?php
$path_prefix = '../';
$page_title = 'Kelola Akun Wali PMB';
$active_menu = 'pmb_akun';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';
require_once $path_prefix . 'includes/mail.php';

// Auth: Hanya Super Admin, Operator, & Kepala Sekolah
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

// Handler Aksi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role === 'super_admin' || $role === 'operator')) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Validasi keamanan gagal. Silakan muat ulang halaman.';
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $action = $_POST['action'] ?? '';
        
        try {
            // Ambil data akun
            $stmt = $pdo->prepare("SELECT * FROM pmb_akun WHERE id = ?");
            $stmt->execute([$id]);
            $akun = $stmt->fetch();
            
            if (!$akun) {
                $error = 'Akun tidak ditemukan.';
            } else {
                // 1. Aktivasi Langsung (Manual Verify)
                if ($action === 'activate') {
                    $stmt_up = $pdo->prepare("UPDATE pmb_akun SET is_verified = 1, verification_token = NULL WHERE id = ?");
                    $stmt_up->execute([$id]);
                    
                    logActivity($pdo, 'Aktivasi PMB Manual', 'Admin mengaktifkan akun PMB: ' . $akun['email']);
                    $success = 'Akun <strong>' . htmlspecialchars($akun['nama']) . '</strong> (' . htmlspecialchars($akun['email']) . ') berhasil diaktifkan secara langsung!';
                }
                
                // 2. Generate Token Baru
                elseif ($action === 'generate') {
                    $new_otp = (string)rand(100000, 999999);
                    $stmt_up = $pdo->prepare("UPDATE pmb_akun SET verification_token = ?, is_verified = 0 WHERE id = ?");
                    $stmt_up->execute([$new_otp, $id]);
                    
                    logActivity($pdo, 'Generate Token Manual', 'Admin membuat token verifikasi manual untuk: ' . $akun['email']);
                    $success = 'Token baru untuk <strong>' . htmlspecialchars($akun['nama']) . '</strong> berhasil digenerate: <strong class="fs-5 font-monospace text-primary">' . $new_otp . '</strong>. Berikan kode ini kepada pendaftar.';
                }
                
                // 3. Kirim Ulang Email OTP
                elseif ($action === 'send_email') {
                    $otp = $akun['verification_token'];
                    
                    // Jika token kosong (karena sudah terverifikasi), buat token baru dulu
                    if (empty($otp)) {
                        $otp = (string)rand(100000, 999999);
                        $stmt_up = $pdo->prepare("UPDATE pmb_akun SET verification_token = ?, is_verified = 0 WHERE id = ?");
                        $stmt_up->execute([$otp, $id]);
                    }
                    
                    if (sendVerificationEmail($akun['email'], $akun['nama'], $otp)) {
                        logActivity($pdo, 'Resend OTP via Admin', 'Admin mengirim ulang email OTP ke: ' . $akun['email']);
                        $success = 'Email kode verifikasi (' . $otp . ') berhasil dikirim ulang ke <strong>' . htmlspecialchars($akun['email']) . '</strong>!';
                    } else {
                        $error = 'Gagal mengirim email verifikasi ke ' . htmlspecialchars($akun['email']) . '. Cek pengaturan SMTP Gmail di berkas .env Anda.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan basis data: ' . $e->getMessage();
        }
    }
}

// Pencarian dan Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(nama LIKE :search OR email LIKE :search OR no_hp LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if ($status !== '') {
    $where_clauses[] = "is_verified = :status";
    $params['status'] = (int)$status;
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

$accounts = [];
$total_records = 0;

try {
    // Hitung total data terfilter
    $count_sql = "SELECT COUNT(*) FROM pmb_akun $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    
    // Ambil data akun
    $query_sql = "SELECT * FROM pmb_akun $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt_query = $pdo->prepare($query_sql);
    $stmt_query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_query->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_query->bindValue(':' . $key, $val);
    }
    $stmt_query->execute();
    $accounts = $stmt_query->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Gagal memuat data akun: ' . $e->getMessage();
}

$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) $total_pages = 1;

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Kelola Akun Wali Siswa PMB</h4>
    <div class="text-muted small">Total Akun: <strong><?php echo $total_records; ?></strong></div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show py-3 border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show py-3 border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filter & Search Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" id="search" name="search" placeholder="Cari berdasarkan Nama, Email, atau No. HP..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-filter"></i></span>
                    <select class="form-select bg-light border-start-0" name="status">
                        <option value="">Semua Status Verifikasi</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Terverifikasi (Aktif)</option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Pending Verifikasi (Belum Aktif)</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-sliders"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-body p-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-exclamation fs-1 d-block mb-2"></i>
                <p class="mb-0">Tidak ada data akun wali murid PMB yang cocok.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="py-3 ps-4" style="width: 50px;">ID</th>
                            <th class="py-3">Wali Murid</th>
                            <th class="py-3">Kontak</th>
                            <th class="py-3 text-center">Status Verifikasi</th>
                            <th class="py-3 text-center">Token OTP Aktif</th>
                            <th class="py-3 text-center" style="width: 300px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td class="ps-4 text-muted fw-semibold"><?php echo $a['id']; ?></td>
                                <td>
                                    <div class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($a['nama']); ?></div>
                                    <div class="text-muted small" style="font-size: 11px;">Daftar pada: <?php echo date('d-m-Y H:i', strtotime($a['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1"><i class="bi bi-envelope text-muted"></i> <?php echo htmlspecialchars($a['email']); ?></div>
                                    <div class="d-flex align-items-center gap-1 font-monospace" style="font-size: 11.5px;"><i class="bi bi-whatsapp text-success"></i> <?php echo htmlspecialchars($a['no_hp']); ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$a['is_verified'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success py-1 px-3" style="font-size: 11px; border-radius: 20px;">
                                            <i class="bi bi-patch-check-fill me-1"></i> Terverifikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning py-1 px-3" style="font-size: 11px; border-radius: 20px;">
                                            <i class="bi bi-hourglass-split me-1"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($a['verification_token'])): ?>
                                        <span class="font-monospace fw-bold text-primary bg-light border px-2 py-1" style="font-size: 14px; border-radius: 6px; letter-spacing: 1px;">
                                            <?php echo htmlspecialchars($a['verification_token']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                                            <!-- Form Aksi -->
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin memverifikasi akun ini secara manual?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-sm btn-outline-success py-1" <?php echo (int)$a['is_verified'] === 1 ? 'disabled' : ''; ?> title="Aktivasi Akun Langsung">
                                                    <i class="bi bi-patch-check"></i> Aktivasi
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <input type="hidden" name="action" value="generate">
                                                <button type="submit" class="btn btn-sm btn-outline-primary py-1" title="Generate Token Baru secara Manual">
                                                    <i class="bi bi-arrow-repeat"></i> Token Baru
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Kirim ulang email OTP verifikasi ke alamat ini?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                                <input type="hidden" name="action" value="send_email">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-1" title="Kirim Ulang Email Verifikasi">
                                                    <i class="bi bi-envelope-at"></i> Kirim Ulang
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">No Access</span>
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
