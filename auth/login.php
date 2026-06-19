<?php
$no_auth_check = true;
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'] ?? '';

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Semua field wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Verify if the role requested matches user's role
                if ($user['role'] === $role) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                    // Log activity
                    logActivity($pdo, 'Login', 'User berhasil login dengan hak akses: ' . $role);

                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = 'Hak akses yang dipilih tidak sesuai.';
                    logActivity($pdo, 'Login Gagal', 'Username ' . $username . ' mencoba login dengan role yang salah: ' . $role);
                }
            } else {
                $error = 'Username atau password salah.';
                // Log failed attempt
                logActivity($pdo, 'Login Gagal', 'Percobaan login gagal untuk username: ' . $username);
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Master Data Sekolah</title>
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="login-body">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <i class="bi bi-mortarboard-fill text-warning" style="font-size: 3rem;"></i>
                <h3 class="text-white fw-bold mt-2">Master Data Sekolah</h3>
                <p class="text-white-50 small">Pusat Data Siswa, Guru & Dokumen Sekolah</p>
            </div>
            
            <div class="card login-card p-4">
                <div class="card-body p-0">
                    <h5 class="card-title fw-bold text-center mb-4">Masuk Ke Sistem</h5>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert" style="font-size: 14px;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label small fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="role" class="form-label small fw-semibold">Hak Akses</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-shield-lock"></i></span>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="" disabled selected>Pilih Hak Akses...</option>
                                    <option value="super_admin">Super Admin</option>
                                    <option value="operator">Operator</option>
                                    <option value="guru">Guru</option>
                                    <option value="kepala_sekolah">Kepala Sekolah</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" style="background: var(--primary-gradient); border: none;">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Log In
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="../pmb/login.php" class="text-white-50 small text-decoration-none fw-semibold"><i class="bi bi-people-fill text-warning me-1"></i> Portal Pendaftaran Murid Baru (PMB) &rarr;</a>
            </div>
            
            <div class="text-center mt-4 text-white-50 small">
                <p>&copy; <?php echo date('Y'); ?> Master Data Sekolah</p>
                <div class="d-flex justify-content-center gap-2" style="font-size: 11px;">
                    <span>admin / admin</span> | 
                    <span>operator / operator</span> | 
                    <span>guru / guru</span> | 
                    <span>kepsek / kepsek</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
