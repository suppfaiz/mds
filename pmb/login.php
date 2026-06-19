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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Validasi keamanan gagal. Silakan muat ulang halaman.';
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
        <div class="mb-4">
            <label for="password" class="form-label">Kata Sandi</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Masukkan kata sandi Anda..." required>
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

</body>
</html>
