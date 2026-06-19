<?php
$path_prefix = '../';
$page_title = 'Keamanan 2FA';
$active_menu = 'dashboard'; // keeping active sidebar item neutral or dashboard

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';
require_once $path_prefix . 'includes/totp.php';

// Enforce login for all staff roles
checkRole(['super_admin', 'operator', 'guru', 'kepala_sekolah']);

$error = '';
$userId = $_SESSION['user_id'];

// Fetch latest user details from DB
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = 'User tidak ditemukan.';
        header("Location: ../dashboard_core.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat profil user: ' . $e->getMessage();
    header("Location: ../dashboard_core.php");
    exit();
}

$totpEnabled = (int)($user['totp_enabled'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'enable') {
            $code = trim($_POST['otp_code'] ?? '');
            $secret = $_SESSION['pending_totp_secret'] ?? '';
            
            if (empty($secret)) {
                $error = 'Sesi setup 2FA kedaluwarsa. Silakan muat ulang halaman.';
            } elseif (empty($code)) {
                $error = 'Kode verifikasi wajib diisi.';
            } else {
                if (TOTPHelper::verifyCode($secret, $code)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?");
                        $stmt->execute([$secret, $userId]);
                        
                        logActivity($pdo, 'Aktifkan 2FA', 'Pengguna mengaktifkan keamanan Two-Factor Authentication (TOTP).');
                        
                        unset($_SESSION['pending_totp_secret']);
                        $_SESSION['success_message'] = 'Otentikasi Dua Faktor (2FA) berhasil diaktifkan!';
                        header("Location: totp_setup.php");
                        exit();
                    } catch (PDOException $e) {
                        $error = 'Gagal memperbarui status keamanan di database: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Kode verifikasi tidak cocok atau kedaluwarsa. Pastikan aplikasi Authenticator Anda sinkron dengan jam HP.';
                }
            }
        } elseif ($action === 'disable') {
            $password = $_POST['password'] ?? '';
            
            if (empty($password)) {
                $error = 'Password konfirmasi wajib diisi untuk menonaktifkan 2FA.';
            } else {
                if (password_verify($password, $user['password'])) {
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?");
                        $stmt->execute([$userId]);
                        
                        logActivity($pdo, 'Nonaktifkan 2FA', 'Pengguna menonaktifkan keamanan Two-Factor Authentication (TOTP).');
                        
                        $_SESSION['success_message'] = 'Otentikasi Dua Faktor (2FA) telah berhasil dinonaktifkan.';
                        header("Location: totp_setup.php");
                        exit();
                    } catch (PDOException $e) {
                        $error = 'Gagal menonaktifkan 2FA di database: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Konfirmasi password salah. Proses pembatalan 2FA ditolak.';
                }
            }
        }
    }
}

// Generate a temporary secret if not already set, for initialization
if (!$totpEnabled && empty($_SESSION['pending_totp_secret'])) {
    $_SESSION['pending_totp_secret'] = TOTPHelper::generateSecret();
}

include $path_prefix . 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="../dashboard_core.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <h4 class="fw-bold mb-0">Keamanan Multi-Faktor (2FA)</h4>
        </div>
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($totpEnabled): ?>
                    <!-- State: 2FA is active -->
                    <div class="text-center py-3">
                        <div class="mb-3">
                            <i class="bi bi-shield-fill-check text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="fw-bold text-success">Otentikasi Dua Faktor (2FA) Aktif</h5>
                        <p class="text-muted small px-3">Akun Anda dilindungi dengan lapisan keamanan ekstra menggunakan kode TOTP (Time-based One-Time Password) dari Google Authenticator atau Authy.</p>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded p-3 text-danger mb-3">
                        <h6 class="fw-bold mb-1 small"><i class="bi bi-exclamation-octagon-fill"></i> Area Sensitif</h6>
                        <p class="small mb-0" style="font-size: 12px; line-height: 1.5;">Menonaktifkan 2FA akan mengurangi tingkat perlindungan akun Anda. Siapa pun dengan kredensial password Anda akan bisa langsung masuk ke dashboard.</p>
                    </div>

                    <form action="" method="POST" class="mt-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="disable">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small">Password Akun Anda</label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password untuk konfirmasi">
                        </div>
                        
                        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold" onclick="return confirm('Apakah Anda benar-benar ingin menonaktifkan 2FA?');">
                            <i class="bi bi-shield-slash me-2"></i> Nonaktifkan 2FA
                        </button>
                    </form>

                <?php else: ?>
                    <!-- State: 2FA is inactive, show setup instructions -->
                    <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock-fill text-primary"></i> Hubungkan Aplikasi Authenticator</h5>
                    <p class="text-muted small mb-4">Tambahkan proteksi ekstra dengan mewajibkan kode 6 digit dari ponsel Anda setiap kali login.</p>
                    
                    <div class="steps-wrapper">
                        <!-- Step 1 -->
                        <div class="d-flex gap-3 mb-4">
                            <div class="step-number d-flex align-items-center justify-content-center bg-primary text-white rounded-circle flex-shrink-0" style="width: 28px; height: 28px; font-size: 13px; font-weight: 700;">1</div>
                            <div>
                                <h6 class="fw-bold mb-1">Unduh Aplikasi Authenticator</h6>
                                <p class="text-muted small mb-0">Pasang aplikasi <strong class="text-dark-emphasis">Google Authenticator</strong>, <strong class="text-dark-emphasis">Authy</strong>, atau Microsoft Authenticator di Android / iPhone Anda.</p>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="d-flex gap-3 mb-4">
                            <div class="step-number d-flex align-items-center justify-content-center bg-primary text-white rounded-circle flex-shrink-0" style="width: 28px; height: 28px; font-size: 13px; font-weight: 700;">2</div>
                            <div class="w-100">
                                <h6 class="fw-bold mb-1">Scan Kode QR di Bawah</h6>
                                <p class="text-muted small mb-3">Buka aplikasi authenticator Anda, pilih opsi <strong>"Scan QR Code"</strong> atau ketik kunci rahasia secara manual.</p>
                                
                                <div class="text-center my-3 bg-white p-3 border rounded shadow-xs d-inline-block">
                                    <?php
                                    $secret = $_SESSION['pending_totp_secret'];
                                    $username = $user['username'];
                                    $issuer = 'Master Data Sekolah - ' . $_SERVER['HTTP_HOST'];
                                    $qrUrl = TOTPHelper::getQRUrl($username, $secret, $issuer);
                                    // Construct Google Chart QR URL
                                    $qrChartUrl = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($qrUrl);
                                    ?>
                                    <img src="<?php echo htmlspecialchars($qrChartUrl); ?>" alt="QR Code 2FA" style="max-width: 200px; width: 100%;">
                                </div>
                                
                                <div class="p-3 bg-light border rounded mt-2">
                                    <div class="small text-secondary mb-1">Kunci Rahasia Manual:</div>
                                    <code class="fs-6 fw-bold text-primary font-monospace select-all"><?php echo htmlspecialchars($secret); ?></code>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="d-flex gap-3">
                            <div class="step-number d-flex align-items-center justify-content-center bg-primary text-white rounded-circle flex-shrink-0" style="width: 28px; height: 28px; font-size: 13px; font-weight: 700;">3</div>
                            <div class="w-100">
                                <h6 class="fw-bold mb-1">Masukkan Kode Verifikasi 6 Digit</h6>
                                <p class="text-muted small mb-3">Ketik kode yang muncul di aplikasi Authenticator untuk memvalidasi sinkronisasi perangkat.</p>
                                
                                <form action="" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="enable">
                                    
                                    <div class="mb-3">
                                        <input type="text" class="form-control text-center fs-4 fw-bold font-monospace letter-spacing-lg" name="otp_code" required maxlength="6" pattern="\d{6}" placeholder="000000" autocomplete="one-time-code" style="letter-spacing: 0.5rem;">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                        <i class="bi bi-shield-check me-2"></i> Aktifkan & Simpan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
