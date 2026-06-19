<?php
$path_prefix = './';
$page_title = 'Dashboard';
$active_menu = 'dashboard';

require_once 'config/db.php';
require_once 'includes/auth_check.php';

checkLoginRoot();

// Retrieve counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM siswa");
    $total_siswa = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM guru");
    $total_guru = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM karyawan");
    $total_karyawan = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM dokumen");
    $total_dokumen = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM kelas");
    $total_kelas = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pmb_pendaftar");
    $total_pmb = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pmb_pendaftar WHERE status = 'Pending'");
    $total_pmb_pending = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pmb_pendaftar WHERE status = 'Diterima'");
    $total_pmb_diterima = $stmt->fetch()['total'] ?? 0;

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
    $today_hadir  = $today_att['hadir'] ?? 0;
    $today_logged = $today_att['total'] ?? 0;
    $today_attendance_pct = $today_logged > 0 ? round(($today_hadir / $today_logged) * 100) : null;

    $curr_month = (int)date('m');
    $curr_year  = (int)date('Y');
    $stmt = $pdo->prepare("SELECT SUM(gaji_bersih) AS total FROM payroll WHERE bulan = ? AND tahun = ? AND status_bayar = 'Dibayar'");
    $stmt->execute([$curr_month, $curr_year]);
    $total_payroll_paid = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("
        SELECT k.nama_kelas, COUNT(s.id) AS jumlah 
        FROM kelas k 
        LEFT JOIN siswa s ON k.id = s.kelas_id 
        GROUP BY k.id 
        LIMIT 6
    ");
    $kelas_distribution = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT jenis_kelamin, COUNT(*) AS jumlah FROM siswa GROUP BY jenis_kelamin");
    $gender_counts = $stmt->fetchAll();
    $genders = ['L' => 0, 'P' => 0];
    foreach ($gender_counts as $gc) {
        $genders[$gc['jenis_kelamin']] = (int)$gc['jumlah'];
    }

    $payroll_trends = [];
    $spp_trends = [];
    if ($_SESSION['role'] !== 'guru') {
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

    $recent_logs = [];
    if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'kepala_sekolah') {
        $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY tanggal_akses DESC LIMIT 6");
        $recent_logs = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat data dashboard: ' . $e->getMessage();
}

$month_names_short = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

$class_labels = [];
$class_values = [];
foreach ($kelas_distribution as $kd) {
    $class_labels[] = $kd['nama_kelas'];
    $class_values[] = (int)$kd['jumlah'];
}

$financial_periods = [];
foreach ($payroll_trends as $pt) {
    $key = $pt['tahun'] . '-' . str_pad($pt['bulan'], 2, '0', STR_PAD_LEFT);
    $financial_periods[$key] = ['bulan' => (int)$pt['bulan'], 'tahun' => (int)$pt['tahun'], 'payroll' => (float)$pt['total'], 'spp' => 0.0];
}
foreach ($spp_trends as $st) {
    $key = $st['tahun'] . '-' . str_pad($st['bulan'], 2, '0', STR_PAD_LEFT);
    if (isset($financial_periods[$key])) {
        $financial_periods[$key]['spp'] = (float)$st['total'];
    } else {
        $financial_periods[$key] = ['bulan' => (int)$st['bulan'], 'tahun' => (int)$st['tahun'], 'payroll' => 0.0, 'spp' => (float)$st['total']];
    }
}
ksort($financial_periods);
$financial_periods = array_slice($financial_periods, -6, 6, true);

$fin_labels   = [];
$fin_payroll  = [];
$fin_spp      = [];
foreach ($financial_periods as $data) {
    $fin_labels[]  = $month_names_short[$data['bulan']] . ' ' . $data['tahun'];
    $fin_payroll[] = $data['payroll'];
    $fin_spp[]     = $data['spp'];
}

// Greeting based on time
$hour = (int)date('H');
if ($hour < 11)      $greeting = 'Selamat Pagi';
elseif ($hour < 15)  $greeting = 'Selamat Siang';
elseif ($hour < 18)  $greeting = 'Selamat Sore';
else                 $greeting = 'Selamat Malam';

$user_name = htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin');
$role_labels = ['super_admin' => 'Super Admin', 'operator' => 'Operator', 'guru' => 'Guru', 'kepala_sekolah' => 'Kepala Sekolah'];
$user_role = $role_labels[$_SESSION['role']] ?? $_SESSION['role'];

include 'includes/header.php';
?>

<style>
/* ===== DASHBOARD CUSTOM STYLES ===== */

/* Hero Greeting Banner */
.dashboard-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 40%, #6d28d9 100%);
    border-radius: 20px;
    padding: 28px 32px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    color: white;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    top: -60px;
    right: -60px;
    width: 220px;
    height: 220px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}

.dashboard-hero::after {
    content: '';
    position: absolute;
    bottom: -80px;
    left: 20%;
    width: 180px;
    height: 180px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}

.hero-greeting {
    font-size: 13px;
    font-weight: 600;
    opacity: 0.7;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 4px;
}

.hero-name {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 8px;
    letter-spacing: -0.5px;
}

.hero-subtitle {
    font-size: 13px;
    opacity: 0.6;
    margin: 0;
}

.hero-date-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.15);
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 16px;
}

/* Stat Cards - Redesigned */
.stat-card-v2 {
    border-radius: 18px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 0;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    height: 100%;
}

.stat-card-v2:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,0.08);
}

[data-bs-theme="dark"] .stat-card-v2:hover {
    box-shadow: 0 16px 40px rgba(0,0,0,0.35);
}

.stat-card-body {
    padding: 20px 20px 16px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.stat-card-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.stat-card-value {
    font-size: 28px;
    font-weight: 800;
    line-height: 1;
    margin: 0;
    color: var(--text-primary);
    letter-spacing: -0.5px;
}

.stat-card-value.sm { font-size: 18px; }

.stat-card-footer {
    padding: 10px 20px;
    border-top: 1px solid var(--border-color);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.2s ease;
    background: transparent;
}

.stat-card-footer:hover { gap: 8px; }

/* Accent top bar per card */
.stat-card-v2.accent-indigo  { border-top: 3px solid #6366f1; }
.stat-card-v2.accent-green   { border-top: 3px solid #22c55e; }
.stat-card-v2.accent-sky     { border-top: 3px solid #0ea5e9; }
.stat-card-v2.accent-slate   { border-top: 3px solid #64748b; }
.stat-card-v2.accent-amber   { border-top: 3px solid #f59e0b; }
.stat-card-v2.accent-rose    { border-top: 3px solid #f43f5e; }
.stat-card-v2.accent-violet  { border-top: 3px solid #8b5cf6; }
.stat-card-v2.accent-teal    { border-top: 3px solid #14b8a6; }

/* Icon background colors */
.icon-indigo { background: rgba(99,102,241,0.1); color: #6366f1; }
.icon-green  { background: rgba(34,197,94,0.1);  color: #22c55e; }
.icon-sky    { background: rgba(14,165,233,0.1); color: #0ea5e9; }
.icon-slate  { background: rgba(100,116,139,0.1);color: #64748b; }
.icon-amber  { background: rgba(245,158,11,0.1); color: #f59e0b; }
.icon-rose   { background: rgba(244,63,94,0.1);  color: #f43f5e; }
.icon-violet { background: rgba(139,92,246,0.1); color: #8b5cf6; }
.icon-teal   { background: rgba(20,184,166,0.1); color: #14b8a6; }

/* Chart Cards */
.chart-card {
    border-radius: 18px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    overflow: hidden;
}

.chart-card-header {
    padding: 20px 24px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chart-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}

.chart-card-body {
    padding: 16px 20px 20px;
}

/* Class Progress Bars */
.class-bar-item {
    margin-bottom: 14px;
}

.class-bar-item:last-child { margin-bottom: 0; }

.class-bar-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    font-size: 13px;
}

.class-bar-name { font-weight: 600; color: var(--text-primary); }
.class-bar-count { font-size: 12px; color: var(--text-muted); font-weight: 600; }

.class-progress {
    height: 8px;
    background: rgba(99,102,241,0.08);
    border-radius: 10px;
    overflow: hidden;
}

.class-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 10px;
    transition: width 1s cubic-bezier(0.4,0,0.2,1);
}

/* Activity Log */
.activity-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
    align-items: flex-start;
}

.activity-item:last-child { border-bottom: none; padding-bottom: 0; }
.activity-item:first-child { padding-top: 0; }

.activity-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6366f1;
    margin-top: 5px;
    flex-shrink: 0;
}

.activity-action {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.4;
}

.activity-detail {
    font-size: 11.5px;
    color: var(--text-muted);
    margin: 2px 0 0;
}

.activity-meta {
    font-size: 10.5px;
    color: var(--text-muted);
    text-align: right;
    white-space: nowrap;
    flex-shrink: 0;
}

.activity-user {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    background: rgba(99,102,241,0.08);
    color: #6366f1;
    font-size: 10.5px;
    font-weight: 700;
    margin-bottom: 3px;
}

/* Quick Access Panel */
.quick-access-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.quick-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px 8px;
    border-radius: 14px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    text-decoration: none;
    transition: all 0.2s ease;
    gap: 8px;
    text-align: center;
}

.quick-btn:hover {
    transform: translateY(-2px);
    border-color: rgba(99,102,241,0.3);
    box-shadow: 0 8px 20px rgba(99,102,241,0.1);
}

.quick-btn i { font-size: 22px; }
.quick-btn span { font-size: 11px; font-weight: 600; color: var(--text-muted); }

/* Section divider label */
.section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-color);
}

@media (max-width: 575px) {
    .dashboard-hero { padding: 20px 18px; }
    .hero-name { font-size: 20px; }
    .stat-card-value { font-size: 22px; }
}
</style>

<!-- ================================================
     HERO GREETING BANNER
================================================ -->
<div class="dashboard-hero">
    <div style="position:relative;z-index:1;">
        <p class="hero-greeting"><i class="bi bi-sun me-1"></i><?php echo $greeting; ?></p>
        <h1 class="hero-name"><?php echo $user_name; ?> 👋</h1>
        <p class="hero-subtitle">Anda login sebagai <strong><?php echo $user_role; ?></strong> &bull; Ini ringkasan data sekolah hari ini.</p>
        <div class="hero-date-badge">
            <i class="bi bi-calendar3"></i>
            <?php
            $days_id = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            $months_id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            echo $days_id[date('w')] . ', ' . date('d') . ' ' . $months_id[(int)date('m')-1] . ' ' . date('Y');
            ?>
        </div>
    </div>
</div>

<!-- ================================================
     STAT CARDS ROW
================================================ -->
<p class="section-label"><i class="bi bi-grid-3x3-gap-fill text-primary"></i> Statistik Utama</p>
<div class="row g-3 mb-4">

    <!-- Siswa -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-indigo">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Total Siswa</div>
                    <p class="stat-card-value"><?php echo number_format($total_siswa); ?></p>
                </div>
                <div class="stat-card-icon icon-indigo"><i class="bi bi-people-fill"></i></div>
            </div>
            <a href="siswa/index.php" class="stat-card-footer text-primary">
                Lihat Detail <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Guru -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-green">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Total Guru</div>
                    <p class="stat-card-value"><?php echo number_format($total_guru); ?></p>
                </div>
                <div class="stat-card-icon icon-green"><i class="bi bi-person-badge-fill"></i></div>
            </div>
            <a href="guru/index.php" class="stat-card-footer text-success">
                Lihat Detail <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Karyawan -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-sky">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Karyawan</div>
                    <p class="stat-card-value"><?php echo number_format($total_karyawan); ?></p>
                </div>
                <div class="stat-card-icon icon-sky"><i class="bi bi-briefcase-fill"></i></div>
            </div>
            <a href="karyawan/index.php" class="stat-card-footer text-info">
                Lihat Detail <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Kelas -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-slate">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Total Kelas</div>
                    <p class="stat-card-value"><?php echo number_format($total_kelas); ?></p>
                </div>
                <div class="stat-card-icon icon-slate"><i class="bi bi-building-fill"></i></div>
            </div>
            <a href="kelas/index.php" class="stat-card-footer text-secondary">
                Lihat Detail <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Presensi Hari Ini -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-amber">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Presensi Hari Ini</div>
                    <p class="stat-card-value <?php echo $today_attendance_pct !== null ? ($today_attendance_pct >= 80 ? 'text-success' : 'text-warning') : ''; ?>">
                        <?php echo $today_attendance_pct !== null ? $today_attendance_pct . '%' : '—'; ?>
                    </p>
                    <?php if ($today_attendance_pct === null): ?>
                        <span style="font-size:11px;color:var(--text-muted);">Belum Diisi</span>
                    <?php endif; ?>
                </div>
                <div class="stat-card-icon icon-amber"><i class="bi bi-calendar-check-fill"></i></div>
            </div>
            <a href="presensi/siswa.php" class="stat-card-footer text-warning">
                Kelola <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Dokumen -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-teal">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Arsip Dokumen</div>
                    <p class="stat-card-value"><?php echo number_format($total_dokumen); ?></p>
                </div>
                <div class="stat-card-icon icon-teal"><i class="bi bi-file-earmark-fill"></i></div>
            </div>
            <div class="stat-card-footer" style="color:var(--text-muted);">
                <i class="bi bi-folder2-open me-1"></i> Arsip Digital
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] !== 'guru'): ?>
    <!-- PMB -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-violet">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Pendaftar PMB</div>
                    <p class="stat-card-value"><?php echo number_format($total_pmb); ?></p>
                    <span style="font-size:11px;color:var(--text-muted);">Pending: <strong style="color:#f59e0b;"><?php echo $total_pmb_pending; ?></strong></span>
                </div>
                <div class="stat-card-icon icon-violet"><i class="bi bi-person-plus-fill"></i></div>
            </div>
            <a href="pmb/index.php" class="stat-card-footer" style="color:#8b5cf6;">
                Lihat Detail <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Payroll -->
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
        <div class="stat-card-v2 accent-rose">
            <div class="stat-card-body">
                <div>
                    <div class="stat-card-label">Gaji Dibayar Bln Ini</div>
                    <p class="stat-card-value sm text-danger">Rp <?php echo number_format($total_payroll_paid, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card-icon icon-rose"><i class="bi bi-cash-stack"></i></div>
            </div>
            <a href="payroll/index.php" class="stat-card-footer text-danger">
                Detail Payroll <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- ================================================
     CHART ROW 1: Kelas + Gender
================================================ -->
<p class="section-label"><i class="bi bi-bar-chart-line text-primary"></i> Analitik & Visualisasi</p>
<div class="row g-4 mb-4">

    <!-- Bar Chart: Distribusi Siswa per Kelas -->
    <div class="col-12 col-lg-8">
        <div class="chart-card h-100">
            <div class="chart-card-header">
                <h2 class="chart-card-title">
                    <span class="chart-card-icon icon-indigo"><i class="bi bi-bar-chart-fill"></i></span>
                    Distribusi Siswa per Kelas
                </h2>
            </div>
            <div class="chart-card-body" style="height: 310px; position: relative;">
                <canvas id="classChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Doughnut Chart: Gender -->
    <div class="col-12 col-lg-4">
        <div class="chart-card h-100">
            <div class="chart-card-header">
                <h2 class="chart-card-title">
                    <span class="chart-card-icon icon-violet"><i class="bi bi-pie-chart-fill"></i></span>
                    Proporsi Gender
                </h2>
            </div>
            <div class="chart-card-body" style="height: 310px; position: relative;">
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Finance Chart -->
    <?php if ($_SESSION['role'] !== 'guru' && (!empty($payroll_trends) || !empty($spp_trends))): ?>
    <div class="col-12">
        <div class="chart-card">
            <div class="chart-card-header">
                <h2 class="chart-card-title">
                    <span class="chart-card-icon icon-green"><i class="bi bi-graph-up-arrow"></i></span>
                    Arus Kas: Penerimaan vs Pengeluaran (6 Bulan Terakhir)
                </h2>
            </div>
            <div class="chart-card-body" style="height: 290px; position: relative;">
                <canvas id="financeChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ================================================
     BOTTOM ROW: Class Progress + Activity / Quicklinks
================================================ -->
<div class="row g-4">

    <!-- Class Distribution Progress Bars -->
    <div class="col-12 col-lg-7">
        <div class="chart-card h-100">
            <div class="chart-card-header mb-4">
                <h2 class="chart-card-title">
                    <span class="chart-card-icon icon-indigo"><i class="bi bi-list-ol"></i></span>
                    Jumlah Siswa per Kelas
                </h2>
                <a href="kelas/index.php" style="font-size:12px;font-weight:600;color:#6366f1;text-decoration:none;">
                    Kelola Kelas <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="chart-card-body" style="padding-top:0;">
                <?php if (empty($kelas_distribution)): ?>
                    <p class="text-muted small text-center py-4">Belum ada data kelas terdaftar.</p>
                <?php else: 
                    $max_siswa = max(array_column($kelas_distribution, 'jumlah') ?: [1]);
                    $max_siswa = $max_siswa > 0 ? $max_siswa : 1;
                    foreach ($kelas_distribution as $kd):
                        $pct = round(($kd['jumlah'] / $max_siswa) * 100);
                ?>
                    <div class="class-bar-item">
                        <div class="class-bar-label">
                            <span class="class-bar-name"><?php echo htmlspecialchars($kd['nama_kelas']); ?></span>
                            <span class="class-bar-count"><?php echo $kd['jumlah']; ?> Siswa</span>
                        </div>
                        <div class="class-progress">
                            <div class="class-progress-fill" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Activity Log OR Quick Access -->
    <div class="col-12 col-lg-5">
        <?php if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'kepala_sekolah'): ?>
        <!-- Activity Log -->
        <div class="chart-card h-100">
            <div class="chart-card-header mb-3" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="chart-card-title" style="font-size:14px;">
                    <span class="chart-card-icon icon-amber"><i class="bi bi-clock-history"></i></span>
                    Aktivitas Terakhir
                </h2>
                <a href="logs/index.php" style="font-size:12px;font-weight:600;color:#6366f1;text-decoration:none;">
                    Semua Log <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="chart-card-body" style="padding-top:0;">
                <?php if (empty($recent_logs)): ?>
                    <div style="text-align:center;padding:30px 0;color:var(--text-muted);">
                        <i class="bi bi-inbox" style="font-size:32px;opacity:0.35;"></i>
                        <p style="font-size:13px;margin:10px 0 0;">Belum ada aktivitas tercatat.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_logs as $log): ?>
                    <div class="activity-item">
                        <div class="activity-dot"></div>
                        <div style="flex:1;min-width:0;">
                            <p class="activity-action"><?php echo htmlspecialchars($log['aktivitas']); ?></p>
                            <p class="activity-detail"><?php echo htmlspecialchars($log['detail']); ?></p>
                        </div>
                        <div class="activity-meta">
                            <div class="activity-user"><?php echo htmlspecialchars($log['username']); ?></div>
                            <div><?php echo date('H:i d/m', strtotime($log['tanggal_akses'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Quick Access Panel for Operator/Guru -->
        <div class="chart-card h-100">
            <div class="chart-card-header mb-3">
                <h2 class="chart-card-title">
                    <span class="chart-card-icon icon-indigo"><i class="bi bi-lightning-fill"></i></span>
                    Akses Cepat
                </h2>
            </div>
            <div class="chart-card-body" style="padding-top:0;">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
                    Sebagai <strong><?php echo $user_role; ?></strong>, gunakan menu berikut untuk akses cepat:
                </p>
                <div class="quick-access-grid">
                    <a href="siswa/index.php" class="quick-btn">
                        <i class="bi bi-people-fill text-primary"></i>
                        <span>Data Siswa</span>
                    </a>
                    <a href="presensi/siswa.php" class="quick-btn">
                        <i class="bi bi-calendar-check-fill text-warning"></i>
                        <span>Presensi Siswa</span>
                    </a>
                    <?php if ($_SESSION['role'] !== 'guru'): ?>
                    <a href="spp/index.php" class="quick-btn">
                        <i class="bi bi-cash-stack text-success"></i>
                        <span>Keuangan SPP</span>
                    </a>
                    <a href="guru/index.php" class="quick-btn">
                        <i class="bi bi-person-badge-fill text-info"></i>
                        <span>Data Guru</span>
                    </a>
                    <?php else: ?>
                    <a href="payroll/index.php" class="quick-btn">
                        <i class="bi bi-receipt text-success"></i>
                        <span>Slip Gaji Saya</span>
                    </a>
                    <a href="rapor/index.php" class="quick-btn">
                        <i class="bi bi-journal-bookmark-fill text-violet" style="color:#8b5cf6;"></i>
                        <span>Rapor</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
    const tickColor = isDark ? '#64748b' : '#94a3b8';

    // 1. Class Distribution Bar Chart
    const ctxClass = document.getElementById('classChart').getContext('2d');
    new Chart(ctxClass, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($class_labels); ?>,
            datasets: [{
                label: 'Jumlah Siswa',
                data: <?php echo json_encode($class_values); ?>,
                backgroundColor: [
                    'rgba(99,102,241,0.75)',
                    'rgba(139,92,246,0.75)',
                    'rgba(14,165,233,0.75)',
                    'rgba(20,184,166,0.75)',
                    'rgba(34,197,94,0.75)',
                    'rgba(245,158,11,0.75)'
                ],
                borderColor: [
                    '#6366f1','#8b5cf6','#0ea5e9','#14b8a6','#22c55e','#f59e0b'
                ],
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: tickColor, font: { size: 11 } },
                    grid: { color: gridColor }
                },
                x: {
                    ticks: { color: tickColor, font: { size: 11 } },
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.raw + ' Siswa'
                    }
                }
            }
        }
    });

    // 2. Gender Doughnut Chart
    const ctxGender = document.getElementById('genderChart').getContext('2d');
    new Chart(ctxGender, {
        type: 'doughnut',
        data: {
            labels: ['Laki-laki', 'Perempuan'],
            datasets: [{
                data: [<?php echo $genders['L']; ?>, <?php echo $genders['P']; ?>],
                backgroundColor: ['#0ea5e9', '#f43f5e'],
                borderColor: isDark ? '#151b2c' : '#ffffff',
                borderWidth: 3,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: tickColor,
                        font: { size: 12, weight: '600' },
                        padding: 16,
                        usePointStyle: true,
                        pointStyleWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.raw + ' Siswa (' + Math.round(ctx.parsed / (<?php echo $genders['L'] + $genders['P'] ?: 1; ?>) * 100) + '%)'
                    }
                }
            }
        }
    });

    // 3. Finance Line Chart
    <?php if ($_SESSION['role'] !== 'guru' && (!empty($payroll_trends) || !empty($spp_trends))): ?>
    const ctxFin = document.getElementById('financeChart').getContext('2d');

    const gradIn = ctxFin.createLinearGradient(0, 0, 0, 250);
    gradIn.addColorStop(0, 'rgba(16,185,129,0.2)');
    gradIn.addColorStop(1, 'rgba(16,185,129,0)');

    const gradOut = ctxFin.createLinearGradient(0, 0, 0, 250);
    gradOut.addColorStop(0, 'rgba(244,63,94,0.15)');
    gradOut.addColorStop(1, 'rgba(244,63,94,0)');

    new Chart(ctxFin, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($fin_labels); ?>,
            datasets: [
                {
                    label: 'Penerimaan Kas',
                    data: <?php echo json_encode($fin_spp); ?>,
                    borderColor: '#10b981',
                    backgroundColor: gradIn,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: isDark ? '#151b2c' : '#fff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Pengeluaran Kas',
                    data: <?php echo json_encode($fin_payroll); ?>,
                    borderColor: '#f43f5e',
                    backgroundColor: gradOut,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#f43f5e',
                    pointBorderColor: isDark ? '#151b2c' : '#fff',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: tickColor,
                        font: { size: 11 },
                        callback: v => 'Rp ' + (v >= 1000000 ? (v/1000000).toFixed(1) + 'jt' : v.toLocaleString('id-ID'))
                    },
                    grid: { color: gridColor }
                },
                x: {
                    ticks: { color: tickColor, font: { size: 11 } },
                    grid: { display: false }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { color: tickColor, font: { size: 11, weight: '600' }, usePointStyle: true, pointStyleWidth: 8, boxHeight: 8 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.dataset.label + ': Rp ' + ctx.raw.toLocaleString('id-ID')
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Re-render charts when theme toggles
    document.getElementById('theme-toggle')?.addEventListener('change', () => {
        setTimeout(() => location.reload(), 300);
    });
    document.getElementById('themeToggleBtn')?.addEventListener('click', () => {
        setTimeout(() => location.reload(), 300);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
