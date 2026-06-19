<?php
$path_prefix = '../';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $path_prefix . 'config/db.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in as parent
if (isset($_SESSION['pmb_parent_id'])) {
    header("Location: dashboard.php");
    exit();
}

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

// Load School settings for headers
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
} catch (PDOException $e) {
    // Fail silently
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot anti-bot check
    $honeypot = $_POST['honeypot'] ?? '';
    // Captcha checkbox verification
    $is_human = $_POST['is_human'] ?? '';

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Validasi keamanan gagal. Silakan muat ulang halaman.';
    } elseif (!empty($honeypot)) {
        $error = 'Akses ditolak (Terdeteksi aktivitas bot).';
    } elseif (empty($is_human) || $is_human !== 'on') {
        $error = 'Harap verifikasi bahwa Anda bukan robot.';
    } else {
        $email = strtolower(trim($_POST['email']));
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Email dan kata sandi wajib diisi.';
        } else {
            try {
                // Fetch account
                $stmt = $pdo->prepare("SELECT * FROM pmb_akun WHERE email = ?");
                $stmt->execute([$email]);
                $parent = $stmt->fetch();
                
                if ($parent && password_verify($password, $parent['password'])) {
                    // Cek apakah email sudah terverifikasi
                    if ((int)$parent['is_verified'] !== 1) {
                        $_SESSION['pending_verification_email'] = $parent['email'];
                        $_SESSION['error_message'] = 'Akun Anda belum diverifikasi. Silakan masukkan kode OTP yang telah dikirim ke email Anda.';
                        header("Location: verify.php");
                        exit();
                    }

                    // Establish Parent session
                    $_SESSION['pmb_parent_id'] = $parent['id'];
                    $_SESSION['pmb_parent_nama'] = $parent['nama'];
                    $_SESSION['pmb_parent_email'] = $parent['email'];
                    $_SESSION['pmb_parent_no_hp'] = $parent['no_hp'];
                    
                    // Regenerate CSRF for security
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = 'Kombinasi email atau kata sandi tidak valid.';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Wali/Orang Tua PMB - <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Sekolah'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --card-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }
        body {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.06) 0px, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(99, 102, 241, 0.03) 0px, transparent 50%);
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
        }
        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 13px;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.6rem 1rem;
            border-color: #cbd5e1;
            font-size: 13.5px;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
        }
    </style>
</head>
<body class="py-4 px-3">

<div class="login-card p-4 p-md-5">
    <!-- Brand Title -->
    <div class="text-center mb-4">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="height: 50px;" class="mb-2">
        <?php else: ?>
            <i class="bi bi-mortarboard-fill text-primary display-5 mb-2 d-inline-block"></i>
        <?php endif; ?>
        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></h5>
        <p class="text-muted small">Portal Masuk Wali Calon Murid</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 mb-3 small" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-3 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="form-label">Alamat Email</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="nama@email.com" required>
            </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">Kata Sandi</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Masukkan kata sandi Anda..." required>
            </div>
        </div>

        <!-- Honeypot anti-bot field -->
        <div style="display: none;">
            <input type="text" name="honeypot" id="honeypot" tabindex="-1" autocomplete="off">
        </div>

        <!-- Custom "Saya bukan robot" Captcha -->
        <div class="mb-4 d-flex justify-content-center">
            <div class="captcha-container d-flex align-items-center justify-content-between p-3 border rounded" style="width: 100%; max-width: 320px; height: 74px; border-color: #cbd5e1; background: #ffffff; user-select: none;">
                <div class="d-flex align-items-center gap-3">
                    <div class="captcha-checkbox-wrapper position-relative">
                        <input type="checkbox" name="is_human" id="is_human" class="d-none" required>
                        <div class="captcha-box d-flex align-items-center justify-content-center border border-2 border-secondary" style="width: 28px; height: 28px; border-radius: 4px; cursor: pointer; transition: all 0.2s; background: #f8fafc;">
                            <i class="bi bi-check-lg text-success d-none fs-4 fw-bold"></i>
                            <div class="spinner-border spinner-border-sm text-primary d-none" role="status" style="width: 18px; height: 18px;"></div>
                        </div>
                    </div>
                    <label for="is_human" class="captcha-label mb-0 fw-semibold text-secondary-emphasis" style="font-size: 13.5px; cursor: pointer;">Saya bukan robot</label>
                </div>
                <div class="d-flex flex-column align-items-center text-muted" style="font-size: 9px; line-height: 1.2;">
                    <i class="bi bi-shield-fill-check text-primary fs-3"></i>
                    <span class="mt-1" style="font-size: 8px;">MDS Secure</span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold mb-3"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk Ke Portal</button>
        
        <div class="text-center small text-muted">
            Belum memiliki akun? <a href="register.php" class="text-decoration-none fw-semibold">Daftar Di Sini</a>
        </div>
        <div class="text-center mt-2 small text-muted" style="font-size: 11.5px;">
            Apakah Anda staf sekolah? <a href="../auth/login.php" class="text-decoration-none fw-semibold">Masuk Portal Staf &rarr;</a>
        </div>
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Custom "Saya bukan robot" Captcha Interactive Logic
const captchaBox = document.querySelector('.captcha-box');
const captchaCheckbox = document.getElementById('is_human');
const captchaLabel = document.querySelector('.captcha-label');
const checkIcon = captchaBox?.querySelector('.bi-check-lg');
const spinner = captchaBox?.querySelector('.spinner-border');

if (captchaBox && captchaCheckbox) {
    captchaBox.addEventListener('click', () => {
        if (captchaCheckbox.checked) return;
        
        captchaBox.classList.remove('border-secondary');
        captchaBox.classList.add('border-light');
        spinner.classList.remove('d-none');
        
        setTimeout(() => {
            spinner.classList.add('d-none');
            checkIcon.classList.remove('d-none');
            captchaBox.classList.remove('border-light');
            captchaBox.classList.add('border-success');
            captchaBox.style.background = 'rgba(25, 135, 84, 0.1)';
            
            captchaCheckbox.checked = true;
        }, 1000);
    });

    if (captchaLabel) {
        captchaLabel.addEventListener('click', (e) => {
            e.preventDefault();
            captchaBox.click();
        });
    }
}
</script>
</body>
</html>
