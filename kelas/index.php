<?php
$path_prefix = '../';
$page_title = 'Manajemen Kelas';
$active_menu = 'kelas';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkLogin();

$error = '';
$success = '';
$is_admin_or_op = hasPermission(['super_admin', 'operator']);

// 1. ADD CLASS HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
        header("Location: index.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!$is_admin_or_op) {
        $_SESSION['error_message'] = 'Anda tidak memiliki hak akses untuk menambah kelas.';
        header("Location: index.php");
        exit();
    }
    
    $nama_kelas = trim($_POST['nama_kelas']);
    $tarif_spp = isset($_POST['tarif_spp']) ? (float)$_POST['tarif_spp'] : 500000.00;
    
    if (empty($nama_kelas)) {
        $error = 'Nama kelas tidak boleh kosong.';
    } else {
        try {
            // Check unique
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ?");
            $stmt->execute([$nama_kelas]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Nama kelas sudah terdaftar.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tarif_spp) VALUES (?, ?)");
                $stmt->execute([$nama_kelas, $tarif_spp]);
                
                logActivity($pdo, 'Tambah Kelas', 'Menambahkan kelas baru: ' . $nama_kelas . ' dengan tarif SPP Rp ' . number_format($tarif_spp, 0, ',', '.'));
                $_SESSION['success_message'] = 'Kelas ' . htmlspecialchars($nama_kelas) . ' berhasil ditambahkan.';
                header("Location: index.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

// 2. EDIT CLASS HANDLER
$edit_class = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_class = $stmt->fetch();
        if (!$edit_class) {
            $_SESSION['error_message'] = 'Kelas yang akan diedit tidak ditemukan.';
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Kesalahan database: ' . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!$is_admin_or_op) {
        $_SESSION['error_message'] = 'Anda tidak memiliki hak akses untuk mengedit kelas.';
        header("Location: index.php");
        exit();
    }
    
    $id = (int)$_POST['id'];
    $nama_kelas = trim($_POST['nama_kelas']);
    $tarif_spp = isset($_POST['tarif_spp']) ? (float)$_POST['tarif_spp'] : 500000.00;
    
    if (empty($nama_kelas)) {
        $error = 'Nama kelas tidak boleh kosong.';
    } else {
        try {
            // Check unique to others
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ? AND id != ?");
            $stmt->execute([$nama_kelas, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Nama kelas sudah terdaftar untuk kelas lain.';
            } else {
                $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas = ?, tarif_spp = ? WHERE id = ?");
                $stmt->execute([$nama_kelas, $tarif_spp, $id]);
                
                logActivity($pdo, 'Edit Kelas', 'Mengubah nama kelas ID ' . $id . ' menjadi: ' . $nama_kelas . ' (Tarif SPP: Rp ' . number_format($tarif_spp, 0, ',', '.') . ')');
                $_SESSION['success_message'] = 'Nama kelas berhasil diubah menjadi ' . htmlspecialchars($nama_kelas) . '.';
                header("Location: index.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Gagal mengupdate data: ' . $e->getMessage();
        }
    }
}

// 3. FETCH ALL CLASSES
try {
    $stmt = $pdo->query("
        SELECT k.id, k.nama_kelas, k.tarif_spp, COUNT(s.id) AS total_siswa 
        FROM kelas k 
        LEFT JOIN siswa s ON k.id = s.kelas_id 
        GROUP BY k.id 
        ORDER BY k.nama_kelas ASC
    ");
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal mengambil data kelas: ' . $e->getMessage();
    $classes = [];
}

include $path_prefix . 'includes/header.php';
?>

<div class="row g-4">
    <!-- Form Side (Only visible to Admin & Operator) -->
    <div class="col-12 col-lg-4">
        <?php if ($is_admin_or_op): ?>
            <?php if ($edit_class): ?>
                <!-- Card Edit Kelas -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold mb-0 text-warning-emphasis"><i class="bi bi-pencil-square"></i> Edit Kelas</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger py-2" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo $edit_class['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="nama_kelas" class="form-label fw-semibold small">Nama Kelas</label>
                                <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" value="<?php echo htmlspecialchars($edit_class['nama_kelas']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tarif_spp" class="form-label fw-semibold small">Tarif SPP Bulanan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="tarif_spp" name="tarif_spp" value="<?php echo (int)$edit_class['tarif_spp']; ?>" min="0" required>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning text-dark fw-semibold flex-grow-1">
                                    <i class="bi bi-save"></i> Perbarui
                                </button>
                                <a href="index.php" class="btn btn-light border">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Card Tambah Kelas -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold mb-0 text-primary-emphasis"><i class="bi bi-plus-circle"></i> Tambah Kelas Baru</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger py-2" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="nama_kelas" class="form-label fw-semibold small">Nama Kelas</label>
                                <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" placeholder="Contoh: Kelas X-C, Kelas XII-Bahasa" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tarif_spp" class="form-label fw-semibold small">Tarif SPP Bulanan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="tarif_spp" name="tarif_spp" value="500000" min="0" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                <i class="bi bi-plus"></i> Simpan Kelas
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Card read-only warning for others -->
            <div class="card shadow-sm border-0 bg-light p-3">
                <div class="card-body text-center text-muted">
                    <i class="bi bi-shield-lock fs-2 mb-2"></i>
                    <h6 class="fw-bold">Akses Terbatas</h6>
                    <p class="small m-0">Hanya Super Admin dan Operator yang dapat menambah atau mengubah pilihan kelas.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Table List Side -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Daftar Pilihan Kelas</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3" style="width: 80px;">No</th>
                                <th class="py-3">Nama Kelas</th>
                                <th class="py-3">Tarif SPP</th>
                                <th class="py-3">Jumlah Siswa Terdaftar</th>
                                <?php if ($is_admin_or_op): ?>
                                    <th class="px-4 py-3 text-end" style="width: 180px;">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="<?php echo $is_admin_or_op ? '5' : '4'; ?>" class="text-center py-4 text-muted">Belum ada pilihan kelas terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1;
                                foreach ($classes as $c): 
                                ?>
                                    <tr>
                                        <td class="px-4"><?php echo $no++; ?></td>
                                        <td>
                                            <span class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($c['nama_kelas']); ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-primary">Rp <?php echo number_format($c['tarif_spp'], 0, ',', '.'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                                <?php echo htmlspecialchars($c['total_siswa']); ?> Siswa
                                            </span>
                                        </td>
                                        <?php if ($is_admin_or_op): ?>
                                            <td class="px-4 text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="?edit_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-warning text-dark border-warning" title="Edit Kelas">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <a href="delete.php?id=<?php echo $c['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus kelas ini? Seluruh siswa dalam kelas ini akan diset menjadi Belum Diatur.')" title="Hapus Kelas">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
