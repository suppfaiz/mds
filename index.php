<?php
$path_prefix = './';
$page_title = 'Dashboard';
$active_menu = 'dashboard';

require_once 'config/db.php';
require_once 'includes/auth_check.php';

// Protect the page
checkLoginRoot();

// Retrieve counts
try {
    // Siswa count
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM siswa");
    $total_siswa = $stmt->fetch()['total'];

    // Guru count
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM guru");
    $total_guru = $stmt->fetch()['total'];

    // Karyawan count
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM karyawan");
    $total_karyawan = $stmt->fetch()['total'];

    // Dokumen count
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM dokumen");
    $total_dokumen = $stmt->fetch()['total'];

    // Kelas count
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM kelas");
    $total_kelas = $stmt->fetch()['total'];

    // PMB counts
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pmb_pendaftar");
    $total_pmb = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pmb_pendaftar WHERE status = 'Pending'");
    $total_pmb_pending = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pmb_pendaftar WHERE status = 'Diterima'");
    $total_pmb_diterima = $stmt->fetch()['total'] ?? 0;

    // Today's student attendance percentage
    $today_date = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            COUNT(*) AS total 
        FROM presensi_siswa 
        WHERE tanggal = ?
    ");
    $stmt->execute([$today_date]);
    $today_att = $stmt->fetch();
    $today_hadir = $today_att['hadir'] ?? 0;
    $today_logged = $today_att['total'] ?? 0;
    $today_attendance_pct = $today_logged > 0 ? round(($today_hadir / $today_logged) * 100) : null;

    // Payroll paid current month
    $curr_month = (int)date('m');
    $curr_year = (int)date('Y');
    $stmt = $pdo->prepare("SELECT SUM(gaji_bersih) AS total FROM payroll WHERE bulan = ? AND tahun = ? AND status_bayar = 'Dibayar'");
    $stmt->execute([$curr_month, $curr_year]);
    $total_payroll_paid = $stmt->fetch()['total'] ?? 0;
    
    // Class distribution for progress bar chart
    $stmt = $pdo->query("
        SELECT k.nama_kelas, COUNT(s.id) AS jumlah 
        FROM kelas k 
        LEFT JOIN siswa s ON k.id = s.kelas_id 
        GROUP BY k.id 
        LIMIT 5
    ");
    $kelas_distribution = $stmt->fetchAll();

    // Gender distribution query
    $stmt = $pdo->query("SELECT jenis_kelamin, COUNT(*) AS jumlah FROM siswa GROUP BY jenis_kelamin");
    $gender_counts = $stmt->fetchAll();
    $genders = ['L' => 0, 'P' => 0];
    foreach ($gender_counts as $gc) {
        $genders[$gc['jenis_kelamin']] = (int)$gc['jumlah'];
    }

    // Monthly payroll expense trends (last 6 records)
    $payroll_trends = [];
    $spp_trends = [];
    if ($_SESSION['role'] !== 'guru') {
        // Combined expenses trends (Payroll + manual cash out)
        $stmt = $pdo->query("
            SELECT bulan, tahun, SUM(total) AS total
            FROM (
                SELECT bulan, tahun, SUM(gaji_bersih) AS total 
                FROM payroll 
                WHERE status_bayar = 'Dibayar' 
                GROUP BY tahun, bulan 
                UNION ALL
                SELECT MONTH(tanggal) AS bulan, YEAR(tanggal) AS tahun, SUM(nominal) AS total
                FROM keuangan_transaksi
                WHERE tipe = 'Pengeluaran'
                GROUP BY YEAR(tanggal), MONTH(tanggal)
            ) combined_exp
            GROUP BY tahun, bulan 
            ORDER BY tahun ASC, bulan ASC 
            LIMIT 12
        ");
        $payroll_trends = $stmt->fetchAll();

        // Combined income trends (SPP + manual cash in)
        $stmt_spp = $pdo->query("
            SELECT bulan, tahun, SUM(total) AS total
            FROM (
                SELECT bulan, tahun, SUM(jumlah_bayar) AS total 
                FROM spp_pembayaran 
                WHERE status_bayar = 'Lunas' 
                GROUP BY tahun, bulan 
                UNION ALL
                SELECT MONTH(tanggal) AS bulan, YEAR(tanggal) AS tahun, SUM(nominal) AS total
                FROM keuangan_transaksi
                WHERE tipe = 'Pemasukan'
                GROUP BY YEAR(tanggal), MONTH(tanggal)
            ) combined_inc
            GROUP BY tahun, bulan 
            ORDER BY tahun ASC, bulan ASC 
            LIMIT 12
        ");
        $spp_trends = $stmt_spp->fetchAll();
    }

    // Recent Audit Logs
    $recent_logs = [];
    if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'kepala_sekolah') {
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY tanggal_akses DESC LIMIT 5");
        $recent_logs = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat data dashboard: ' . $e->getMessage();
}

$month_names_short = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

// Map chart datasets
$class_labels = [];
$class_values = [];
foreach ($kelas_distribution as $kd) {
    $class_labels[] = $kd['nama_kelas'];
    $class_values[] = (int)$kd['jumlah'];
}

// Combine trends chronologically
$financial_periods = []; // key: "tahun-bulan"
foreach ($payroll_trends as $pt) {
    $key = $pt['tahun'] . '-' . str_pad($pt['bulan'], 2, '0', STR_PAD_LEFT);
    $financial_periods[$key] = [
        'bulan' => (int)$pt['bulan'],
        'tahun' => (int)$pt['tahun'],
        'payroll' => (float)$pt['total'],
        'spp' => 0.0
    ];
}

foreach ($spp_trends as $st) {
    $key = $st['tahun'] . '-' . str_pad($st['bulan'], 2, '0', STR_PAD_LEFT);
    if (isset($financial_periods[$key])) {
        $financial_periods[$key]['spp'] = (float)$st['total'];
    } else {
        $financial_periods[$key] = [
            'bulan' => (int)$st['bulan'],
            'tahun' => (int)$st['tahun'],
            'payroll' => 0.0,
            'spp' => (float)$st['total']
        ];
    }
}

// Sort key chronologically
ksort($financial_periods);

// Limit to last 6 periods if there are more
$financial_periods = array_slice($financial_periods, -6, 6, true);

$fin_labels = [];
$fin_payroll = [];
$fin_spp = [];
foreach ($financial_periods as $key => $data) {
    $fin_labels[] = $month_names_short[$data['bulan']] . ' ' . $data['tahun'];
    $fin_payroll[] = $data['payroll'];
    $fin_spp[] = $data['spp'];
}

include 'includes/header.php';
?>

<div class="row g-3 mb-4">
    <!-- Card Siswa -->
    <div class="col-12 col-sm-6 col-md-4 col-xl-2">
        <div class="card stat-card border-start border-primary border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Siswa</span>
                    <h3 class="mt-1 mb-0 fw-bold"><?php echo number_format($total_siswa); ?></h3>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                    <i class="bi bi-people fs-4"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <a href="siswa/index.php" class="text-decoration-none small text-primary fw-medium" style="font-size: 11px;">
                    Detail <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Card Guru -->
    <div class="col-12 col-sm-6 col-md-4 col-xl-2">
        <div class="card stat-card border-start border-success border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Guru</span>
                    <h3 class="mt-1 mb-0 fw-bold"><?php echo number_format($total_guru); ?></h3>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">
                    <i class="bi bi-person-badge fs-4"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <a href="guru/index.php" class="text-decoration-none small text-success fw-medium" style="font-size: 11px;">
                    Detail <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Card Karyawan -->
    <div class="col-12 col-sm-6 col-md-4 col-xl-2">
        <div class="card stat-card border-start border-info border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Karyawan</span>
                    <h3 class="mt-1 mb-0 fw-bold"><?php echo number_format($total_karyawan); ?></h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info rounded-3 p-2">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <a href="karyawan/index.php" class="text-decoration-none small text-info fw-medium" style="font-size: 11px;">
                    Detail <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Card Kelas -->
    <div class="col-12 col-sm-6 col-md-4 col-xl-2">
        <div class="card stat-card border-start border-secondary border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Kelas</span>
                    <h3 class="mt-1 mb-0 fw-bold"><?php echo number_format($total_kelas); ?></h3>
                </div>
                <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 p-2">
                    <i class="bi bi-building fs-4"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <a href="kelas/index.php" class="text-decoration-none small text-secondary fw-medium" style="font-size: 11px;">
                    Detail <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Card Presensi Siswa Hari Ini -->
    <div class="col-12 col-sm-6 col-md-4 col-xl-2">
        <div class="card stat-card border-start border-warning border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Presensi Siswa Hari Ini</span>
                    <h3 class="mt-1 mb-0 fw-bold"><?php echo $today_attendance_pct !== null ? $today_attendance_pct . '%' : 'Belum Diisi'; ?></h3>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2">
                    <i class="bi bi-calendar-check fs-4"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <a href="presensi/siswa.php" class="text-decoration-none small text-warning fw-medium" style="font-size: 11px;">
                    Kelola <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Card Dokumen -->
    <div class="col-12 col-sm-6 col-md-4 col-xl-2">
        <div class="card stat-card border-start border-dark border-4 h-100 shadow-sm">
            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Dokumen</span>
                    <h3 class="mt-1 mb-0 fw-bold"><?php echo number_format($total_dokumen); ?></h3>
                </div>
                <div class="bg-dark bg-opacity-10 text-dark rounded-3 p-2">
                    <i class="bi bi-file-earmark-pdf fs-4"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                <span class="text-muted small" style="font-size: 11px;">Arsip Digital</span>
            </div>
        </div>
    </div>

    <!-- Card PMB -->
    <?php if ($_SESSION['role'] !== 'guru'): ?>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2">
            <div class="card stat-card border-start border-primary border-4 h-100 shadow-sm">
                <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Pendaftar PMB</span>
                        <h3 class="mt-1 mb-0 fw-bold"><?php echo number_format($total_pmb); ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                        <i class="bi bi-person-plus-fill fs-4"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3 d-flex justify-content-between align-items-center">
                    <a href="pmb/index.php" class="text-decoration-none small text-primary fw-medium" style="font-size: 11px;">
                        Detail <i class="bi bi-arrow-right"></i>
                    </a>
                    <span class="text-muted" style="font-size: 9.5px;">Pending: <?php echo $total_pmb_pending; ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Card Payroll -->
    <?php if ($_SESSION['role'] !== 'guru'): ?>
        <div class="col-12 col-sm-6 col-md-4 col-xl-2">
            <div class="card stat-card border-start border-danger border-4 h-100 shadow-sm">
                <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Gaji Dibayar (Bln Ini)</span>
                        <h6 class="mt-1 mb-0 fw-bold text-danger" style="font-size: 13px;">Rp <?php echo number_format($total_payroll_paid, 0, ',', '.'); ?></h6>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-2">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-2 px-3">
                    <a href="payroll/index.php" class="text-decoration-none small text-danger fw-medium" style="font-size: 11px;">
                        Detail <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js CDN Import -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Analytics Charts Row -->
<div class="row g-4 mb-4">
    <!-- Chart Kelas (Bar) -->
    <div class="col-12 col-md-6 col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-primary me-2"></i> Distribusi Siswa per Kelas</h5>
            </div>
            <div class="card-body p-4" style="height: 320px; position: relative;">
                <canvas id="classChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Chart Gender (Doughnut) -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart text-success me-2"></i> Proporsi Gender Siswa</h5>
            </div>
            <div class="card-body p-4" style="height: 320px; position: relative;">
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart Perbandingan Keuangan (Line) -->
    <?php if ($_SESSION['role'] !== 'guru' && (!empty($payroll_trends) || !empty($spp_trends))): ?>
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-arrow-left-right text-success me-2"></i> Perbandingan Keuangan: Arus Kas Masuk vs Kas Keluar (6 Bulan Terakhir)</h5>
                </div>
                <div class="card-body p-4" style="height: 300px; position: relative;">
                    <canvas id="financeChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Welcome & Info Panel -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0 h-100 p-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-mortarboard fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1">Selamat Datang di Master Data Sekolah!</h4>
                        <p class="text-muted mb-0">Platform ini berfungsi sebagai repositori sentral data akademik sekolah.</p>
                    </div>
                </div>
                
                <h5 class="fw-bold mb-3 mt-4">Statistik Kelas (Sampel)</h5>
                <?php if (empty($kelas_distribution)): ?>
                    <p class="text-muted small">Belum ada data kelas yang terdaftar.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php 
                        $max_siswa = 0;
                        foreach($kelas_distribution as $kd) {
                            if ($kd['jumlah'] > $max_siswa) $max_siswa = $kd['jumlah'];
                        }
                        // Avoid division by zero
                        $max_siswa = $max_siswa > 0 ? $max_siswa : 1;
                        
                        foreach($kelas_distribution as $kd): 
                            $pct = round(($kd['jumlah'] / $max_siswa) * 100);
                        ?>
                            <div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold"><?php echo htmlspecialchars($kd['nama_kelas']); ?></span>
                                    <span class="text-muted"><?php echo htmlspecialchars($kd['jumlah']); ?> Siswa</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary rounded" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Audit Logs or Action Cards -->
    <div class="col-12 col-lg-5">
        <?php if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'kepala_sekolah'): ?>
            <!-- Recent Audit Logs card -->
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Aktivitas Terakhir</h5>
                    <a href="logs/index.php" class="btn btn-sm btn-light border">Semua Log</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_logs)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle fs-3"></i>
                            <p class="mt-2 small">Belum ada aktivitas tercatat.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_logs as $log): ?>
                                <div class="list-group-item px-0 py-3 bg-transparent">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold text-primary-emphasis" style="font-size: 14px;">
                                                <?php echo htmlspecialchars($log['aktivitas']); ?>
                                            </h6>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($log['detail']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis small"><?php echo htmlspecialchars($log['username']); ?></span>
                                            <div class="text-muted" style="font-size: 10px; margin-top: 4px;">
                                                <?php echo date('H:i:s d/m/y', strtotime($log['tanggal_akses'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Helper panel for operators and teachers -->
            <div class="card shadow-sm border-0 h-100 p-3 bg-primary text-white" style="background: var(--primary-gradient) !important;">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-3">Panduan Pengguna Cepat</h5>
                        <p class="small text-white-50">Sebagai <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong>, Anda memiliki hak akses untuk mengelola data berikut:</p>
                        <ul class="small text-white-50">
                            <?php if ($_SESSION['role'] === 'operator'): ?>
                                <li>Melihat, menambah, mengedit, dan menghapus Data Siswa & Guru.</li>
                                <li>Mengunggah dokumen legal siswa dan guru.</li>
                                <li>Mencetak laporan profil & mengekspor ke Excel.</li>
                            <?php elseif ($_SESSION['role'] === 'guru'): ?>
                                <li>Melihat Data Siswa secara detail.</li>
                                <li>Mengunggah dokumen guru pribadi (KTP, NPWP, SK Mengajar).</li>
                                <li>Melihat daftar Guru pengajar.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="mt-4 pt-3 border-top border-white-50">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-white-50">API Integrasi Aktif</span>
                            <span class="badge bg-light text-primary">v1.0 (JSON)</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Class Distribution Chart
    const ctxClass = document.getElementById('classChart').getContext('2d');
    new Chart(ctxClass, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($class_labels); ?>,
            datasets: [{
                label: 'Jumlah Siswa',
                data: <?php echo json_encode($class_values); ?>,
                backgroundColor: 'rgba(79, 70, 229, 0.7)',
                borderColor: 'rgb(79, 70, 229)',
                borderWidth: 1.5,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 2. Gender Proportion Chart
    const ctxGender = document.getElementById('genderChart').getContext('2d');
    new Chart(ctxGender, {
        type: 'doughnut',
        data: {
            labels: ['Laki-laki', 'Perempuan'],
            datasets: [{
                data: [
                    <?php echo $genders['L']; ?>, 
                    <?php echo $genders['P']; ?>
                ],
                backgroundColor: ['#0284c7', '#db2777'],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 3. Financial Comparison Chart (Total Penerimaan vs Total Pengeluaran)
    <?php if ($_SESSION['role'] !== 'guru' && (!empty($payroll_trends) || !empty($spp_trends))): ?>
        const ctxFinance = document.getElementById('financeChart').getContext('2d');
        new Chart(ctxFinance, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($fin_labels); ?>,
                datasets: [
                    {
                        label: 'Total Penerimaan Kas (Rp)',
                        data: <?php echo json_encode($fin_spp); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#10b981'
                    },
                    {
                        label: 'Total Pengeluaran Kas (Rp)',
                        data: <?php echo json_encode($fin_payroll); ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#ef4444'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + context.raw.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
