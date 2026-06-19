<?php
$path_prefix = '../';
// Allow access without general login check, but ensure session is active for CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/image_helper.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;
$registered_data = null;

// Gatekeeper: Check parent authentication
if (isset($_GET['view_ticket']) && isset($_GET['token'])) {
    if (!isset($_SESSION['pmb_parent_id'])) {
        $_SESSION['error_message'] = 'Silakan masuk terlebih dahulu untuk melihat kartu pendaftaran.';
        header("Location: login.php");
        exit();
    }
    $token = trim($_GET['token']);
    $parent_id = $_SESSION['pmb_parent_id'];
    
    try {
        $stmt_ticket = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE token = ? AND pmb_akun_id = ?");
        $stmt_ticket->execute([$token, $parent_id]);
        $ticket_data = $stmt_ticket->fetch();
        
        if ($ticket_data) {
            $success = true;
            $registered_data = [
                'no_pendaftaran' => $ticket_data['no_pendaftaran'],
                'nama' => $ticket_data['nama'],
                'jenis_kelamin' => $ticket_data['jenis_kelamin'],
                'tempat_lahir' => $ticket_data['tempat_lahir'],
                'tanggal_lahir' => $ticket_data['tanggal_lahir'],
                'asal_sekolah' => $ticket_data['asal_sekolah'],
                'nama_ortu' => $ticket_data['nama_ortu'],
                'no_hp' => $ticket_data['no_hp'],
                'alamat' => $ticket_data['alamat'],
                'token' => $ticket_data['token']
            ];
        } else {
            $_SESSION['error_message'] = 'Kartu pendaftaran tidak ditemukan atau Anda tidak memiliki akses.';
            header("Location: dashboard.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
} else {
    // Normal registration view: must be authenticated
    if (!isset($_SESSION['pmb_parent_id'])) {
        $_SESSION['error_message'] = 'Silakan daftar atau masuk sebagai wali/orang tua terlebih dahulu.';
        header("Location: login.php");
        exit();
    }
}

// Load School settings for headers
$settings = null;
$applicant_count = 0;
try {
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
    if ($settings) {
        $applicant_count = $pdo->query("SELECT COUNT(*) FROM pmb_pendaftar")->fetchColumn();
    }
} catch (PDOException $e) {
    // Fail silently
}

// Validation parameters
$is_closed_by_status = ($settings['pmb_status'] ?? 'Tutup') !== 'Buka';
$today = date('Y-m-d');
$is_early = (!empty($settings['pmb_mulai']) && $today < $settings['pmb_mulai']);
$is_late = (!empty($settings['pmb_selesai']) && $today > $settings['pmb_selesai']);
$is_quota_full = ($settings && $applicant_count >= ($settings['pmb_kuota'] ?? 100));

$registration_blocked = $is_closed_by_status || $is_early || $is_late || $is_quota_full;
$block_reason = '';
if ($is_closed_by_status) {
    $block_reason = 'Pendaftaran saat ini sedang ditutup oleh administrator.';
} elseif ($is_early) {
    $block_reason = 'Pendaftaran belum dibuka. Jadwal pendaftaran dimulai pada tanggal ' . date('d-m-Y', strtotime($settings['pmb_mulai'])) . '.';
} elseif ($is_late) {
    $block_reason = 'Pendaftaran sudah ditutup. Jadwal pendaftaran telah berakhir pada tanggal ' . date('d-m-Y', strtotime($settings['pmb_selesai'])) . '.';
} elseif ($is_quota_full) {
    $block_reason = 'Kuota pendaftaran sudah terpenuhi (Maksimal ' . ($settings['pmb_kuota'] ?? 100) . ' pendaftar).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($registration_blocked) {
        $error = $block_reason;
    } else {
        // Verify CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Validasi keamanan gagal. Silakan muat ulang halaman.';
        } else {
        $nama = trim($_POST['nama']);
        $jenis_kelamin = trim($_POST['jenis_kelamin']);
        $tempat_lahir = trim($_POST['tempat_lahir']);
        $tanggal_lahir = trim($_POST['tanggal_lahir']);
        $asal_sekolah = trim($_POST['asal_sekolah']);
        $nama_ortu = trim($_POST['nama_ortu']);
        $no_hp = trim($_POST['no_hp']);
        $alamat = trim($_POST['alamat']);
        
        // Validation checks
        if (empty($nama) || empty($jenis_kelamin) || empty($tempat_lahir) || empty($tanggal_lahir) || empty($asal_sekolah) || empty($nama_ortu) || empty($no_hp) || empty($alamat)) {
            $error = 'Semua data wajib diisi.';
        } else {
            // Document upload processing
            $uploaded_file = null;
            if (isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Kesalahan saat mengunggah dokumen bukti.';
                } else {
                    $file_name = $_FILES['dokumen']['name'];
                    $file_tmp = $_FILES['dokumen']['tmp_name'];
                    $file_size = $_FILES['dokumen']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file_ext, $allowed_exts)) {
                        $error = 'Format berkas tidak didukung. Hanya berkas PDF, JPG, dan PNG yang diperbolehkan.';
                    } elseif ($file_size > $max_size) {
                        $error = 'Ukuran berkas melebihi batas maksimum 5MB.';
                    } else {
                        // Secure upload name & directory
                        $secure_dir = $path_prefix . 'uploads/secure/';
                        if (!file_exists($secure_dir)) {
                            mkdir($secure_dir, 0755, true);
                        }
                        
                        $secure_name = 'pmb_doc_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $target_path = $secure_dir . $secure_name;
                        
                        if (move_uploaded_file($file_tmp, $target_path)) {
                        compressImage($target_path);
                            $uploaded_file = 'uploads/secure/' . $secure_name;
                        } else {
                            $error = 'Gagal menyimpan dokumen bukti di server.';
                        }
                    }
                }
            }
            
            // If no error so far, register in DB
            if (empty($error)) {
                try {
                    $pdo->beginTransaction();
                    
                    // Generate Unique Reg ID (PMB-YYYY-XXXX)
                    $year_prefix = 'PMB-' . date('Y') . '-';
                    $stmt_id = $pdo->prepare("
                        SELECT no_pendaftaran 
                        FROM pmb_pendaftar 
                        WHERE no_pendaftaran LIKE ? 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt_id->execute([$year_prefix . '%']);
                    $latest = $stmt_id->fetchColumn();
                    $counter = 1;
                    if ($latest) {
                        $parts = explode('-', $latest);
                        $counter = (int)end($parts) + 1;
                    }
                    $no_pendaftaran = $year_prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
                    
                    // Generate tracking token
                    $token = bin2hex(random_bytes(16));
                    
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO pmb_pendaftar (no_pendaftaran, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, asal_sekolah, nama_ortu, no_hp, alamat, status, dokumen_bukti, token, pmb_akun_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)
                    ");
                    $stmt_insert->execute([
                        $no_pendaftaran, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, 
                        $asal_sekolah, $nama_ortu, $no_hp, $alamat, $uploaded_file, $token, $_SESSION['pmb_parent_id']
                    ]);
                    
                    $pdo->commit();
                    
                    $success = true;
                    $registered_data = [
                        'no_pendaftaran' => $no_pendaftaran,
                        'nama' => $nama,
                        'jenis_kelamin' => $jenis_kelamin,
                        'tempat_lahir' => $tempat_lahir,
                        'tanggal_lahir' => $tanggal_lahir,
                        'asal_sekolah' => $asal_sekolah,
                        'nama_ortu' => $nama_ortu,
                        'no_hp' => $no_hp,
                        'alamat' => $alamat,
                        'token' => $token
                    ];
                    
                    // Regenerate CSRF for next entry
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Pendaftaran gagal disimpan ke database: ' . $e->getMessage();
                }
            }
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
    <title>Pendaftaran Murid Baru (PMB) - <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Sekolah'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts Plus Jakarta Sans & Outfit -->
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
                radial-gradient(at 50% 0%, rgba(16, 185, 129, 0.04) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(99, 102, 241, 0.03) 0px, transparent 50%);
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }
        .pmb-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
        }
        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 13px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.6rem 1rem;
            border-color: #cbd5e1;
            font-size: 13.5px;
        }
        .form-control:focus, .form-select:focus {
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
        .ticket-header {
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 20px;
            position: relative;
        }
        .ticket-cut::before, .ticket-cut::after {
            content: '';
            position: absolute;
            bottom: -10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #f8fafc;
        }
        .ticket-cut::before { left: -10px; }
        .ticket-cut::after { right: -10px; }
        
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .pmb-card {
                border: none !important;
                box-shadow: none !important;
                background: #fff !important;
            }
        }
    </style>
</head>
<body class="py-4 py-md-5">

<div class="container" style="max-width: 650px;">
    
    <!-- Top Brand Logo -->
    <div class="text-center mb-4 no-print">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="height: 60px;" class="mb-2">
        <?php else: ?>
            <i class="bi bi-mortarboard-fill text-primary display-4 mb-2 d-inline-block"></i>
        <?php endif; ?>
        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></h4>
        <p class="text-muted small">Penerimaan Murid Baru (PMB) Online</p>
    </div>

    <?php if ($success && $registered_data): ?>
        <!-- Success Ticket layout -->
        <div class="card pmb-card border-0 mb-4 p-4 p-md-5">
            <div class="ticket-header ticket-cut text-center mb-4 pb-4">
                <i class="bi bi-check-circle-fill text-success display-4 mb-3 d-inline-block"></i>
                <h4 class="fw-bold mb-1">Pendaftaran Berhasil!</h4>
                <p class="text-muted small mb-0">Simpan kartu pendaftaran digital ini sebagai bukti pendaftaran.</p>
            </div>
            
            <div class="text-center bg-light p-4 rounded-4 mb-4 border border-light-subtle">
                <span class="text-muted small text-uppercase fw-semibold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">Nomor Pendaftaran Anda</span>
                <h2 class="fw-bold font-monospace mb-1 text-primary" style="font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($registered_data['no_pendaftaran']); ?></h2>
                <span class="text-muted small d-block">Gunakan nomor ini untuk mengecek status penerimaan siswa.</span>
            </div>

            <!-- Student Bio Summary -->
            <h6 class="fw-bold text-dark-emphasis mb-3 small text-uppercase"><i class="bi bi-person-badge text-primary me-1"></i> Data Ringkasan Calon Siswa</h6>
            <div class="table-responsive bg-light p-3 rounded-3 border border-light-subtle mb-4">
                <table class="table table-sm table-borderless mb-0 small" style="font-size: 12.5px;">
                    <tr>
                        <td class="text-muted py-1" style="width: 130px;">Nama Lengkap</td>
                        <td class="py-1" style="width: 10px;">:</td>
                        <td class="fw-bold text-dark-emphasis py-1"><?php echo htmlspecialchars($registered_data['nama']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">Jenis Kelamin</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?php echo $registered_data['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">Tempat, Tgl Lahir</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?php echo htmlspecialchars($registered_data['tempat_lahir'] . ', ' . date('d F Y', strtotime($registered_data['tanggal_lahir']))); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">Asal Sekolah</td>
                        <td class="py-1">:</td>
                        <td class="py-1 fw-medium"><?php echo htmlspecialchars($registered_data['asal_sekolah']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">Orang Tua / Wali</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?php echo htmlspecialchars($registered_data['nama_ortu']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">No. Kontak HP</td>
                        <td class="py-1">:</td>
                        <td class="py-1 font-monospace"><?php echo htmlspecialchars($registered_data['no_hp']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">Alamat Domisili</td>
                        <td class="py-1">:</td>
                        <td class="py-1 text-wrap"><?php echo htmlspecialchars($registered_data['alamat']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Barcode Visual representation -->
            <div class="d-flex flex-column align-items-center mt-3 pt-3 border-top border-light-subtle">
                <div class="barcode mb-1" style="height: 35px; width: 220px; background: repeating-linear-gradient(90deg, #1e293b, #1e293b 2px, transparent 2px, transparent 6px, #1e293b 6px, #1e293b 8px, transparent 8px, transparent 12px, #1e293b 12px, #1e293b 16px, transparent 16px, transparent 18px); opacity: 0.7;"></div>
                <span class="text-muted font-monospace" style="font-size: 9.5px;">*TOKEN-<?php echo strtoupper(substr($registered_data['token'], 0, 12)); ?>*</span>
            </div>

            <!-- Printable Action Buttons -->
            <div class="no-print d-flex gap-2 justify-content-center mt-5">
                <button class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Kartu Bukti</button>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm px-4"><i class="bi bi-speedometer2 me-1"></i> Dashboard Wali</a>
                <a href="daftar.php" class="btn btn-link btn-sm text-decoration-none">Daftar Lagi</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($registration_blocked): ?>
            <!-- Registration Blocked Visual Card -->
            <div class="card pmb-card border-0 mb-4 p-4 p-md-5 text-center shadow">
                <div class="py-4">
                    <i class="bi bi-lock-fill text-danger display-3 mb-3 d-inline-block"></i>
                    <h4 class="fw-bold text-dark-emphasis mb-2">Pendaftaran Ditutup</h4>
                    <p class="text-muted small mx-auto mb-4" style="max-width: 480px;"><?php echo htmlspecialchars($block_reason); ?></p>
                    
                    <?php if (!empty($settings['pmb_mulai']) || !empty($settings['pmb_selesai'])): ?>
                        <div class="bg-light p-3 rounded-4 border border-light-subtle d-inline-block mb-4">
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Jadwal PMB Resmi:</span>
                            <span class="fw-bold text-primary-emphasis small">
                                <?php echo !empty($settings['pmb_mulai']) ? date('d-m-Y', strtotime($settings['pmb_mulai'])) : 'Mulai'; ?>
                                s/d
                                <?php echo !empty($settings['pmb_selesai']) ? date('d-m-Y', strtotime($settings['pmb_selesai'])) : 'Selesai'; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2 justify-content-center pt-3 border-top border-light-subtle">
                        <a href="status.php" class="btn btn-outline-secondary btn-sm px-4 fw-semibold"><i class="bi bi-search me-1"></i> Cek Status Pendaftaran</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- PMB Registration form -->
            <div class="card pmb-card border-0 mb-4 shadow">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-1 text-primary-emphasis"><i class="bi bi-journal-plus me-1 text-primary"></i> Formulir Pendaftaran Online</h5>
                <p class="text-muted small">Silakan lengkapi biodata calon murid baru dengan benar.</p>
            </div>
            
            <div class="card-body p-4 pt-2">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 mb-4 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-person-badge"></i> Data Pribadi Siswa</h6>
                        </div>

                        <!-- Nama -->
                        <div class="col-12">
                            <label for="nama" class="form-label">Nama Lengkap Calon Murid</label>
                            <input type="text" class="form-control" id="nama" name="nama" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" placeholder="Masukkan nama sesuai akta kelahiran..." required>
                        </div>

                        <!-- Gender -->
                        <div class="col-md-6">
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                            <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="">-- Pilih Gender --</option>
                                <option value="L" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] === 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] === 'P') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>

                        <!-- Tempat Lahir -->
                        <div class="col-md-6">
                            <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" value="<?php echo isset($_POST['tempat_lahir']) ? htmlspecialchars($_POST['tempat_lahir']) : ''; ?>" placeholder="Contoh: Bandung" required>
                        </div>

                        <!-- Tanggal Lahir -->
                        <div class="col-md-6">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo isset($_POST['tanggal_lahir']) ? htmlspecialchars($_POST['tanggal_lahir']) : ''; ?>" required>
                        </div>

                        <!-- Asal Sekolah -->
                        <div class="col-md-6">
                            <label for="asal_sekolah" class="form-label">Asal Sekolah (SMP/MTs/Sederajat)</label>
                            <input type="text" class="form-control" id="asal_sekolah" name="asal_sekolah" value="<?php echo isset($_POST['asal_sekolah']) ? htmlspecialchars($_POST['asal_sekolah']) : ''; ?>" placeholder="Contoh: SMP Negeri 1 Bandung" required>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-people-fill"></i> Data Orang Tua & Kontak</h6>
                        </div>

                        <!-- Orang Tua -->
                        <div class="col-md-6">
                            <label for="nama_ortu" class="form-label">Nama Lengkap Orang Tua / Wali</label>
                            <input type="text" class="form-control" id="nama_ortu" name="nama_ortu" value="<?php echo isset($_POST['nama_ortu']) ? htmlspecialchars($_POST['nama_ortu']) : htmlspecialchars($_SESSION['pmb_parent_nama'] ?? ''); ?>" placeholder="Nama Ayah/Ibu/Wali..." required>
                        </div>

                        <!-- No HP -->
                        <div class="col-md-6">
                            <label for="no_hp" class="form-label">No. HP / WhatsApp Orang Tua</label>
                            <input type="tel" class="form-control" id="no_hp" name="no_hp" value="<?php echo isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : htmlspecialchars($_SESSION['pmb_parent_no_hp'] ?? ''); ?>" placeholder="Contoh: 08123456789" required>
                        </div>

                        <!-- Alamat -->
                        <div class="col-12">
                            <label for="alamat" class="form-label">Alamat Lengkap Domisili</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap rumah saat ini..." required><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-secondary-emphasis border-bottom pb-2 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-file-earmark-arrow-up"></i> Lampiran Berkas Bukti</h6>
                        </div>

                        <!-- Dokumen Lampiran -->
                        <div class="col-12">
                            <label for="dokumen" class="form-label">Upload Berkas Pendukung (Akta Kelahiran / KK / Ijazah)</label>
                            <input type="file" class="form-control" id="dokumen" name="dokumen" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">Format berkas yang diperbolehkan: PDF, JPG, PNG. Ukuran maksimal 5MB.</small>
                        </div>
                    </div>

                    <hr class="my-4">
                    
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <span class="small text-muted">Lihat riwayat? <a href="dashboard.php" class="text-decoration-none fw-semibold">Buka Dashboard Wali</a></span>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check2-circle me-1"></i> Kirim Form Pendaftaran</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
