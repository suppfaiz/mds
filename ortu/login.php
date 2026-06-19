<?php
$no_auth_check = true;
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';

// Redirect if already logged in as parent
if (isset($_SESSION['parent_logged_in']) && isset($_SESSION['parent_siswa_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $nisn = trim($_POST['nisn'] ?? '');
        $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');

        if (empty($nisn) || empty($tanggal_lahir)) {
            $error = 'NISN dan Tanggal Lahir wajib diisi.';
        } else {
            try {
                // Cari siswa dengan NISN dan Tanggal Lahir yang cocok
                $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.nisn = ? AND s.tanggal_lahir = ?");
                $stmt->execute([$nisn, $tanggal_lahir]);
                $siswa = $stmt->fetch();

                if ($siswa) {
                    // Set session orang tua
                    $_SESSION['parent_logged_in'] = true;
                    $_SESSION['parent_siswa_id'] = $siswa['id'];
                    $_SESSION['parent_siswa_nama'] = $siswa['nama'];
                    $_SESSION['parent_siswa_nisn'] = $siswa['nisn'];
                    $_SESSION['parent_siswa_kelas'] = $siswa['nama_kelas'] ?? 'Belum Diatur';

                    // Catat log audit (IP terdeteksi otomatis di logActivity)
                    logActivity($pdo, 'Login Orang Tua', 'Orang tua siswa ' . $siswa['nama'] . ' (NISN: ' . $nisn . ') berhasil masuk portal.');

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = 'NISN atau Tanggal Lahir salah. Pastikan data sudah terdaftar di sekolah.';
                    logActivity($pdo, 'Login Gagal Orang Tua', 'Percobaan login portal orang tua gagal untuk NISN: ' . $nisn);
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Orang Tua - Master Data Sekolah</title>
    <!-- Google Fonts Outfit & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .login-card {
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(21, 27, 44, 0.85) !important;
        }
        [data-bs-theme="light"] .login-card {
            background: rgba(255, 255, 255, 0.8) !important;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body class="login-body">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="text-center mb-4">
                <i class="bi bi-heart-pulse-fill text-danger animate-pulse" style="font-size: 3rem; display: inline-block;"></i>
                <h3 class="text-white fw-bold mt-2">Portal Orang Tua</h3>
                <p class="text-white-50 small">Pemantauan Nilai, Presensi, dan Pembayaran SPP</p>
            </div>
            
            <div class="card login-card p-4">
                <div class="card-body p-0">
                    <h5 class="card-title fw-bold text-center mb-4"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Masuk Portal Wali</h5>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert" style="font-size: 13px;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" novalidate>
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="nisn" class="form-label small fw-semibold">NISN Siswa</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-card-text"></i></span>
                                <input type="text" class="form-control" id="nisn" name="nisn" placeholder="Masukkan 10 digit NISN" required autofocus maxlength="20">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="tanggal_lahir" class="form-label small fw-semibold">Tanggal Lahir Siswa</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3" style="background: var(--primary-gradient); border: none;">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Masuk Ke Portal
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <div class="d-flex justify-content-between">
                    <a href="../auth/login.php" class="text-white-50 small text-decoration-none fw-semibold"><i class="bi bi-person-workspace text-warning me-1"></i> Portal Staff/Guru</a>
                    <a href="../pmb/login.php" class="text-white-50 small text-decoration-none fw-semibold">Portal PMB &rarr;</a>
                </div>
            </div>
            
            <div class="text-center mt-5 text-white-50 small">
                <p>&copy; <?php echo date('Y'); ?> Master Data Sekolah</p>
                <div class="bg-dark bg-opacity-50 p-2 rounded" style="font-size: 11px; border: 1px solid rgba(255,255,255,0.05);">
                    <span class="d-block fw-bold text-warning mb-1">Akun Contoh (Demo):</span>
                    <span>NISN: <code>0091234567</code></span><br>
                    <span>Tgl Lahir: <code>15-08-2009</code> (2009-08-15)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
