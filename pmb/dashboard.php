<?php
$path_prefix = '../';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $path_prefix . 'config/db.php';

// Auth Check: Redirect to login if session pmb_parent_id is missing
if (!isset($_SESSION['pmb_parent_id'])) {
    $_SESSION['error_message'] = 'Silakan masuk terlebih dahulu untuk mengakses dashboard.';
    header("Location: login.php");
    exit();
}

$parent_id = $_SESSION['pmb_parent_id'];
$parent_nama = $_SESSION['pmb_parent_nama'];
$parent_email = $_SESSION['pmb_parent_email'];

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

// Handle Document Re-upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reupload_document') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $pendaftar_id = (int)$_POST['pendaftar_id'];
        
        // Fetch this candidate to verify ownership and pending status
        $stmt_check = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE id = ? AND pmb_akun_id = ?");
        $stmt_check->execute([$pendaftar_id, $parent_id]);
        $cand = $stmt_check->fetch();
        
        if (!$cand) {
            $error = 'Data pendaftar tidak ditemukan atau Anda tidak memiliki akses.';
        } elseif ($cand['status'] !== 'Pending') {
            $error = 'Anda hanya dapat memperbarui berkas jika status pendaftaran masih Pending.';
        } elseif (!isset($_FILES['dokumen']) || $_FILES['dokumen']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Silakan pilih berkas dokumen terlebih dahulu.';
        } else {
            // Process file upload
            if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Kesalahan saat mengunggah berkas.';
            } else {
                $file_name = $_FILES['dokumen']['name'];
                $file_tmp = $_FILES['dokumen']['tmp_name'];
                $file_size = $_FILES['dokumen']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file_ext, $allowed_exts)) {
                    $error = 'Format berkas tidak didukung. Hanya PDF, JPG, dan PNG yang diperbolehkan.';
                } elseif ($file_size > $max_size) {
                    $error = 'Ukuran berkas melebihi batas 5MB.';
                } else {
                    // Save file
                    $secure_dir = $path_prefix . 'uploads/secure/';
                    if (!file_exists($secure_dir)) {
                        mkdir($secure_dir, 0755, true);
                    }
                    
                    $secure_name = 'pmb_doc_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    $target_path = $secure_dir . $secure_name;
                    
                    if (move_uploaded_file($file_tmp, $target_path)) {
                        $new_file_path = 'uploads/secure/' . $secure_name;
                        
                        // Delete old file if exists
                        if (!empty($cand['dokumen_bukti']) && file_exists($path_prefix . $cand['dokumen_bukti'])) {
                            unlink($path_prefix . $cand['dokumen_bukti']);
                        }
                        
                        // Update in database
                        $stmt_update = $pdo->prepare("UPDATE pmb_pendaftar SET dokumen_bukti = ? WHERE id = ?");
                        $stmt_update->execute([$new_file_path, $pendaftar_id]);
                        
                        logActivity($pdo, 'Koreksi Berkas PMB', "Mengunggah ulang berkas dokumen untuk pendaftar " . $cand['nama'] . " (" . $cand['no_pendaftaran'] . ")");
                        
                        $_SESSION['success_message'] = 'Berkas dokumen bukti berhasil diperbarui.';
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = 'Gagal menyimpan berkas ke server.';
                    }
                }
            }
        }
    }
}

// Load School settings for headers
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
} catch (PDOException $e) {
    // Fail silently
}

// Fetch all registered children for this parent
$children = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE pmb_akun_id = ? ORDER BY created_at DESC");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Gagal memuat data pendaftaran: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Wali PMB - <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Sekolah'); ?></title>
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
            --card-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }
        body {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.03) 0px, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(99, 102, 241, 0.02) 0px, transparent 50%);
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }
        .navbar-brand-custom {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .dashboard-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
        }
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.6rem 1.25rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.3);
        }
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 0.6rem 1.25rem;
            font-weight: 600;
            border-color: #cbd5e1;
        }
        .btn-outline-secondary:hover {
            background-color: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }
        /* Milestone tracker */
        .step-tracker {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 20px 0;
            padding: 0 10px;
        }
        .step-tracker::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #e2e8f0;
            transform: translateY(-50%);
            z-index: 1;
        }
        .step-tracker-progress {
            position: absolute;
            top: 50%;
            left: 0;
            height: 3px;
            background-color: #6366f1;
            transform: translateY(-50%);
            z-index: 1;
            transition: width 0.4s ease;
        }
        .step-item {
            position: relative;
            z-index: 2;
            text-align: center;
            background: #ffffff;
            padding: 0 10px;
        }
        .step-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            color: #94a3b8;
            font-weight: bold;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .step-item.completed .step-icon {
            background-color: #4f46e5;
            border-color: #4f46e5;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }
        .step-item.active .step-icon {
            background-color: #f59e0b;
            border-color: #f59e0b;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        }
        .step-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }
        .step-item.completed .step-title {
            color: #4f46e5;
        }
        .step-item.active .step-title {
            color: #d97706;
        }
    </style>
</head>
<body>

<!-- Navigation Header -->
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm sticky-top py-3">
    <div class="container">
        <a class="navbar-brand navbar-brand-custom d-flex align-items-center gap-2 text-dark" href="#">
            <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
                <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="height: 35px;">
            <?php else: ?>
                <i class="bi bi-mortarboard-fill text-primary fs-4"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto align-items-center gap-3 mt-3 mt-lg-0">
                <li class="nav-item d-flex flex-column text-end d-none d-lg-flex">
                    <span class="text-dark fw-bold small"><?php echo htmlspecialchars($parent_nama); ?></span>
                    <span class="text-muted small" style="font-size: 11px;"><?php echo htmlspecialchars($parent_email); ?></span>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-sm btn-outline-danger fw-semibold"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5" style="max-width: 900px;">
    <!-- Welcome Header banner -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark-emphasis">Selamat Datang, <?php echo htmlspecialchars($parent_nama); ?>!</h4>
            <p class="text-muted mb-0 small">Pantau status seleksi dan cetak kartu pendaftaran calon murid baru secara online.</p>
        </div>
        <a href="daftar.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle-fill me-1"></i> Daftarkan Anak</a>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 mb-4 shadow-sm small border-start border-success border-3" role="alert">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-4 shadow-sm small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <h6 class="fw-bold text-dark-emphasis mb-3 text-uppercase small" style="letter-spacing: 0.5px;"><i class="bi bi-list-stars text-primary me-1"></i> Daftar Pendaftaran Anak Anda</h6>

    <?php if (empty($children)): ?>
        <!-- Empty registered children list state -->
        <div class="card dashboard-card border-0 py-5 px-4 text-center">
            <div class="py-4">
                <i class="bi bi-journal-x display-3 text-muted opacity-50 mb-3 d-inline-block"></i>
                <h5 class="fw-bold text-dark-emphasis mb-2">Belum Ada Pendaftaran</h5>
                <p class="text-muted small mx-auto mb-4" style="max-width: 450px;">Anda belum mendaftarkan anak Anda ke portal PMB ini. Silakan klik tombol di bawah untuk mengisi formulir pendaftaran.</p>
                <a href="daftar.php" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-journal-plus me-1"></i> Daftarkan Calon Siswa Sekarang</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($children as $c): ?>
                <?php
                // Determine step statuses
                $step1 = 'completed'; // Form submitted
                $step2 = '';
                $step3 = '';
                $tracker_width = '0%';
                
                if ($c['status'] === 'Pending') {
                    $step2 = 'active';
                    $tracker_width = '50%';
                } elseif ($c['status'] === 'Diterima' || $c['status'] === 'Ditolak') {
                    $step2 = 'completed';
                    $step3 = 'completed';
                    $tracker_width = '100%';
                }
                ?>
                <div class="col-12">
                    <div class="card dashboard-card border-0">
                        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center py-3 px-4">
                            <span class="font-monospace fw-bold text-primary" style="font-size: 13.5px;"><i class="bi bi-hash"></i> <?php echo htmlspecialchars($c['no_pendaftaran']); ?></span>
                            <span class="text-muted small" style="font-size: 11.5px;">Registrasi: <?php echo date('d-m-Y H:i', strtotime($c['created_at'])); ?></span>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3 align-items-center">
                                <!-- Student Biodata -->
                                <div class="col-md-6 border-end-md">
                                    <h5 class="fw-bold text-dark-emphasis mb-2"><?php echo htmlspecialchars($c['nama']); ?></h5>
                                    <table class="table table-sm table-borderless mb-0 small text-muted" style="font-size: 12.5px;">
                                        <tr>
                                            <td style="width: 110px;" class="py-1">Jenis Kelamin</td>
                                            <td style="width: 10px;" class="py-1">:</td>
                                            <td class="py-1"><?php echo $c['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="py-1">Lahir</td>
                                            <td class="py-1">:</td>
                                            <td class="py-1"><?php echo htmlspecialchars($c['tempat_lahir'] . ', ' . date('d-m-Y', strtotime($c['tanggal_lahir']))); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="py-1">Sekolah Asal</td>
                                            <td class="py-1">:</td>
                                            <td class="py-1 fw-medium"><?php echo htmlspecialchars($c['asal_sekolah']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="py-1">Berkas Lampiran</td>
                                            <td class="py-1">:</td>
                                            <td class="py-1">
                                                <?php if (!empty($c['dokumen_bukti'])): ?>
                                                    <a href="view_doc.php?id=<?php echo $c['id']; ?>" target="_blank" class="text-decoration-none fw-bold text-primary"><i class="bi bi-file-earmark-check-fill text-primary"></i> Lihat Berkas</a>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-file-earmark-x"></i> Belum diunggah</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- Status evaluation & tracker -->
                                <div class="col-md-6 ps-md-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small fw-semibold text-muted">Status Seleksi:</span>
                                        <?php if ($c['status'] === 'Diterima'): ?>
                                            <span class="badge bg-success-subtle text-success py-1 px-3 rounded-pill fw-bold" style="font-size: 11px;"><i class="bi bi-check-circle-fill"></i> Diterima</span>
                                        <?php elseif ($c['status'] === 'Ditolak'): ?>
                                            <span class="badge bg-danger-subtle text-danger py-1 px-3 rounded-pill fw-bold" style="font-size: 11px;"><i class="bi bi-x-circle-fill"></i> Ditolak</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis py-1 px-3 rounded-pill fw-bold" style="font-size: 11px;"><i class="bi bi-clock-fill"></i> Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Visual Progress Line -->
                                    <div class="step-tracker">
                                        <div class="step-tracker-progress" style="width: <?php echo $tracker_width; ?>;"></div>
                                        
                                        <div class="step-item <?php echo $step1; ?>">
                                            <div class="step-icon"><i class="bi bi-file-earmark-check"></i></div>
                                            <div class="step-title">Form Kirim</div>
                                        </div>
                                        <div class="step-item <?php echo $step2; ?>">
                                            <div class="step-icon">
                                                <?php if ($step2 === 'active'): ?>
                                                    <div class="spinner-border spinner-border-sm text-white" role="status" style="width: 10px; height: 10px;"><span class="visually-hidden">Loading...</span></div>
                                                <?php else: ?>
                                                    <i class="bi bi-sliders"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="step-title">Evaluasi</div>
                                        </div>
                                        <div class="step-item <?php echo $step3; ?>">
                                            <div class="step-icon">
                                                <?php if ($c['status'] === 'Diterima'): ?>
                                                    <i class="bi bi-award"></i>
                                                <?php elseif ($c['status'] === 'Ditolak'): ?>
                                                    <i class="bi bi-x-circle"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-check-circle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="step-title">Keputusan</div>
                                        </div>
                                    </div>

                                    <!-- Feedback detail text -->
                                    <div class="alert bg-light py-2 px-3 mb-0 small text-muted border border-light-subtle rounded-3" style="font-size: 11.5px; line-height: 1.4;">
                                        <?php if ($c['status'] === 'Diterima'): ?>
                                            <span class="text-success-emphasis fw-bold d-block"><i class="bi bi-award-fill"></i> Pengumuman Kelulusan</span>
                                            Selamat! Calon siswa dinyatakan **Lulus Seleksi**. Silakan lakukan konfirmasi ulang dan melengkapi berkas administrasi langsung ke tata usaha sekolah.
                                        <?php elseif ($c['status'] === 'Ditolak'): ?>
                                            <span class="text-danger-emphasis fw-bold d-block"><i class="bi bi-x-circle-fill"></i> Hasil Keputusan</span>
                                            Maaf, calon pendaftar dinyatakan **Gugur/Belum Diterima** pada seleksi periode ini. Terima kasih.
                                        <?php else: ?>
                                            <span class="text-warning-emphasis fw-bold d-block"><i class="bi bi-clock-fill"></i> Berkas Sedang Direview</span>
                                            Formulir pendaftaran dan dokumen terlampir sedang diverifikasi oleh panitia. Mohon menunggu.
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($c['catatan_panitia'])): ?>
                                            <div class="mt-2 pt-2 border-top border-secondary-subtle border-opacity-25 text-dark-emphasis">
                                                <strong>Keterangan Panitia:</strong> <?php echo nl2br(htmlspecialchars($c['catatan_panitia'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Actions -->
                            <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-end flex-wrap align-items-center">
                                <?php if ($c['status'] === 'Pending'): ?>
                                    <button class="btn btn-outline-warning btn-sm px-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#reupload-form-<?php echo $c['id']; ?>" aria-expanded="false" aria-controls="reupload-form-<?php echo $c['id']; ?>">
                                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Koreksi Berkas
                                    </button>
                                <?php endif; ?>
                                <a href="daftar.php?view_ticket=1&token=<?php echo $c['token']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm px-3 fw-bold"><i class="bi bi-printer me-1"></i> Cetak Bukti Kartu</a>
                            </div>

                            <?php if ($c['status'] === 'Pending'): ?>
                                <!-- Re-upload Form Gated by Pending status -->
                                <div class="collapse mt-3" id="reupload-form-<?php echo $c['id']; ?>">
                                    <div class="card card-body bg-light border-warning border-opacity-20 p-3" style="border-radius: 12px;">
                                        <form method="POST" action="" enctype="multipart/form-data" class="d-flex align-items-end gap-2 flex-wrap">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="reupload_document">
                                            <input type="hidden" name="pendaftar_id" value="<?php echo $c['id']; ?>">
                                            <div class="flex-grow-1" style="min-width: 200px;">
                                                <label for="dokumen-<?php echo $c['id']; ?>" class="form-label mb-1" style="font-size: 11px;">Pilih Berkas Baru (PDF, JPG, PNG - Maks 5MB)</label>
                                                <input type="file" class="form-control form-control-sm" id="dokumen-<?php echo $c['id']; ?>" name="dokumen" accept=".pdf,.jpg,.jpeg,.png" required>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-warning fw-bold"><i class="bi bi-cloud-arrow-up"></i> Unggah Baru</button>
                                        </form>
                                        <small class="text-muted mt-1" style="font-size: 10.5px;">Unggahan berkas baru akan secara otomatis menggantikan dokumen lama yang salah/buram.</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
