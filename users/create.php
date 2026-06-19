<?php
$path_prefix = '../';
$page_title = 'Tambah User';
$active_menu = 'users';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check role
checkRole(['super_admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username']);
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'] ?? '';

        // Simple validation
        if (empty($username) || empty($nama_lengkap) || empty($password) || empty($role)) {
            $error = 'Semua field wajib diisi.';
        } elseif ($password !== $confirm_password) {
            $error = 'Konfirmasi password tidak cocok.';
        } elseif (strlen($password) < 4) {
            $error = 'Password harus minimal 4 karakter.';
        } else {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username sudah terdaftar. Gunakan username lain.';
                } else {
                    // Insert new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $role, $nama_lengkap]);

                    logActivity($pdo, 'Tambah User', 'Menambahkan user baru: ' . $username . ' (' . $role . ')');
                    $_SESSION['success_message'] = 'User ' . htmlspecialchars($username) . ' berhasil ditambahkan.';
                    header("Location: index.php");
                    exit();
                }
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan user: ' . $e->getMessage();
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
            <h4 class="fw-bold mb-0">Tambah User Baru</h4>
        </div>
        
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label fw-semibold small">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold small">Username</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <div class="form-text">Username digunakan untuk masuk ke sistem.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label fw-semibold small">Hak Akses / Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="" disabled selected>Pilih Role...</option>
                            <option value="super_admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                            <option value="operator" <?php echo (isset($_POST['role']) && $_POST['role'] === 'operator') ? 'selected' : ''; ?>>Operator</option>
                            <option value="guru" <?php echo (isset($_POST['role']) && $_POST['role'] === 'guru') ? 'selected' : ''; ?>>Guru</option>
                            <option value="kepala_sekolah" <?php echo (isset($_POST['role']) && $_POST['role'] === 'kepala_sekolah') ? 'selected' : ''; ?>>Kepala Sekolah</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold small">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold small">Konfirmasi Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-save me-2"></i> Simpan User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
