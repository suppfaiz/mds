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
        $nama = trim($_POST['nama']);
        $email = strtolower(trim($_POST['email']));
        $no_hp = trim($_POST['no_hp']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($nama) || empty($email) || empty($no_hp) || empty($password)) {
            $error = 'Semua kolom wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format alamat email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Kata sandi minimal harus 6 karakter.';
        } elseif ($password !== $confirm_password) {
            $error = 'Konfirmasi kata sandi tidak cocok.';
        } else {
            try {
                // Check if email already exists
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM pmb_akun WHERE email = ?");
                $stmt_check->execute([$email]);
                
                if ($stmt_check->fetchColumn() > 0) {
                    $error = 'Alamat email ini sudah terdaftar. Silakan masuk.';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    
                    // Insert account
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO pmb_akun (nama, email, password, no_hp) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt_insert->execute([$nama, $email, $hashed_password, $no_hp]);
                    
                    $parent_id = $pdo->lastInsertId();
                    
                    // Establish Parent session
                    $_SESSION['pmb_parent_id'] = $parent_id;
                    $_SESSION['pmb_parent_nama'] = $nama;
                    $_SESSION['pmb_parent_email'] = $email;
                    $_SESSION['pmb_parent_no_hp'] = $no_hp;
                    
                    // Regenerate CSRF for security
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    header("Location: dashboard.php");
                    exit();
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
    <title>Daftar Akun Wali/Orang Tua PMB - <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Sekolah'); ?></title>
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
        .register-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
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

<div class="register-card p-4 p-md-5">
    <!-- Brand Title -->
    <div class="text-center mb-4">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="height: 50px;" class="mb-2">
        <?php else: ?>
            <i class="bi bi-mortarboard-fill text-primary display-5 mb-2 d-inline-block"></i>
        <?php endif; ?>
        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></h5>
        <p class="text-muted small">Registrasi Akun Wali Calon Murid</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-3 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <!-- Nama Lengkap -->
        <div class="mb-3">
            <label for="nama" class="form-label">Nama Lengkap Wali <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="nama" name="nama" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" placeholder="Masukkan nama lengkap Anda..." required>
            </div>
        </div>

        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="nama@email.com" required>
            </div>
        </div>

        <!-- No HP -->
        <div class="mb-3">
            <label for="no_hp" class="form-label">No. HP / WhatsApp <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-whatsapp"></i></span>
                <input type="tel" class="form-control border-start-0 ps-0 font-monospace" id="no_hp" name="no_hp" value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : ''; ?>" placeholder="Contoh: 08123456789" required>
            </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">Kata Sandi <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Minimal 6 karakter..." required>
            </div>
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
            <label for="confirm_password" class="form-label">Ulangi Kata Sandi <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-shield-lock-fill"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="confirm_password" name="confirm_password" placeholder="Ulangi kata sandi..." required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold mb-3"><i class="bi bi-person-plus-fill me-1"></i> Daftar Akun Wali</button>
        
        <div class="text-center small text-muted">
            Sudah memiliki akun? <a href="login.php" class="text-decoration-none fw-semibold">Masuk Di Sini</a>
        </div>
        <div class="text-center mt-2 small text-muted" style="font-size: 11.5px;">
            Apakah Anda staf sekolah? <a href="../auth/login.php" class="text-decoration-none fw-semibold">Masuk Portal Staf &rarr;</a>
        </div>
    </form>
</div>

</body>
</html>
