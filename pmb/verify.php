<?php
$path_prefix = '../';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/mail.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect jika sudah login
if (isset($_SESSION['pmb_parent_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Ambil email dari session pending
$pending_email = $_SESSION['pending_verification_email'] ?? '';

if (empty($pending_email)) {
    $_SESSION['error_message'] = 'Sesi verifikasi habis atau tidak ditemukan. Silakan daftar kembali.';
    header("Location: register.php");
    exit();
}

$error = '';
$success = '';

// Load School settings for headers
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
} catch (PDOException $e) {
    // Fail silently
}

// Handler POST: Submit OTP atau Kirim Ulang OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Validasi keamanan gagal. Silakan muat ulang halaman.';
    } else {
        // Aksi: Verifikasi OTP
        if (isset($_POST['action']) && $_POST['action'] === 'verify') {
            $otp = trim($_POST['otp'] ?? '');
            
            if (empty($otp)) {
                $error = 'Kode verifikasi wajib diisi.';
            } elseif (strlen($otp) !== 6 || !is_numeric($otp)) {
                $error = 'Kode verifikasi harus berupa 6 digit angka.';
            } else {
                try {
                    // Cari akun pending berdasarkan email dan token
                    $stmt = $pdo->prepare("SELECT * FROM pmb_akun WHERE email = ? AND verification_token = ? AND is_verified = 0");
                    $stmt->execute([$pending_email, $otp]);
                    $account = $stmt->fetch();
                    
                    if ($account) {
                        // OTP Valid: Aktivasi akun
                        $stmt_update = $pdo->prepare("UPDATE pmb_akun SET is_verified = 1, verification_token = NULL WHERE id = ?");
                        $stmt_update->execute([$account['id']]);
                        
                        // Buat session login PMB
                        $_SESSION['pmb_parent_id'] = $account['id'];
                        $_SESSION['pmb_parent_nama'] = $account['nama'];
                        $_SESSION['pmb_parent_email'] = $account['email'];
                        $_SESSION['pmb_parent_no_hp'] = $account['no_hp'];
                        
                        // Bersihkan session pending
                        unset($_SESSION['pending_verification_email']);
                        
                        // Regenerate CSRF for security
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $_SESSION['success_message'] = 'Akun Anda berhasil diverifikasi! Selamat datang.';
                        
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = 'Kode verifikasi salah atau sudah kadaluarsa.';
                    }
                } catch (PDOException $e) {
                    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                }
            }
        }
        
        // Aksi: Kirim Ulang OTP
        elseif (isset($_POST['action']) && $_POST['action'] === 'resend') {
            try {
                // Generate OTP baru
                $new_otp = (string)rand(100000, 999999);
                
                // Update database
                $stmt_update = $pdo->prepare("UPDATE pmb_akun SET verification_token = ? WHERE email = ? AND is_verified = 0");
                $stmt_update->execute([$new_otp, $pending_email]);
                
                // Dapatkan nama untuk email
                $stmt_get = $pdo->prepare("SELECT nama FROM pmb_akun WHERE email = ?");
                $stmt_get->execute([$pending_email]);
                $nama = $stmt_get->fetchColumn();
                
                // Kirim email ulang
                if (sendVerificationEmail($pending_email, $nama, $new_otp)) {
                    $success = 'Kode verifikasi baru berhasil dikirim ke email Anda.';
                } else {
                    $error = 'Gagal mengirim email verifikasi. Silakan cek konfigurasi email server atau coba lagi.';
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
    <title>Verifikasi Akun PMB - <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Sekolah'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
        .verify-card {
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
        .form-control-otp {
            border-radius: 12px;
            padding: 0.8rem 1rem;
            border-color: #cbd5e1;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 8px;
            text-align: center;
        }
        .form-control-otp:focus {
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
        .btn-link {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }
        .btn-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="py-4 px-3">

<div class="verify-card p-4 p-md-5">
    <div class="text-center mb-4">
        <i class="bi bi-shield-fill-check text-primary display-5 mb-2 d-inline-block"></i>
        <h4 class="fw-bold mb-1">Verifikasi Email Anda</h4>
        <p class="text-muted small">Kami telah mengirimkan 6-digit kode OTP ke alamat email:<br><strong class="text-dark"><?php echo htmlspecialchars($pending_email); ?></strong></p>
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

    <!-- Form Submit OTP -->
    <form method="POST" action="" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="action" value="verify">

        <div class="mb-4">
            <label for="otp" class="form-label d-block text-center mb-2">Masukkan 6-Digit Kode Verifikasi</label>
            <input type="text" class="form-control form-control-otp" id="otp" name="otp" placeholder="123456" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus autocomplete="one-time-code">
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-patch-check-fill me-1"></i> Verifikasi Akun</button>
    </form>

    <div class="text-center pt-2 border-top small text-muted">
        Tidak menerima email? 
        <!-- Form Kirim Ulang OTP -->
        <form method="POST" action="" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-link p-0 fs-7 border-0 align-baseline">Kirim Ulang Kode</button>
        </form>
    </div>

    <div class="text-center mt-3 small">
        <a href="register.php" class="text-decoration-none text-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali & Daftar Ulang</a>
    </div>
</div>

</body>
</html>
