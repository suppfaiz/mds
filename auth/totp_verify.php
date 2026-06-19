<?php
$no_auth_check = true;
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';
require_once $path_prefix . 'includes/totp.php';

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
    header("Location: ../dashboard_core.php");
    exit();
}

// Ensure there is a pending user login
if (empty($_SESSION['totp_pending_user_id'])) {
    header("Location: login.php");
    exit();
}

$pendingUserId = $_SESSION['totp_pending_user_id'];
$error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$pendingUserId]);
    $user = $stmt->fetch();
    
    if (!$user || (int)($user['totp_enabled'] ?? 0) !== 1 || empty($user['totp_secret'])) {
        // User not found or does not have TOTP enabled
        unset($_SESSION['totp_pending_user_id']);
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan sistem. Silakan hubungi administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $code = trim($_POST['otp_code'] ?? '');
        
        if (empty($code)) {
            $error = 'Kode verifikasi wajib diisi.';
        } else {
            if (TOTPHelper::verifyCode($user['totp_secret'], $code)) {
                // SUCCESS — clear temporary session and finalize login
                unset($_SESSION['totp_pending_user_id']);
                
                // Regenerate session ID to prevent fixation attacks
                session_regenerate_id(true);
                
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                
                logActivity($pdo, 'Login 2FA Berhasil', 'User berhasil login dengan otentikasi ganda (2FA). Role: ' . $user['role']);
                
                header("Location: ../dashboard_core.php");
                exit();
            } else {
                $error = 'Kode verifikasi salah atau kedaluwarsa. Silakan coba lagi.';
                logActivity($pdo, 'Login 2FA Gagal', 'Percobaan login 2FA gagal untuk username: ' . $user['username']);
            }
        }
    }
    
    // Regenerate CSRF token after post
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi 2FA - Master Data Sekolah</title>
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css?v=1.1" rel="stylesheet">
</head>
<body class="login-body">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill text-warning" style="font-size: 3rem;"></i>
                <h3 class="text-white fw-bold mt-2">Verifikasi Keamanan</h3>
                <p class="text-white-50 small">Two-Factor Authentication (2FA)</p>
            </div>
            
            <div class="card login-card p-4">
                <div class="card-body p-0">
                    <h5 class="card-title fw-bold text-center mb-3">Masukkan Kode OTP</h5>
                    <p class="text-white-50 small text-center mb-4">Buka aplikasi authenticator Anda (Google Authenticator / Authy) dan masukkan 6 digit kode keamanan yang tampil.</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert" style="font-size: 14px;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" autocomplete="off">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="mb-4">
                            <input type="text" class="form-control text-center fs-3 fw-bold font-monospace bg-light bg-opacity-10 text-white border-secondary" name="otp_code" required maxlength="6" pattern="\d{6}" placeholder="000000" autofocus autocomplete="one-time-code" style="letter-spacing: 0.4rem; height: 55px;">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm mb-3" style="background: var(--primary-gradient); border: none;">
                            <i class="bi bi-unlock-fill me-2"></i> Verifikasi & Masuk
                        </button>
                        
                        <a href="login.php" class="btn btn-link w-100 text-white-50 text-decoration-none small text-center" onclick="return confirm('Kembali ke login akan membatalkan sesi masuk saat ini?');">
                            <i class="bi bi-arrow-left me-1"></i> Batal / Kembali ke Login
                        </a>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-4 text-white-50 small">
                <p class="m-0">&copy; <?php echo date('Y'); ?> Master Data Sekolah</p>
                <p class="m-0" style="font-size:10px;">Sistem dilindungi. Akses tidak sah akan direkam.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
