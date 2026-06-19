<?php
$path_prefix = '../';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $path_prefix . 'config/db.php';

$no_pendaftaran = isset($_GET['no']) ? trim($_GET['no']) : '';
$pendaftar = null;
$searched = false;
$error = '';

// Load School settings for headers
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
} catch (PDOException $e) {
    // Fail silently
}

if (!empty($no_pendaftaran)) {
    $searched = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM pmb_pendaftar WHERE no_pendaftaran = ?");
        $stmt->execute([$no_pendaftaran]);
        $pendaftar = $stmt->fetch();
        
        if (!$pendaftar) {
            $error = 'Nomor pendaftaran tidak ditemukan. Silakan periksa kembali.';
        }
    } catch (PDOException $e) {
        $error = 'Kesalahan database: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Status Kelulusan PMB - <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Sekolah'); ?></title>
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
        .form-control {
            border-radius: 10px;
            padding: 0.65rem 1.25rem;
            border-color: #cbd5e1;
            font-size: 14px;
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
        .status-header {
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 20px;
            position: relative;
        }
        .status-badge {
            font-size: 14px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="py-4 py-md-5">

<div class="container" style="max-width: 600px;">
    
    <!-- Top Brand Logo -->
    <div class="text-center mb-4">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="height: 60px;" class="mb-2">
        <?php else: ?>
            <i class="bi bi-mortarboard-fill text-primary display-4 mb-2 d-inline-block"></i>
        <?php endif; ?>
        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></h4>
        <p class="text-muted small">Penerimaan Murid Baru (PMB) Online</p>
    </div>

    <!-- Search Card -->
    <div class="card pmb-card border-0 mb-4 p-4 shadow">
        <h5 class="fw-bold mb-3"><i class="bi bi-search text-primary me-1"></i> Cek Kelulusan PMB</h5>
        <form method="GET" action="">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-9">
                    <label for="no" class="form-label">Nomor Pendaftaran Siswa</label>
                    <input type="text" class="form-control text-uppercase font-monospace" id="no" name="no" value="<?php echo htmlspecialchars($no_pendaftaran); ?>" placeholder="Contoh: PMB-2026-0001" required autocomplete="off">
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-search"></i> Cari</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Result Display -->
    <?php if ($searched): ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-3 shadow-sm rounded-3 border-danger border-opacity-10 d-flex gap-2 align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                <span class="small"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php elseif ($pendaftar): ?>
            <div class="card pmb-card border-0 p-4 p-md-5">
                <div class="status-header text-center mb-4 pb-4">
                    <span class="text-muted small d-block mb-1">Status Kelulusan Siswa</span>
                    
                    <?php if ($pendaftar['status'] === 'Diterima'): ?>
                        <div class="mb-3">
                            <span class="badge bg-success-subtle text-success status-badge text-uppercase"><i class="bi bi-check-circle-fill me-1"></i> Diterima</span>
                        </div>
                        <h4 class="fw-bold text-success mb-2">Selamat, Anda Lulus PMB!</h4>
                        <p class="text-muted small mb-0">Selamat bergabung di **<?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'sekolah kami'); ?>**. Silakan hubungi tata usaha untuk penyelesaian administrasi daftar ulang.</p>
                    <?php elseif ($pendaftar['status'] === 'Ditolak'): ?>
                        <div class="mb-3">
                            <span class="badge bg-danger-subtle text-danger status-badge text-uppercase"><i class="bi bi-x-circle-fill me-1"></i> Belum Diterima</span>
                        </div>
                        <h4 class="fw-bold text-danger mb-2">Belum Lulus Seleksi</h4>
                        <p class="text-muted small mb-0">Mohon maaf, Anda belum dapat diterima pada penerimaan murid baru gelombang ini. Terima kasih atas partisipasi Anda.</p>
                    <?php else: ?>
                        <div class="mb-3">
                            <span class="badge bg-warning-subtle text-warning-emphasis status-badge text-uppercase"><i class="bi bi-clock-fill me-1"></i> Sedang Diproses</span>
                        </div>
                        <h4 class="fw-bold text-warning-emphasis mb-2">Dalam Proses Seleksi</h4>
                        <p class="text-muted small mb-0">Berkas pendaftaran Anda sedang dalam tahap verifikasi oleh panitia PMB. Silakan cek halaman ini secara berkala.</p>
                    <?php endif; ?>

                    <?php if (!empty($pendaftar['catatan_panitia'])): ?>
                        <div class="alert bg-light py-2 px-3 mt-3 mb-0 small text-start border rounded-3" style="font-size: 12px; line-height: 1.4;">
                            <span class="fw-bold text-dark-emphasis d-block mb-1"><i class="bi bi-info-circle-fill text-primary"></i> Catatan Panitia Seleksi:</span>
                            <?php echo nl2br(htmlspecialchars($pendaftar['catatan_panitia'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Registration Biodata Summary -->
                <div class="table-responsive bg-light p-3 rounded-3 border border-light-subtle small mb-0">
                    <table class="table table-sm table-borderless mb-0" style="font-size: 12px;">
                        <tr>
                            <td class="text-muted py-1" style="width: 120px;">No Pendaftaran</td>
                            <td class="py-1" style="width: 10px;">:</td>
                            <td class="fw-bold font-monospace py-1 text-primary"><?php echo htmlspecialchars($pendaftar['no_pendaftaran']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">Nama Calon Siswa</td>
                            <td class="py-1">:</td>
                            <td class="fw-bold text-dark-emphasis py-1"><?php echo htmlspecialchars($pendaftar['nama']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">Asal Sekolah</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?php echo htmlspecialchars($pendaftar['asal_sekolah']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">Waktu Mendaftar</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?php echo date('d-m-Y H:i', strtotime($pendaftar['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="text-center mt-4 no-print">
        <a href="daftar.php" class="text-decoration-none small fw-semibold"><i class="bi bi-arrow-left"></i> Kembali ke Form Pendaftaran</a>
    </div>
</div>

</body>
</html>
