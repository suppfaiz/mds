<?php
$no_auth_check = true;
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';

// =====================================================
// GATE CHECK: Hanya izinkan akses jika datang via
// path rahasia (session 'admin_gate' harus sudah diset)
// =====================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_gate'])) {
    // Tidak ada gate session → tampilkan 404 generik
    http_response_code(404);
    exit('404 Not Found.');
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// =====================================================
// BRUTE FORCE PROTECTION
// Max 5 failed attempts within 10 minutes
// =====================================================
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 10 * 60); // 10 minutes in seconds

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockout_key  = 'login_lockout_' . md5($ip);
$attempts_key = 'login_attempts_' . md5($ip);
$time_key     = 'login_time_' . md5($ip);

// Check if currently locked out
$is_locked   = false;
$lockout_remaining = 0;
if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] === true) {
    $elapsed = time() - ($_SESSION[$time_key] ?? 0);
    if ($elapsed < LOCKOUT_DURATION) {
        $is_locked = true;
        $lockout_remaining = LOCKOUT_DURATION - $elapsed;
    } else {
        // Lockout expired — reset
        unset($_SESSION[$lockout_key], $_SESSION[$attempts_key], $_SESSION[$time_key]);
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$attempts_left = MAX_LOGIN_ATTEMPTS - (int)($_SESSION[$attempts_key] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif ($is_locked) {
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . ceil($lockout_remaining / 60) . ' menit.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? '';

        if (empty($username) || empty($password) || empty($role)) {
            $error = 'Semua field wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['role'] === $role) {
                        // SUCCESS — reset attempt counter
                        unset($_SESSION[$lockout_key], $_SESSION[$attempts_key], $_SESSION[$time_key]);

                        // Regenerate session ID to prevent fixation attacks
                        session_regenerate_id(true);

                        $_SESSION['user_id']      = $user['id'];
                        $_SESSION['username']     = $user['username'];
                        $_SESSION['role']         = $user['role'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                        logActivity($pdo, 'Login', 'User berhasil login dengan hak akses: ' . $role);

                        header("Location: ../dashboard_core.php");
                        exit();
                    } else {
                        $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;
                        $_SESSION[$time_key] = time();
                        $error = 'Hak akses yang dipilih tidak sesuai.';
                        logActivity($pdo, 'Login Gagal', 'Username ' . $username . ' mencoba login dengan role yang salah: ' . $role);
                    }
                } else {
                    // FAILED — increment counter
                    $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;
                    $_SESSION[$time_key] = time();

                    if ($_SESSION[$attempts_key] >= MAX_LOGIN_ATTEMPTS) {
                        $_SESSION[$lockout_key] = true;
                        $is_locked = true;
                        $lockout_remaining = LOCKOUT_DURATION;
                        $error = 'Akun sementara dikunci selama 10 menit karena terlalu banyak percobaan login yang gagal.';
                        logActivity($pdo, 'Login Dikunci', 'IP ' . $ip . ' dikunci setelah ' . MAX_LOGIN_ATTEMPTS . ' percobaan gagal.');
                    } else {
                        $attempts_left = MAX_LOGIN_ATTEMPTS - $_SESSION[$attempts_key];
                        $error = 'Username atau password salah. Sisa percobaan: ' . $attempts_left . 'x';
                        logActivity($pdo, 'Login Gagal', 'Percobaan login gagal untuk username: ' . $username . ' (IP: ' . $ip . ')');
                    }
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem. Silakan hubungi administrator.';
            }
        }
    }

    // Regenerate CSRF token after every POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
                    
                    <?php if ($is_locked): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2" style="font-size:13px;">
                        <i class="bi bi-shield-fill-exclamation fs-5 flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>Akses Sementara Dikunci</strong><br>
                            Terlalu banyak percobaan login gagal. Silakan tunggu <strong><?php echo ceil($lockout_remaining/60); ?> menit</strong> lagi.
                        </div>
                    </div>
                    <?php else: ?>
                    <form action="" method="POST" novalidate autocomplete="off">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="mb-3">
                            <label for="username" class="form-label small fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required autofocus autocomplete="username">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label small fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePass" tabindex="-1">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
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
                            <i class="bi bi-box-arrow-in-right me-2"></i> Masuk ke Sistem
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="../pmb/login.php" class="text-white-50 small text-decoration-none fw-semibold"><i class="bi bi-people-fill text-warning me-1"></i> Portal Pendaftaran Murid Baru (PMB) &rarr;</a>
            </div>

            <div class="text-center mt-3">
                <a href="../ortu/login.php" class="text-white-50 small text-decoration-none fw-semibold"><i class="bi bi-house-fill text-info me-1"></i> Portal Orang Tua / Wali Murid &rarr;</a>
            </div>
            
            <div class="text-center mt-4 text-white-50 small">
                <p class="m-0">&copy; <?php echo date('Y'); ?> Master Data Sekolah</p>
                <p class="m-0" style="font-size:10px;">Sistem dilindungi. Akses tidak sah akan direkam.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
const togglePass = document.getElementById('togglePass');
const eyeIcon    = document.getElementById('eyeIcon');
const passInput  = document.getElementById('password');
if (togglePass && passInput) {
    togglePass.addEventListener('click', () => {
        const isPass = passInput.type === 'password';
        passInput.type = isPass ? 'text' : 'password';
        eyeIcon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
}
</script>
</body>
</html>
