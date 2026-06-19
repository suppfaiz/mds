<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';

// Validasi session orang tua
if (!isset($_SESSION['parent_logged_in']) || !isset($_SESSION['parent_siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = (int)$_SESSION['parent_siswa_id'];
$siswa = null;

try {
    // 1. Ambil Profil Siswa dan Kelas
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tarif_spp FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$siswa_id]);
    $siswa = $stmt->fetch();
    
    if (!$siswa) {
        // Jika data siswa mendadak hilang/terhapus, hapus sesi
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // 2. Ambil Informasi Sekolah & Rekening Bank
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();

    // 3. Ambil Nilai Rapor/Akademik
    $nilai_stmt = $pdo->prepare("SELECT * FROM nilai WHERE siswa_id = ? ORDER BY tahun_ajaran DESC, semester DESC, mata_pelajaran ASC");
    $nilai_stmt->execute([$siswa_id]);
    $grades = $nilai_stmt->fetchAll();

    // 4. Ambil Riwayat Pembayaran SPP
    $spp_stmt = $pdo->prepare("SELECT * FROM spp_pembayaran WHERE siswa_id = ? ORDER BY tahun DESC, bulan DESC");
    $spp_stmt->execute([$siswa_id]);
    $spp_payments = $spp_stmt->fetchAll();

    // Petakan pembayaran SPP untuk pencarian cepat
    $payment_map = [];
    foreach ($spp_payments as $payment) {
        $payment_map[$payment['tahun'] . '-' . $payment['bulan']] = $payment;
    }

    // Hitung status pembayaran SPP 12 Bulan Terakhir secara dinamis
    $months_billing = [];
    $current_time = time();
    for ($i = 11; $i >= 0; $i--) {
        $time = strtotime("-$i months", $current_time);
        $m_num = (int)date('n', $time);
        $y_num = (int)date('Y', $time);
        $months_billing[] = ['bulan' => $m_num, 'tahun' => $y_num];
    }

    $total_tunggakan = 0;
    $unpaid_months_count = 0;
    $tarif_spp = (float)($siswa['tarif_spp'] ?? 500000.00);

    foreach ($months_billing as $mb) {
        $key = $mb['tahun'] . '-' . $mb['bulan'];
        if (!isset($payment_map[$key]) || $payment_map[$key]['status_bayar'] !== 'Lunas') {
            $total_tunggakan += $tarif_spp;
            $unpaid_months_count++;
        }
    }

    // 5. Ambil Ringkasan Kehadiran
    $att_summary_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as jumlah 
        FROM presensi_siswa 
        WHERE siswa_id = ? 
        GROUP BY status
    ");
    $att_summary_stmt->execute([$siswa_id]);
    $att_summary_raw = $att_summary_stmt->fetchAll();
    
    $att_summary = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
    $total_att_days = 0;
    foreach ($att_summary_raw as $raw) {
        $att_summary[$raw['status']] = (int)$raw['jumlah'];
        $total_att_days += (int)$raw['jumlah'];
    }

    // 6. Ambil Log Kehadiran Terakhir
    $att_logs_stmt = $pdo->prepare("
        SELECT tanggal, status, keterangan 
        FROM presensi_siswa 
        WHERE siswa_id = ? 
        ORDER BY tanggal DESC 
        LIMIT 15
    ");
    $att_logs_stmt->execute([$siswa_id]);
    $att_logs = $att_logs_stmt->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat data portal orang tua: " . $e->getMessage());
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Orang Tua - Master Data Sekolah</title>
    <!-- Google Fonts Plus Jakarta Sans & Outfit -->
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
        /* CSS Tambahan khusus Portal Orang Tua agar responsif maksimal di HP */
        .parent-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }
        .nav-pills .nav-link {
            border-radius: 12px;
            font-weight: 600;
            color: var(--text-muted);
            border: 1px solid transparent;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link.active {
            background: var(--primary-gradient);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
            color: #ffffff !important;
        }
        .nav-pills .nav-link:hover:not(.active) {
            background: rgba(99, 102, 241, 0.05);
            color: var(--primary-color);
        }
        .stat-card-parent {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease;
        }
        .stat-card-parent:hover {
            transform: translateY(-2px);
        }
        .profile-hero-card {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            border-radius: 20px;
            color: #ffffff;
            border: none;
            box-shadow: 0 10px 25px rgba(49, 46, 129, 0.15);
        }
        [data-bs-theme="dark"] .stat-card-parent {
            background-color: var(--bg-card);
        }
        /* Mobile horizontal scroll support for pills */
        .scroll-pills {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 5px;
        }
        .scroll-pills::-webkit-scrollbar {
            display: none;
        }
        .scroll-pills .nav-item {
            flex: 0 0 auto;
            margin-right: 8px;
        }
        @media (max-width: 576px) {
            .profile-hero-card .profile-img-container {
                width: 100px !important;
                height: 100px !important;
            }
        }
    </style>
</head>
<body>

<!-- Header / Topbar Navigation -->
<nav class="navbar navbar-expand-lg top-nav px-3 py-3 border-bottom sticky-top bg-body shadow-sm" style="backdrop-filter: blur(10px); background: rgba(var(--bg-card), 0.9);">
    <div class="container max-width-1200 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-mortarboard-fill text-primary fs-3"></i>
            <div>
                <h5 class="m-0 fw-bold text-dark-emphasis" style="font-size: 16px;"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></h5>
                <span class="text-muted small" style="font-size: 11px;">Portal Monitoring Wali Murid</span>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <!-- Theme Toggle Switch -->
            <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                <i class="bi bi-sun-fill text-warning"></i>
                <input class="form-check-input" type="checkbox" role="switch" id="theme-toggle">
                <i class="bi bi-moon-stars-fill text-primary"></i>
            </div>
            
            <div class="vr text-secondary"></div>
            
            <!-- Logout Button -->
            <a href="logout.php" class="btn btn-outline-danger btn-sm fw-bold d-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Keluar</span>
            </a>
        </div>
    </div>
</nav>

<div class="parent-container">
    <!-- Student Header Summary Card -->
    <div class="card profile-hero-card p-4 mb-4">
        <div class="row align-items-center g-3 text-center text-md-start">
            <div class="col-12 col-md-auto d-flex justify-content-center">
                <div class="profile-img-container bg-white bg-opacity-10 d-flex align-items-center justify-content-center border border-white border-opacity-20 rounded-circle overflow-hidden" style="width: 120px; height: 120px;">
                    <?php if (!empty($siswa['foto']) && file_exists('../' . $siswa['foto'])): ?>
                        <img src="../<?php echo htmlspecialchars($siswa['foto']); ?>" alt="Foto" style="width:100%; height:100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person text-white-50" style="font-size: 4rem;"></i>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-12 col-md">
                <span class="badge bg-warning text-dark fw-bold mb-2 px-3 py-1 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">Kelas <?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Diatur'); ?></span>
                <h3 class="fw-bold mb-1 text-white"><?php echo htmlspecialchars($siswa['nama']); ?></h3>
                <p class="text-white-50 small mb-2">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?> &nbsp;|&nbsp; NIS: <?php echo htmlspecialchars($siswa['nis']); ?></p>
                <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2">
                    <span class="badge bg-white bg-opacity-15 text-white"><i class="bi bi-calendar-check me-1"></i> Angkatan: <?php echo htmlspecialchars($siswa['tahun_masuk']); ?></span>
                    <span class="badge bg-white bg-opacity-15 text-white"><i class="bi bi-gender-ambiguous me-1"></i> <?php echo $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Summary Row -->
    <div class="row g-3 mb-4">
        <!-- Attendance Widget -->
        <div class="col-12 col-md-4">
            <?php 
            $rate = $total_att_days > 0 ? round(($att_summary['Hadir'] / $total_att_days) * 100) : 100;
            $rate_color = 'text-success';
            if ($rate < 90) $rate_color = 'text-warning';
            if ($rate < 75) $rate_color = 'text-danger';
            ?>
            <div class="card stat-card-parent bg-body p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold d-block">Persentase Kehadiran</span>
                    <h3 class="fw-bold m-0 mt-1 <?php echo $rate_color; ?>"><?php echo $rate; ?>%</h3>
                    <span class="text-muted small" style="font-size:11px;"><?php echo $att_summary['Hadir']; ?> dari <?php echo $total_att_days; ?> hari sekolah</span>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="bi bi-calendar-check-fill fs-3"></i>
                </div>
            </div>
        </div>
        
        <!-- SPP Bills Widget -->
        <div class="col-12 col-md-4">
            <div class="card stat-card-parent bg-body p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold d-block">Tunggakan SPP (12 Bln)</span>
                    <h3 class="fw-bold m-0 mt-1 <?php echo $total_tunggakan > 0 ? 'text-danger' : 'text-success'; ?>">
                        Rp <?php echo number_format($total_tunggakan, 0, ',', '.'); ?>
                    </h3>
                    <span class="text-muted small" style="font-size:11px;"><?php echo $unpaid_months_count; ?> bulan belum dibayar</span>
                </div>
                <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                    <i class="bi bi-wallet2 fs-3"></i>
                </div>
            </div>
        </div>

        <!-- Grade Average Widget -->
        <div class="col-12 col-md-4">
            <?php 
            $avg_score = 0;
            if (!empty($grades)) {
                $sum = 0;
                foreach ($grades as $g) $sum += (float)$g['nilai_akhir'];
                $avg_score = round($sum / count($grades), 1);
            }
            ?>
            <div class="card stat-card-parent bg-body p-3 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold d-block">Rata-rata Rapor</span>
                    <h3 class="fw-bold m-0 mt-1 text-primary"><?php echo $avg_score > 0 ? $avg_score : '-'; ?></h3>
                    <span class="text-muted small" style="font-size:11px;"><?php echo count($grades); ?> mata pelajaran terinput</span>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                    <i class="bi bi-journal-bookmark-fill fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Responsive Tab Navigation (Mobile scrollable) -->
    <div class="mb-3">
        <ul class="nav nav-pills scroll-pills border p-2 bg-body rounded-4" id="parentTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
                    <i class="bi bi-grid-fill me-1"></i> Ringkasan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="spp-tab" data-bs-toggle="tab" data-bs-target="#spp" type="button" role="tab" aria-controls="spp" aria-selected="false">
                    <i class="bi bi-wallet2 me-1"></i> SPP Bulanan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="grades-tab" data-bs-toggle="tab" data-bs-target="#grades" type="button" role="tab" aria-controls="grades" aria-selected="false">
                    <i class="bi bi-journal-bookmark-fill me-1"></i> Akademik & Nilai
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false">
                    <i class="bi bi-calendar-check me-1"></i> Kehadiran
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents Wrapper -->
    <div class="tab-content" id="parentTabContent">
        
        <!-- Tab 1: Ringkasan (Overview) -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
            <div class="row g-4">
                <!-- Detailed Profile Card -->
                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body">
                        <h5 class="fw-bold mb-4 text-dark-emphasis"><i class="bi bi-person-lines-fill text-primary me-2"></i> Biodata Lengkap Anak</h5>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Tempat & Tanggal Lahir</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($siswa['tempat_lahir'] . ', ' . date('d F Y', strtotime($siswa['tanggal_lahir']))); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Agama</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($siswa['agama']); ?></span>
                            </div>
                            
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Nomor HP Siswa</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($siswa['no_hp']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Email Siswa</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo !empty($siswa['email']) ? htmlspecialchars($siswa['email']) : '-'; ?></span>
                            </div>
                            
                            <div class="col-12">
                                <span class="text-muted small d-block">Alamat Tinggal</span>
                                <span class="fw-semibold d-block mt-1 p-2 bg-light rounded text-dark-emphasis" style="font-size: 14px;">
                                    <?php echo nl2br(htmlspecialchars($siswa['alamat'])); ?>
                                </span>
                            </div>
                            
                            <hr class="my-4">
                            <h6 class="fw-bold text-dark-emphasis mt-1 mb-3"><i class="bi bi-people-fill text-success me-2"></i>Data Orang Tua / Wali</h6>
                            
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Nama Ayah</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($siswa['nama_ayah']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Nama Ibu</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($siswa['nama_ibu']); ?></span>
                            </div>
                            
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">No HP Orang Tua</span>
                                <span class="fw-semibold text-dark-emphasis"><?php echo !empty($siswa['no_hp_ortu']) ? htmlspecialchars($siswa['no_hp_ortu']) : '-'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box Card / Alerts -->
                <div class="col-12 col-lg-5">
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body mb-4">
                        <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-megaphone-fill text-warning me-2"></i> Pengumuman Sekolah</h5>
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="badge bg-primary mb-2">Informasi</span>
                            <p class="small text-muted mb-0" style="line-height: 1.5;">
                                Untuk mempermudah proses verifikasi pembayaran SPP bulanan, silakan melampirkan bukti transfer dan melakukan konfirmasi ke Bendahara melalui WhatsApp.
                            </p>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body">
                        <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-telephone-fill text-primary me-2"></i> Kontak Wali Kelas</h5>
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="bi bi-chat-left-text-fill fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark-emphasis mb-1">Informasi & Konsultasi</h6>
                                <p class="small text-muted mb-0">Hubungi sekolah untuk konsultasi mengenai perkembangan belajar siswa.</p>
                            </div>
                        </div>
                        <div class="mt-4 border-top pt-3 text-center">
                            <span class="text-muted small d-block">Nomor Telepon Sekolah:</span>
                            <span class="fw-bold text-primary fs-5"><?php echo htmlspecialchars($settings['no_telp'] ?? '-'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Pembayaran SPP -->
        <div class="tab-pane fade" id="spp" role="tabpanel" aria-labelledby="spp-tab">
            <div class="row g-4">
                <!-- SPP Summary and Bank Details -->
                <div class="col-12 col-lg-5">
                    <!-- Billing Summary -->
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body mb-4">
                        <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-calculator-fill text-primary me-2"></i> Ringkasan Tagihan</h5>
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-2">
                            <span class="text-muted">Tarif SPP Bulanan</span>
                            <span class="fw-semibold text-dark-emphasis">Rp <?php echo number_format($tarif_spp, 0, ',', '.'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-2">
                            <span class="text-muted">Bulan Belum Lunas</span>
                            <span class="fw-bold text-danger"><?php echo $unpaid_months_count; ?> Bulan</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <span class="h6 fw-bold m-0 text-dark-emphasis">Total Tunggakan</span>
                            <span class="h5 fw-bold m-0 text-danger">Rp <?php echo number_format($total_tunggakan, 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <!-- Bank Transfer Details -->
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body">
                        <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-bank text-success me-2"></i> Info Rekening Sekolah</h5>
                        <p class="small text-muted mb-4">Pembayaran SPP dapat ditransfer ke rekening bank resmi sekolah berikut:</p>
                        
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <span class="text-muted small d-block">Nama Bank</span>
                            <span class="fw-bold text-dark-emphasis fs-6"><?php echo htmlspecialchars($settings['nama_bank'] ?? '-'); ?></span>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 border mb-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small d-block">Nomor Rekening</span>
                                <span class="fw-bold text-dark-emphasis fs-5 font-monospace" id="rek-num"><?php echo htmlspecialchars($settings['nomor_rekening'] ?? '-'); ?></span>
                            </div>
                            <button class="btn btn-outline-primary btn-sm rounded-3" onclick="copyRekening()">
                                <i class="bi bi-copy"></i> Salin
                            </button>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small d-block">Atas Nama</span>
                            <span class="fw-bold text-dark-emphasis fs-6"><?php echo htmlspecialchars($settings['nama_rekening'] ?? '-'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- 12 Months SPP Timeline/List -->
                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body">
                        <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-calendar-range text-primary me-2"></i> Status SPP 12 Bulan Terakhir</h5>
                        <p class="small text-muted">Daftar tagihan dan pembayaran SPP Anda dalam rentang 12 bulan terakhir:</p>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Periode Bulan</th>
                                        <th>Tagihan</th>
                                        <th>Status</th>
                                        <th class="text-end">Aksi/Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($months_billing as $mb): 
                                        $key = $mb['tahun'] . '-' . $mb['bulan'];
                                        $is_paid = isset($payment_map[$key]) && $payment_map[$key]['status_bayar'] === 'Lunas';
                                    ?>
                                        <tr>
                                            <td class="fw-semibold text-dark-emphasis">
                                                <?php echo $month_names[$mb['bulan']] . ' ' . $mb['tahun']; ?>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                Rp <?php echo number_format($tarif_spp, 0, ',', '.'); ?>
                                            </td>
                                            <td>
                                                <?php if ($is_paid): ?>
                                                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle-fill me-1"></i> Lunas</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-x-circle-fill me-1"></i> Belum Bayar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($is_paid): ?>
                                                    <a href="../spp/invoice.php?token=<?php echo htmlspecialchars($payment_map[$key]['invoice_token']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3">
                                                        <i class="bi bi-receipt"></i> Kuitansi
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Silakan Transfer</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Akademik & Nilai -->
        <div class="tab-pane fade" id="grades" role="tabpanel" aria-labelledby="grades-tab">
            <div class="card shadow-sm border-0 rounded-4 p-4 bg-body">
                <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-journal-bookmark-fill text-success me-2"></i> Hasil Belajar Rapor</h5>
                
                <?php 
                // Group grades by Academic Year & Semester
                $grouped_grades = [];
                foreach ($grades as $grade) {
                    $group_key = $grade['tahun_ajaran'] . ' - ' . $grade['semester'];
                    $grouped_grades[$group_key][] = $grade;
                }
                
                if (empty($grouped_grades)): 
                ?>
                    <div class="text-center py-5 text-muted border border-dashed rounded-4">
                        <i class="bi bi-journal-x fs-1 text-secondary"></i>
                        <p class="mt-2 mb-0">Belum ada catatan nilai rapor terdaftar untuk siswa ini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_grades as $semester_name => $semester_grades): ?>
                        <div class="mb-5">
                            <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center" style="font-size: 15px;">
                                <span><i class="bi bi-calendar3 me-2"></i> Tahun Ajaran <?php echo htmlspecialchars($semester_name); ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                    Rata-rata: <?php 
                                    $sum = 0;
                                    foreach ($semester_grades as $sg) $sum += (float)$sg['nilai_akhir'];
                                    echo number_format($sum / count($semester_grades), 1);
                                    ?>
                                </span>
                            </h6>
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Tugas (30%)</th>
                                            <th>UTS (30%)</th>
                                            <th>UAS (40%)</th>
                                            <th>Nilai Akhir</th>
                                            <th>Predikat</th>
                                            <th>Keterangan / Feedback Guru</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($semester_grades as $grade):
                                            $final = (float)$grade['nilai_akhir'];
                                            if ($final >= 85) {
                                                $badge_class = 'bg-success-subtle text-success';
                                                $predikat = 'A (Sangat Baik)';
                                            } elseif ($final >= 75) {
                                                $badge_class = 'bg-primary-subtle text-primary';
                                                $predikat = 'B (Baik)';
                                            } elseif ($final >= 60) {
                                                $badge_class = 'bg-warning-subtle text-warning';
                                                $predikat = 'C (Cukup)';
                                            } else {
                                                $badge_class = 'bg-danger-subtle text-danger';
                                                $predikat = 'D (Kurang)';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td class="fw-bold text-dark-emphasis"><?php echo htmlspecialchars($grade['mata_pelajaran']); ?></td>
                                                <td><?php echo number_format($grade['nilai_tugas'], 1); ?></td>
                                                <td><?php echo number_format($grade['nilai_uts'], 1); ?></td>
                                                <td><?php echo number_format($grade['nilai_uas'], 1); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?> font-monospace px-3 py-1" style="font-size: 13px;">
                                                        <?php echo number_format($grade['nilai_akhir'], 1); ?>
                                                    </span>
                                                </td>
                                                <td><span class="small fw-semibold"><?php echo $predikat; ?></span></td>
                                                <td class="small text-muted text-wrap" style="max-width: 250px;">
                                                    <?php echo !empty($grade['keterangan']) ? htmlspecialchars($grade['keterangan']) : '-'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 4: Kehadiran (Presensi) -->
        <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
            <div class="row g-4">
                <!-- Attendance Stats Widget -->
                <div class="col-12 col-md-5 col-lg-4">
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body text-center">
                        <h5 class="fw-bold mb-4 text-dark-emphasis text-start"><i class="bi bi-pie-chart-fill text-warning me-2"></i> Rekapitulasi</h5>
                        
                        <!-- Circular Rate Display -->
                        <div class="d-inline-flex align-items-center justify-content-center border border-5 <?php echo $rate < 90 ? 'border-warning' : 'border-success'; ?> rounded-circle mb-4 mx-auto" style="width: 130px; height: 130px;">
                            <div>
                                <h2 class="fw-bold m-0 <?php echo $rate < 90 ? 'text-warning' : 'text-success'; ?>"><?php echo $rate; ?>%</h2>
                                <span class="text-muted small" style="font-size: 10px;">Hadir</span>
                            </div>
                        </div>

                        <!-- Mini stats grid -->
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 border rounded bg-success-subtle text-success-emphasis text-center">
                                    <span class="d-block small text-muted">Hadir</span>
                                    <h4 class="fw-bold m-0 mt-1"><?php echo $att_summary['Hadir']; ?></h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded bg-primary-subtle text-primary-emphasis text-center">
                                    <span class="d-block small text-muted">Sakit</span>
                                    <h4 class="fw-bold m-0 mt-1"><?php echo $att_summary['Sakit']; ?></h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded bg-warning-subtle text-warning-emphasis text-center">
                                    <span class="d-block small text-muted">Izin</span>
                                    <h4 class="fw-bold m-0 mt-1"><?php echo $att_summary['Izin']; ?></h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded bg-danger-subtle text-danger-emphasis text-center">
                                    <span class="d-block small text-muted">Alpa</span>
                                    <h4 class="fw-bold m-0 mt-1"><?php echo $att_summary['Alpa']; ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Absences & Attendance Logs -->
                <div class="col-12 col-md-7 col-lg-8">
                    <div class="card shadow-sm border-0 rounded-4 p-4 bg-body">
                        <h5 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-clock-history text-primary me-2"></i> Catatan Absensi Terakhir</h5>
                        <p class="small text-muted">Daftar riwayat kehadiran harian anak Anda (Hingga 15 log terakhir):</p>
                        
                        <?php if (empty($att_logs)): ?>
                            <div class="text-center py-5 text-muted border border-dashed rounded-4">
                                <i class="bi bi-calendar-x fs-2 text-secondary"></i>
                                <p class="mt-2 mb-0">Belum ada data presensi siswa yang dicatatkan oleh guru.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Hari & Tanggal</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $day_names = [
                                            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
                                            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                                        ];
                                        foreach ($att_logs as $log):
                                            $day_en = date('l', strtotime($log['tanggal']));
                                            $day_id = $day_names[$day_en] ?? $day_en;
                                            $formatted_date = $day_id . ', ' . date('d-m-Y', strtotime($log['tanggal']));
                                            
                                            $badge_class = 'bg-secondary';
                                            if ($log['status'] === 'Hadir') $badge_class = 'bg-success-subtle text-success-emphasis';
                                            elseif ($log['status'] === 'Sakit') $badge_class = 'bg-primary-subtle text-primary-emphasis';
                                            elseif ($log['status'] === 'Izin') $badge_class = 'bg-warning-subtle text-warning-emphasis';
                                            elseif ($log['status'] === 'Alpa') $badge_class = 'bg-danger-subtle text-danger-emphasis';
                                        ?>
                                            <tr>
                                                <td class="fw-semibold text-dark-emphasis"><?php echo $formatted_date; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['status']); ?></span>
                                                </td>
                                                <td class="small text-muted">
                                                    <?php echo !empty($log['keterangan']) ? htmlspecialchars($log['keterangan']) : '-'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer Copyright -->
    <footer class="mt-5 py-4 border-top text-center text-muted small">
        <p class="m-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?>. Semua Hak Cipta Dilindungi.</p>
        <p class="m-0 text-muted" style="font-size: 10px;">Diproses oleh Nginx & PHP-FPM - Stabil untuk RAM 4GB</p>
    </footer>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Dark Mode Toggle Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const htmlElement = document.documentElement;
        const themeToggle = document.getElementById('theme-toggle');
        
        // Cek LocalStorage
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            htmlElement.setAttribute('data-bs-theme', 'dark');
            if (themeToggle) themeToggle.checked = true;
        } else {
            htmlElement.setAttribute('data-bs-theme', 'light');
            if (themeToggle) themeToggle.checked = false;
        }
        
        if (themeToggle) {
            themeToggle.addEventListener('change', () => {
                if (themeToggle.checked) {
                    htmlElement.setAttribute('data-bs-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    htmlElement.setAttribute('data-bs-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });
        }
    });

    // Fungsi salin nomor rekening ke clipboard
    function copyRekening() {
        const rekNumText = document.getElementById('rek-num').innerText;
        navigator.clipboard.writeText(rekNumText).then(() => {
            alert('Nomor Rekening berhasil disalin: ' + rekNumText);
        }).catch(err => {
            console.error('Gagal menyalin rekening: ', err);
        });
    }
</script>
</body>
</html>
