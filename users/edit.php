<?php
$path_prefix = '../';
$page_title = 'Edit User';
$active_menu = 'users';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Check role
checkRole(['super_admin']);

$error = '';
$user = null;

// Get user data
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error_message'] = 'User tidak ditemukan.';
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal memuat user: ' . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = 'ID User tidak valid.';
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username']);
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if (empty($username) || empty($nama_lengkap) || empty($role)) {
            $error = 'Nama Lengkap, Username, dan Role wajib diisi.';
        } else {
            try {
                // Check if username unique to others
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username sudah digunakan oleh user lain.';
                } else {
                    $pdo->beginTransaction();
                    
                    // If password provided
                    if (!empty($password)) {
                        if ($password !== $confirm_password) {
                            $error = 'Konfirmasi password tidak cocok.';
                        } elseif (strlen($password) < 4) {
                            $error = 'Password harus minimal 4 karakter.';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, nama_lengkap = ? WHERE id = ?");
                            $stmt->execute([$username, $hashed_password, $role, $nama_lengkap, $id]);
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, nama_lengkap = ? WHERE id = ?");
                        $stmt->execute([$username, $role, $nama_lengkap, $id]);
                    }
                    
                    if (empty($error)) {
                        $pdo->commit();
                        logActivity($pdo, 'Edit User', 'Mengubah data user: ' . $username . ' (ID: ' . $id . ')');
                        $_SESSION['success_message'] = 'Data user ' . htmlspecialchars($username) . ' berhasil diupdate.';
                        header("Location: index.php");
                        exit();
                    } else {
                        $pdo->rollBack();
                    }
                }
            } catch (PDOException $e) {
                $error = 'Gagal mengupdate user: ' . $e->getMessage();
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
            <h4 class="fw-bold mb-0">Edit User</h4>
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
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold small">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label fw-semibold small">Hak Akses / Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="super_admin" <?php echo ($user['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                            <option value="operator" <?php echo ($user['role'] === 'operator') ? 'selected' : ''; ?>>Operator</option>
                            <option value="guru" <?php echo ($user['role'] === 'guru') ? 'selected' : ''; ?>>Guru</option>
                            <option value="kepala_sekolah" <?php echo ($user['role'] === 'kepala_sekolah') ? 'selected' : ''; ?>>Kepala Sekolah</option>
                        </select>
                    </div>
                    
                    <div class="card bg-light border-0 p-3 mb-3">
                        <h6 class="fw-bold text-dark-emphasis mb-2 small"><i class="bi bi-shield-key"></i> Ubah Password (Opsional)</h6>
                        <p class="text-muted small mb-2">Kosongkan kolom di bawah jika tidak ingin mengganti password user.</p>
                        
                        <div class="mb-2">
                            <label for="password" class="form-label fw-semibold small" style="font-size: 11px;">Password Baru</label>
                            <input type="password" class="form-control form-control-sm" id="password" name="password" placeholder="Minimal 4 karakter">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="form-label fw-semibold small" style="font-size: 11px;">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control form-control-sm" id="confirm_password" name="confirm_password" placeholder="Ulangi password baru">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-save me-2"></i> Update User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
