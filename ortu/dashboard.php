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
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // 2. Ambil Informasi Sekolah & Rekening Bank
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
    if (!$settings) {
        $settings = [
            'nama_sekolah' => 'Master Data Sekolah',
            'no_telp' => '-',
            'nama_bank' => '-',
            'nomor_rekening' => '-',
            'nama_rekening' => '-'
        ];
    }

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

    // Hitung status pembayaran SPP 12 Bulan Terakhir
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
    $att_summary_stmt = $pdo->prepare("SELECT status, COUNT(*) as jumlah FROM presensi_siswa WHERE siswa_id = ? GROUP BY status");
    $att_summary_stmt->execute([$siswa_id]);
    $att_summary_raw = $att_summary_stmt->fetchAll();
    
    $att_summary = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
    $total_att_days = 0;
    foreach ($att_summary_raw as $raw) {
        $att_summary[$raw['status']] = (int)$raw['jumlah'];
        $total_att_days += (int)$raw['jumlah'];
    }

    // 6. Ambil Log Kehadiran Terakhir
    $att_logs_stmt = $pdo->prepare("SELECT tanggal, status, keterangan FROM presensi_siswa WHERE siswa_id = ? ORDER BY tanggal DESC LIMIT 15");
    $att_logs_stmt->execute([$siswa_id]);
    $att_logs = $att_logs_stmt->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat data portal orang tua: " . $e->getMessage());
}

$month_names = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];
$month_names_full = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$rate = $total_att_days > 0 ? round(($att_summary['Hadir'] / $total_att_days) * 100) : 100;
$avg_score = 0;
if (!empty($grades)) {
    $sum = 0;
    foreach ($grades as $g) $sum += (float)$g['nilai_akhir'];
    $avg_score = round($sum / count($grades), 1);
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Portal Orang Tua - <?php echo htmlspecialchars($siswa['nama']); ?></title>
    <meta name="description" content="Portal informasi akademik dan pembayaran SPP untuk orang tua/wali murid.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">

    <style>
        /* ===========================
           BASE & RESET
        =========================== */
        *, *::before, *::after { box-sizing: border-box; }

        html {
            max-width: 100%;
            overflow-x: hidden;
            overscroll-behavior-x: none;
        }

        body {
            max-width: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior-x: none;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            background-color: #f4f6fb;
            padding-bottom: calc(80px + env(safe-area-inset-bottom));
        }

        [data-bs-theme="dark"] body {
            background-color: #0f1117;
        }

        /* ===========================
           TOP NAVBAR
        =========================== */
        .top-navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            height: 56px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }

        [data-bs-theme="dark"] .top-navbar {
            background: rgba(15, 17, 23, 0.92);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .navbar-brand-logo {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }

        .navbar-school-name {
            font-size: 13px;
            font-weight: 700;
            color: #1e1b4b;
            line-height: 1.2;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        [data-bs-theme="dark"] .navbar-school-name { color: #e2e8f0; }

        .navbar-sub {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 500;
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #ef4444;
            border: 1px solid #fecaca;
            background: #fff5f5;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        [data-bs-theme="dark"] .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.25);
            color: #f87171;
        }

        .btn-logout:hover { background: #fee2e2; color: #dc2626; }

        /* ===========================
           THEME TOGGLE
        =========================== */
        .theme-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 50px;
            background: #f1f5f9;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        [data-bs-theme="dark"] .theme-pill {
            background: #1e293b;
        }

        .theme-pill i { font-size: 13px; }

        /* ===========================
           PAGE CONTAINER
        =========================== */
        .page-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 16px 12px;
        }

        @media (min-width: 768px) {
            .page-container {
                max-width: 960px;
                padding: 24px 20px;
            }
            body { padding-bottom: 16px; }
        }

        /* ===========================
           PROFILE HERO CARD
        =========================== */
        .profile-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 50%, #6d28d9 100%);
            border-radius: 20px;
            padding: 20px;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        .profile-hero::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 30%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar i { font-size: 28px; color: rgba(255,255,255,0.7); }

        .profile-name {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.3px;
        }

        .profile-meta {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 2px;
        }

        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 600;
            margin-top: 12px;
        }

        /* ===========================
           QUICK STATS ROW
        =========================== */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 14px 12px;
            text-align: center;
            border: 1px solid #e8eaf2;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        [data-bs-theme="dark"] .stat-card {
            background: #1a1d2e;
            border-color: #2d3147;
        }

        .stat-card:active { transform: scale(0.97); }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 16px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 9.5px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ===========================
           SECTION CARDS
        =========================== */
        .section-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e8eaf2;
            overflow: hidden;
            margin-bottom: 14px;
        }

        [data-bs-theme="dark"] .section-card {
            background: #1a1d2e;
            border-color: #2d3147;
        }

        .section-card-header {
            padding: 16px 18px 14px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        [data-bs-theme="dark"] .section-card-header {
            border-bottom-color: #2d3147;
        }

        .section-card-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e1b4b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-bs-theme="dark"] .section-card-title { color: #e2e8f0; }

        .section-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .section-card-body { padding: 16px 18px; }

        /* ===========================
           INFO ROW (Label: Value pairs)
        =========================== */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 12px;
        }

        [data-bs-theme="dark"] .info-row { border-bottom-color: #2d3147; }

        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-row:first-child { padding-top: 0; }

        .info-label {
            font-size: 11.5px;
            color: #94a3b8;
            font-weight: 500;
            flex-shrink: 0;
        }

        .info-value {
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            text-align: right;
        }

        [data-bs-theme="dark"] .info-value { color: #cbd5e1; }

        /* ===========================
           SPP PAYMENT ITEMS
        =========================== */
        .spp-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 8px;
        }

        [data-bs-theme="dark"] .spp-item { border-bottom-color: #2d3147; }
        .spp-item:last-child { border-bottom: none; padding-bottom: 0; }
        .spp-item:first-child { padding-top: 0; }

        .spp-month-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            flex-shrink: 0;
            line-height: 1.2;
        }

        .spp-month-icon.paid {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #d1fae5;
        }

        [data-bs-theme="dark"] .spp-month-icon.paid {
            background: rgba(5, 150, 105, 0.12);
            border-color: rgba(5, 150, 105, 0.2);
        }

        .spp-month-icon.unpaid {
            background: #fff7ed;
            color: #ea580c;
            border: 1px solid #fed7aa;
        }

        [data-bs-theme="dark"] .spp-month-icon.unpaid {
            background: rgba(234, 88, 12, 0.12);
            border-color: rgba(234, 88, 12, 0.2);
        }

        .spp-month-name { font-size: 11px; font-weight: 800; }
        .spp-month-year { font-size: 9px; font-weight: 500; opacity: 0.8; }

        .spp-info { flex: 1; min-width: 0; }

        .spp-period {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        [data-bs-theme="dark"] .spp-period { color: #e2e8f0; }

        .spp-amount {
            font-size: 11px;
            color: #94a3b8;
            margin: 0;
        }

        .badge-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .badge-paid {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #bbf7d0;
        }

        [data-bs-theme="dark"] .badge-paid {
            background: rgba(5, 150, 105, 0.15);
            border-color: rgba(5, 150, 105, 0.25);
            color: #34d399;
        }

        .badge-unpaid {
            background: #fff1f2;
            color: #e11d48;
            border: 1px solid #fecdd3;
        }

        [data-bs-theme="dark"] .badge-unpaid {
            background: rgba(225, 29, 72, 0.15);
            border-color: rgba(225, 29, 72, 0.25);
            color: #fb7185;
        }

        /* ===========================
           GRADES
        =========================== */
        .grade-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 8px;
        }

        [data-bs-theme="dark"] .grade-item { border-bottom-color: #2d3147; }
        .grade-item:last-child { border-bottom: none; padding-bottom: 0; }
        .grade-item:first-child { padding-top: 0; }

        .grade-subject {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            flex: 1;
            min-width: 0;
        }

        [data-bs-theme="dark"] .grade-subject { color: #e2e8f0; }

        .grade-score-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .grade-A { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .grade-B { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .grade-C { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .grade-D { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }

        [data-bs-theme="dark"] .grade-A { background: rgba(5,150,105,0.12); border-color: rgba(5,150,105,0.2); }
        [data-bs-theme="dark"] .grade-B { background: rgba(37,99,235,0.12); border-color: rgba(37,99,235,0.2); }
        [data-bs-theme="dark"] .grade-C { background: rgba(217,119,6,0.12); border-color: rgba(217,119,6,0.2); }
        [data-bs-theme="dark"] .grade-D { background: rgba(225,29,72,0.12); border-color: rgba(225,29,72,0.2); }

        /* ===========================
           ATTENDANCE ITEMS
        =========================== */
        .att-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        [data-bs-theme="dark"] .att-item { border-bottom-color: #2d3147; }
        .att-item:last-child { border-bottom: none; padding-bottom: 0; }
        .att-item:first-child { padding-top: 0; }

        .att-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .att-dot-hadir { background: #22c55e; }
        .att-dot-sakit { background: #3b82f6; }
        .att-dot-izin  { background: #f59e0b; }
        .att-dot-alpa  { background: #ef4444; }

        .att-date {
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            flex: 1;
        }

        [data-bs-theme="dark"] .att-date { color: #e2e8f0; }

        .att-note {
            font-size: 11px;
            color: #94a3b8;
            margin: 0;
        }

        /* ===========================
           ATTENDANCE RECAP GRID
        =========================== */
        .recap-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .recap-cell {
            text-align: center;
            padding: 12px 8px;
            border-radius: 14px;
            border: 1px solid transparent;
        }

        .recap-cell-hadir { background: #f0fdf4; border-color: #bbf7d0; }
        .recap-cell-sakit { background: #eff6ff; border-color: #bfdbfe; }
        .recap-cell-izin  { background: #fffbeb; border-color: #fde68a; }
        .recap-cell-alpa  { background: #fff1f2; border-color: #fecdd3; }

        [data-bs-theme="dark"] .recap-cell-hadir { background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.2); }
        [data-bs-theme="dark"] .recap-cell-sakit { background: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.2); }
        [data-bs-theme="dark"] .recap-cell-izin  { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.2); }
        [data-bs-theme="dark"] .recap-cell-alpa  { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.2); }

        .recap-num {
            font-size: 22px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
        }

        .recap-num-hadir { color: #16a34a; }
        .recap-num-sakit { color: #2563eb; }
        .recap-num-izin  { color: #d97706; }
        .recap-num-alpa  { color: #dc2626; }

        .recap-label {
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
        }

        /* ===========================
           ATTENDANCE RATE RING
        =========================== */
        .rate-ring-wrapper {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 16px;
            margin-bottom: 16px;
            border: 1px solid #e8eaf2;
        }

        [data-bs-theme="dark"] .rate-ring-wrapper {
            background: #131520;
            border-color: #2d3147;
        }

        .rate-ring-svg { flex-shrink: 0; }

        .rate-ring-info h3 {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            line-height: 1;
        }

        .rate-ring-info p {
            font-size: 12px;
            color: #94a3b8;
            margin: 4px 0 0;
        }

        /* ===========================
           BANK INFO CARD
        =========================== */
        .bank-info-box {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #a7f3d0;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }

        [data-bs-theme="dark"] .bank-info-box {
            background: rgba(5, 150, 105, 0.1);
            border-color: rgba(5, 150, 105, 0.25);
        }

        .bank-rek-number {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #065f46;
            font-variant-numeric: tabular-nums;
        }

        [data-bs-theme="dark"] .bank-rek-number { color: #34d399; }

        .btn-copy {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-copy:hover { background: #047857; }
        .btn-copy:active { transform: scale(0.97); }

        /* ===========================
           BILLING SUMMARY
        =========================== */
        .billing-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        [data-bs-theme="dark"] .billing-row { border-bottom-color: #2d3147; }
        .billing-row:last-child { border-bottom: none; }
        .billing-row:first-child { padding-top: 0; }

        .billing-label { font-size: 12.5px; color: #64748b; }
        .billing-value { font-size: 13px; font-weight: 700; color: #1e293b; }
        [data-bs-theme="dark"] .billing-value { color: #e2e8f0; }

        .billing-total-row {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            padding: 12px 16px;
            border-radius: 12px;
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #fecaca;
        }

        [data-bs-theme="dark"] .billing-total-row {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .billing-total-label { font-size: 12px; font-weight: 600; color: #dc2626; }
        .billing-total-value { font-size: 18px; font-weight: 800; color: #dc2626; }

        /* ===========================
           TAB CONTENT SECTIONS
        =========================== */
        .tab-section { display: none; }
        .tab-section.active { display: block; }

        /* ===========================
           DESKTOP TAB NAV
        =========================== */
        .desktop-tab-nav {
            display: none;
            background: white;
            border-radius: 16px;
            padding: 6px;
            border: 1px solid #e8eaf2;
            margin-bottom: 20px;
            gap: 4px;
        }

        [data-bs-theme="dark"] .desktop-tab-nav {
            background: #1a1d2e;
            border-color: #2d3147;
        }

        @media (min-width: 768px) {
            .desktop-tab-nav { display: flex; }
        }

        .desktop-tab-btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            background: transparent;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .desktop-tab-btn.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        /* ===========================
           BOTTOM NAV BAR (MOBILE)
        =========================== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(60px + env(safe-area-inset-bottom));
            padding-bottom: env(safe-area-inset-bottom);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
        }

        [data-bs-theme="dark"] .bottom-nav {
            background: rgba(15, 17, 23, 0.95);
            border-top-color: rgba(255,255,255,0.06);
        }

        @media (min-width: 768px) {
            .bottom-nav { display: none; }
        }

        .bottom-nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            height: 60px;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-size: 9.5px;
            font-weight: 600;
            gap: 3px;
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 0;
            position: relative;
        }

        .bottom-nav-btn i {
            font-size: 20px;
            transition: transform 0.2s ease;
        }

        .bottom-nav-btn.active {
            color: #6366f1;
        }

        .bottom-nav-btn.active i {
            transform: translateY(-1px);
        }

        .bottom-nav-btn.active::before {
            content: '';
            position: absolute;
            top: 6px;
            left: 50%;
            transform: translateX(-50%);
            width: 32px;
            height: 32px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 10px;
        }

        /* ===========================
           EMPTY STATE
        =========================== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.4;
        }

        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* ===========================
           SEMESTER HEADER
        =========================== */
        .semester-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0 10px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 4px;
        }

        [data-bs-theme="dark"] .semester-header { border-bottom-color: #2d3147; }

        .semester-title {
            font-size: 13px;
            font-weight: 700;
            color: #6366f1;
        }

        .semester-avg {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            background: rgba(99,102,241,0.08);
            color: #6366f1;
            border-radius: 20px;
            border: 1px solid rgba(99,102,241,0.15);
        }

        /* ===========================
           ANNOUNCEMENT CARD
        =========================== */
        .announcement-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }

        [data-bs-theme="dark"] .announcement-card {
            background: rgba(37, 99, 235, 0.1);
            border-color: rgba(37, 99, 235, 0.2);
        }

        .announcement-title {
            font-size: 12px;
            font-weight: 700;
            color: #1d4ed8;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        [data-bs-theme="dark"] .announcement-title { color: #60a5fa; }

        .announcement-text {
            font-size: 12px;
            color: #1e40af;
            margin: 0;
            line-height: 1.6;
        }

        [data-bs-theme="dark"] .announcement-text { color: #93c5fd; }

        /* ===========================
           CONTACT CARD
        =========================== */
        .contact-number {
            font-size: 20px;
            font-weight: 800;
            color: #6366f1;
            letter-spacing: 0.5px;
        }

        /* ===========================
           COPY TOAST
        =========================== */
        .copy-toast {
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #1e293b;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
        }

        .copy-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* ===========================
           SCROLLABLE SECTION
        =========================== */
        .scrollable-list {
            overflow-y: visible;
        }

        @media (min-width: 768px) {
            .scrollable-list {
                max-height: 420px;
                overflow-y: auto;
            }
            .scrollable-list::-webkit-scrollbar { width: 4px; }
            .scrollable-list::-webkit-scrollbar-track { background: transparent; }
            .scrollable-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        }

        /* ===========================
           RECEIPT BUTTON
        =========================== */
        .btn-receipt {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 11px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .btn-receipt:hover { background: #f1f5f9; color: #334155; }

        [data-bs-theme="dark"] .btn-receipt {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        .btn-pay {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 11px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #c2410c;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .btn-pay:hover { background: #ffedd5; }

        [data-bs-theme="dark"] .btn-pay {
            background: rgba(234,88,12,0.1);
            border-color: rgba(234,88,12,0.2);
            color: #fb923c;
        }

        @media (min-width: 768px) {
            .stats-row { grid-template-columns: repeat(3, 1fr); gap: 16px; }
            .profile-hero { padding: 28px; }
            .profile-avatar { width: 76px; height: 76px; }
            .profile-name { font-size: 22px; }
            .copy-toast { bottom: 40px; }
        }

        /* Ensure nothing is clipped */
        .page-container, .section-card, .tab-section {
            overflow: visible !important;
        }
    </style>
</head>
<body>

<!-- Copy Toast Notification -->
<div class="copy-toast" id="copyToast"><i class="bi bi-check-circle me-2"></i>Nomor rekening disalin!</div>

<!-- ============================
     TOP NAVBAR
============================ -->
<nav class="top-navbar">
    <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
        <div class="navbar-brand-logo">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div style="min-width:0;">
            <div class="navbar-school-name"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?></div>
            <div class="navbar-sub">Portal Wali Murid</div>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
        <!-- Theme Toggle -->
        <button class="theme-pill" id="themeToggleBtn" aria-label="Toggle theme">
            <i class="bi bi-sun-fill text-warning" id="iconSun" style="font-size:13px;"></i>
            <i class="bi bi-moon-stars-fill text-primary d-none" id="iconMoon" style="font-size:13px;"></i>
        </button>
        <!-- Logout -->
        <a href="logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar</span>
        </a>
    </div>
</nav>

<!-- ============================
     PAGE CONTENT
============================ -->
<div class="page-container">

    <!-- PROFILE HERO CARD -->
    <div class="profile-hero">
        <div style="display:flex; align-items:center; gap:14px; position:relative; z-index:1;">
            <div class="profile-avatar">
                <?php if (!empty($siswa['foto']) && file_exists('../' . $siswa['foto'])): ?>
                    <img src="../<?php echo htmlspecialchars($siswa['foto']); ?>" alt="Foto Siswa">
                <?php else: ?>
                    <i class="bi bi-person-fill"></i>
                <?php endif; ?>
            </div>
            <div style="min-width:0; flex:1;">
                <h1 class="profile-name"><?php echo htmlspecialchars($siswa['nama']); ?></h1>
                <p class="profile-meta">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?> &bull; NIS: <?php echo htmlspecialchars($siswa['nis']); ?></p>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; position:relative; z-index:1;">
            <span class="profile-badge">
                <i class="bi bi-mortarboard-fill"></i>
                Kelas <?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Diatur'); ?>
            </span>
            <span class="profile-badge">
                <i class="bi bi-calendar3"></i>
                Angkatan <?php echo htmlspecialchars($siswa['tahun_masuk']); ?>
            </span>
            <span class="profile-badge">
                <i class="bi bi-gender-ambiguous"></i>
                <?php echo $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?>
            </span>
        </div>
    </div>

    <!-- QUICK STATS -->
    <div class="stats-row">
        <!-- Kehadiran -->
        <?php $rate_color = $rate >= 90 ? '#22c55e' : ($rate >= 75 ? '#f59e0b' : '#ef4444'); ?>
        <div class="stat-card">
            <div class="stat-icon" style="background:<?php echo $rate >= 90 ? '#f0fdf4' : ($rate >= 75 ? '#fffbeb' : '#fff1f2'); ?>">
                <i class="bi bi-calendar-check-fill" style="color:<?php echo $rate_color; ?>"></i>
            </div>
            <div class="stat-value" style="color:<?php echo $rate_color; ?>"><?php echo $rate; ?>%</div>
            <div class="stat-label">Kehadiran</div>
        </div>

        <!-- Tunggakan SPP -->
        <div class="stat-card">
            <div class="stat-icon" style="background:<?php echo $total_tunggakan > 0 ? '#fff1f2' : '#f0fdf4'; ?>">
                <i class="bi bi-wallet2" style="color:<?php echo $total_tunggakan > 0 ? '#ef4444' : '#22c55e'; ?>"></i>
            </div>
            <div class="stat-value" style="color:<?php echo $total_tunggakan > 0 ? '#ef4444' : '#22c55e'; ?>; font-size:13px;">
                <?php echo $unpaid_months_count > 0 ? $unpaid_months_count . ' Bln' : 'Lunas'; ?>
            </div>
            <div class="stat-label">Tunggakan</div>
        </div>

        <!-- Rata-rata Nilai -->
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff;">
                <i class="bi bi-journal-bookmark-fill" style="color:#3b82f6;"></i>
            </div>
            <div class="stat-value" style="color:#3b82f6;"><?php echo $avg_score > 0 ? $avg_score : '-'; ?></div>
            <div class="stat-label">Rata Nilai</div>
        </div>
    </div>

    <!-- DESKTOP TAB NAV -->
    <div class="desktop-tab-nav" id="desktopTabNav">
        <button class="desktop-tab-btn active" data-tab="overview"><i class="bi bi-grid-fill"></i> Ringkasan</button>
        <button class="desktop-tab-btn" data-tab="spp"><i class="bi bi-wallet2"></i> SPP Bulanan</button>
        <button class="desktop-tab-btn" data-tab="grades"><i class="bi bi-journal-bookmark-fill"></i> Akademik & Nilai</button>
        <button class="desktop-tab-btn" data-tab="attendance"><i class="bi bi-calendar-check"></i> Kehadiran</button>
    </div>

    <!-- ========================================
         TAB 1: RINGKASAN (OVERVIEW)
    ======================================== -->
    <div class="tab-section active" id="tab-overview">

        <!-- Biodata Anak -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#eff6ff;"><i class="bi bi-person-lines-fill" style="color:#6366f1;font-size:14px;"></i></span>
                    Biodata Anak
                </h2>
            </div>
            <div class="section-card-body">
                <div class="info-row">
                    <span class="info-label">Tempat & Tgl Lahir</span>
                    <span class="info-value"><?php echo htmlspecialchars($siswa['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($siswa['tanggal_lahir']))); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Agama</span>
                    <span class="info-value"><?php echo htmlspecialchars($siswa['agama']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">No. HP Siswa</span>
                    <span class="info-value"><?php echo htmlspecialchars($siswa['no_hp']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo !empty($siswa['email']) ? htmlspecialchars($siswa['email']) : '-'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Alamat</span>
                    <span class="info-value" style="max-width:60%;"><?php echo nl2br(htmlspecialchars($siswa['alamat'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Data Orang Tua -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#f0fdf4;"><i class="bi bi-people-fill" style="color:#22c55e;font-size:14px;"></i></span>
                    Data Orang Tua / Wali
                </h2>
            </div>
            <div class="section-card-body">
                <div class="info-row">
                    <span class="info-label">Nama Ayah</span>
                    <span class="info-value"><?php echo htmlspecialchars($siswa['nama_ayah']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Ibu</span>
                    <span class="info-value"><?php echo htmlspecialchars($siswa['nama_ibu']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">No. HP Orang Tua</span>
                    <span class="info-value"><?php echo !empty($siswa['no_hp_ortu']) ? htmlspecialchars($siswa['no_hp_ortu']) : '-'; ?></span>
                </div>
            </div>
        </div>

        <!-- Pengumuman & Kontak Sekolah -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#fffbeb;"><i class="bi bi-megaphone-fill" style="color:#f59e0b;font-size:14px;"></i></span>
                    Pengumuman & Kontak
                </h2>
            </div>
            <div class="section-card-body">
                <div class="announcement-card">
                    <div class="announcement-title">
                        <i class="bi bi-info-circle-fill"></i> Informasi Pembayaran SPP
                    </div>
                    <p class="announcement-text">
                        Untuk mempermudah verifikasi pembayaran SPP bulanan, silakan lampirkan bukti transfer dan konfirmasi ke Bendahara melalui WhatsApp.
                    </p>
                </div>
                <div style="text-align:center; padding:10px 0 4px;">
                    <p style="font-size:12px; color:#94a3b8; margin-bottom:8px;">Nomor Telepon Sekolah</p>
                    <div class="contact-number"><?php echo htmlspecialchars($settings['no_telp'] ?? '-'); ?></div>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================
         TAB 2: SPP BULANAN
    ======================================== -->
    <div class="tab-section" id="tab-spp">

        <!-- Ringkasan Tagihan -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#f0fdf4;"><i class="bi bi-calculator-fill" style="color:#22c55e;font-size:14px;"></i></span>
                    Ringkasan Tagihan
                </h2>
            </div>
            <div class="section-card-body">
                <div class="billing-row">
                    <span class="billing-label">Tarif SPP Bulanan</span>
                    <span class="billing-value">Rp <?php echo number_format($tarif_spp, 0, ',', '.'); ?></span>
                </div>
                <div class="billing-row">
                    <span class="billing-label">Bulan Belum Lunas</span>
                    <span class="billing-value" style="color:#ef4444;"><?php echo $unpaid_months_count; ?> Bulan</span>
                </div>
                <div class="billing-total-row">
                    <span class="billing-total-label"><i class="bi bi-exclamation-triangle-fill me-1"></i>Total Tunggakan</span>
                    <span class="billing-total-value">Rp <?php echo number_format($total_tunggakan, 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Info Rekening Sekolah -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#f0fdf4;"><i class="bi bi-bank" style="color:#059669;font-size:14px;"></i></span>
                    Info Rekening Sekolah
                </h2>
            </div>
            <div class="section-card-body">
                <div class="bank-info-box">
                    <p style="font-size:11px; color:#065f46; margin-bottom:4px; font-weight:600;"><?php echo htmlspecialchars($settings['nama_bank'] ?? '-'); ?></p>
                    <div class="bank-rek-number"><?php echo htmlspecialchars($settings['nomor_rekening'] ?? '-'); ?></div>
                    <p style="font-size:11px; color:#065f46; margin:4px 0 10px; font-weight:500;">a.n. <?php echo htmlspecialchars($settings['nama_rekening'] ?? '-'); ?></p>
                    <button class="btn-copy" onclick="copyRekening()" id="copyRekBtn">
                        <i class="bi bi-copy"></i> Salin Nomor Rekening
                    </button>
                </div>
                <p style="font-size:11.5px; color:#94a3b8; text-align:center; margin:4px 0 0;">Setelah transfer, harap konfirmasi ke pihak sekolah.</p>
            </div>
        </div>

        <!-- Status SPP 12 Bulan Terakhir -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#eff6ff;"><i class="bi bi-calendar-range" style="color:#6366f1;font-size:14px;"></i></span>
                    Status SPP 12 Bulan Terakhir
                </h2>
            </div>
            <div class="section-card-body">
                <?php foreach ($months_billing as $mb): 
                    $key = $mb['tahun'] . '-' . $mb['bulan'];
                    $is_paid = isset($payment_map[$key]) && $payment_map[$key]['status_bayar'] === 'Lunas';
                ?>
                <div class="spp-item">
                    <div class="spp-month-icon <?php echo $is_paid ? 'paid' : 'unpaid'; ?>">
                        <span class="spp-month-name"><?php echo $month_names[$mb['bulan']]; ?></span>
                        <span class="spp-month-year"><?php echo $mb['tahun']; ?></span>
                    </div>
                    <div class="spp-info">
                        <p class="spp-period"><?php echo $month_names_full[$mb['bulan']] . ' ' . $mb['tahun']; ?></p>
                        <p class="spp-amount">Rp <?php echo number_format($tarif_spp, 0, ',', '.'); ?></p>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                        <span class="badge-pill <?php echo $is_paid ? 'badge-paid' : 'badge-unpaid'; ?>">
                            <i class="bi <?php echo $is_paid ? 'bi-check-circle-fill' : 'bi-clock-fill'; ?>"></i>
                            <?php echo $is_paid ? 'Lunas' : 'Belum'; ?>
                        </span>
                        <?php if ($is_paid): ?>
                            <a href="../spp/invoice.php?token=<?php echo htmlspecialchars($payment_map[$key]['invoice_token']); ?>" target="_blank" class="btn-receipt">
                                <i class="bi bi-receipt"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn-pay" onclick="scrollToBankInfo()">
                                <i class="bi bi-bank"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ========================================
         TAB 3: AKADEMIK & NILAI
    ======================================== -->
    <div class="tab-section" id="tab-grades">
        <?php 
        $grouped_grades = [];
        foreach ($grades as $grade) {
            $group_key = $grade['tahun_ajaran'] . ' — Semester ' . $grade['semester'];
            $grouped_grades[$group_key][] = $grade;
        }
        
        if (empty($grouped_grades)): ?>
            <div class="section-card">
                <div class="section-card-body">
                    <div class="empty-state">
                        <i class="bi bi-journal-x"></i>
                        <p>Belum ada catatan nilai rapor yang diinput untuk siswa ini.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_grades as $semester_name => $semester_grades): 
                $sem_sum = 0;
                foreach ($semester_grades as $sg) $sem_sum += (float)$sg['nilai_akhir'];
                $sem_avg = round($sem_sum / count($semester_grades), 1);
            ?>
            <div class="section-card">
                <div class="section-card-header">
                    <h2 class="section-card-title" style="font-size:12.5px;">
                        <span class="section-icon" style="background:#eff6ff;"><i class="bi bi-calendar3" style="color:#6366f1;font-size:13px;"></i></span>
                        <?php echo htmlspecialchars($semester_name); ?>
                    </h2>
                    <span class="semester-avg">Rata-rata: <?php echo $sem_avg; ?></span>
                </div>
                <div class="section-card-body">
                    <?php foreach ($semester_grades as $grade):
                        $final = (float)$grade['nilai_akhir'];
                        if ($final >= 85)      { $g_class = 'grade-A'; $predikat = 'A'; }
                        elseif ($final >= 75)  { $g_class = 'grade-B'; $predikat = 'B'; }
                        elseif ($final >= 60)  { $g_class = 'grade-C'; $predikat = 'C'; }
                        else                   { $g_class = 'grade-D'; $predikat = 'D'; }
                    ?>
                    <div class="grade-item">
                        <div style="flex:1; min-width:0;">
                            <p class="grade-subject"><?php echo htmlspecialchars($grade['mata_pelajaran']); ?></p>
                            <p style="font-size:10.5px; color:#94a3b8; margin:2px 0 0;">
                                Tugas: <?php echo number_format($grade['nilai_tugas'],1); ?> &bull;
                                UTS: <?php echo number_format($grade['nilai_uts'],1); ?> &bull;
                                UAS: <?php echo number_format($grade['nilai_uas'],1); ?>
                            </p>
                            <?php if (!empty($grade['keterangan'])): ?>
                                <p style="font-size:10.5px; color:#94a3b8; margin:3px 0 0; font-style:italic;">"<?php echo htmlspecialchars($grade['keterangan']); ?>"</p>
                            <?php endif; ?>
                        </div>
                        <div class="grade-score-box <?php echo $g_class; ?>">
                            <?php echo number_format($grade['nilai_akhir'],1); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ========================================
         TAB 4: KEHADIRAN
    ======================================== -->
    <div class="tab-section" id="tab-attendance">

        <!-- Rekapitulasi Kehadiran -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#fffbeb;"><i class="bi bi-pie-chart-fill" style="color:#f59e0b;font-size:14px;"></i></span>
                    Rekapitulasi Kehadiran
                </h2>
            </div>
            <div class="section-card-body">
                <!-- Rate Ring Visual -->
                <div class="rate-ring-wrapper">
                    <svg class="rate-ring-svg" width="70" height="70" viewBox="0 0 70 70">
                        <circle cx="35" cy="35" r="28" fill="none" stroke="#f1f5f9" stroke-width="8"/>
                        <circle cx="35" cy="35" r="28" fill="none" 
                                stroke="<?php echo $rate >= 90 ? '#22c55e' : ($rate >= 75 ? '#f59e0b' : '#ef4444'); ?>" 
                                stroke-width="8" stroke-linecap="round"
                                stroke-dasharray="<?php echo round(175.9 * $rate / 100); ?> 175.9"
                                transform="rotate(-90 35 35)"/>
                        <text x="35" y="40" text-anchor="middle" font-size="14" font-weight="800" 
                              fill="<?php echo $rate >= 90 ? '#22c55e' : ($rate >= 75 ? '#f59e0b' : '#ef4444'); ?>">
                            <?php echo $rate; ?>%
                        </text>
                    </svg>
                    <div class="rate-ring-info">
                        <h3 style="color:<?php echo $rate >= 90 ? '#22c55e' : ($rate >= 75 ? '#f59e0b' : '#ef4444'); ?>">
                            <?php echo $rate >= 90 ? 'Sangat Baik' : ($rate >= 75 ? 'Perlu Ditingkatkan' : 'Perhatian!'); ?>
                        </h3>
                        <p><?php echo $att_summary['Hadir']; ?> hari hadir dari <?php echo $total_att_days; ?> hari total</p>
                    </div>
                </div>
                <!-- Recap Grid -->
                <div class="recap-grid">
                    <div class="recap-cell recap-cell-hadir">
                        <div class="recap-num recap-num-hadir"><?php echo $att_summary['Hadir']; ?></div>
                        <div class="recap-label">Hadir</div>
                    </div>
                    <div class="recap-cell recap-cell-sakit">
                        <div class="recap-num recap-num-sakit"><?php echo $att_summary['Sakit']; ?></div>
                        <div class="recap-label">Sakit</div>
                    </div>
                    <div class="recap-cell recap-cell-izin">
                        <div class="recap-num recap-num-izin"><?php echo $att_summary['Izin']; ?></div>
                        <div class="recap-label">Izin</div>
                    </div>
                    <div class="recap-cell recap-cell-alpa">
                        <div class="recap-num recap-num-alpa"><?php echo $att_summary['Alpa']; ?></div>
                        <div class="recap-label">Alpa</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Kehadiran -->
        <div class="section-card">
            <div class="section-card-header">
                <h2 class="section-card-title">
                    <span class="section-icon" style="background:#eff6ff;"><i class="bi bi-clock-history" style="color:#6366f1;font-size:14px;"></i></span>
                    Catatan Absensi Terakhir
                </h2>
                <span style="font-size:11px; color:#94a3b8; font-weight:500;">15 log terakhir</span>
            </div>
            <div class="section-card-body scrollable-list">
                <?php if (empty($att_logs)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <p>Belum ada data presensi siswa yang dicatat oleh guru.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $day_names = [
                        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
                        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                    ];
                    foreach ($att_logs as $log):
                        $day_en = date('l', strtotime($log['tanggal']));
                        $day_id = $day_names[$day_en] ?? $day_en;
                        $formatted_date = $day_id . ', ' . date('d M Y', strtotime($log['tanggal']));
                        
                        $dot_class = 'att-dot-hadir';
                        $status_color = '#22c55e';
                        if ($log['status'] === 'Sakit')  { $dot_class = 'att-dot-sakit'; $status_color = '#3b82f6'; }
                        elseif ($log['status'] === 'Izin')  { $dot_class = 'att-dot-izin'; $status_color = '#f59e0b'; }
                        elseif ($log['status'] === 'Alpa')  { $dot_class = 'att-dot-alpa'; $status_color = '#ef4444'; }
                    ?>
                    <div class="att-item">
                        <div class="att-dot <?php echo $dot_class; ?>"></div>
                        <div style="flex:1; min-width:0;">
                            <div class="att-date"><?php echo $formatted_date; ?></div>
                            <?php if (!empty($log['keterangan'])): ?>
                                <p class="att-note"><?php echo htmlspecialchars($log['keterangan']); ?></p>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:12px; font-weight:700; color:<?php echo $status_color; ?>; flex-shrink:0;">
                            <?php echo htmlspecialchars($log['status']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer style="text-align:center; padding:20px 0 10px; color:#94a3b8;">
        <p style="font-size:11px; margin:0;">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'Master Data Sekolah'); ?>. Semua Hak Cipta Dilindungi.</p>
    </footer>

</div><!-- end page-container -->

<!-- ============================
     BOTTOM NAV BAR (MOBILE)
============================ -->
<nav class="bottom-nav" id="bottomNav">
    <button class="bottom-nav-btn active" data-tab="overview">
        <i class="bi bi-grid-fill"></i>
        <span>Ringkasan</span>
    </button>
    <button class="bottom-nav-btn" data-tab="spp">
        <i class="bi bi-wallet2"></i>
        <span>SPP</span>
    </button>
    <button class="bottom-nav-btn" data-tab="grades">
        <i class="bi bi-journal-bookmark-fill"></i>
        <span>Nilai</span>
    </button>
    <button class="bottom-nav-btn" data-tab="attendance">
        <i class="bi bi-calendar-check"></i>
        <span>Absensi</span>
    </button>
</nav>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // =============================
    // THEME TOGGLE
    // =============================
    const html = document.documentElement;
    const themeBtn = document.getElementById('themeToggleBtn');
    const iconSun  = document.getElementById('iconSun');
    const iconMoon = document.getElementById('iconMoon');

    function setTheme(dark) {
        html.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        localStorage.setItem('theme', dark ? 'dark' : 'light');
        iconSun.classList.toggle('d-none', dark);
        iconMoon.classList.toggle('d-none', !dark);
    }

    const saved = localStorage.getItem('theme');
    const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    setTheme(saved === 'dark' || (!saved && sysDark));

    themeBtn.addEventListener('click', () => {
        setTheme(html.getAttribute('data-bs-theme') !== 'dark');
    });

    // =============================
    // TAB NAVIGATION
    // =============================
    const tabSections    = document.querySelectorAll('.tab-section');
    const bottomNavBtns  = document.querySelectorAll('.bottom-nav-btn');
    const desktopTabBtns = document.querySelectorAll('.desktop-tab-btn');

    function activateTab(tabId) {
        // Hide all sections
        tabSections.forEach(s => s.classList.remove('active'));
        bottomNavBtns.forEach(b => b.classList.remove('active'));
        desktopTabBtns.forEach(b => b.classList.remove('active'));

        // Show target section
        const section = document.getElementById('tab-' + tabId);
        if (section) section.classList.add('active');

        // Update bottom nav
        bottomNavBtns.forEach(b => {
            if (b.getAttribute('data-tab') === tabId) b.classList.add('active');
        });

        // Update desktop tab
        desktopTabBtns.forEach(b => {
            if (b.getAttribute('data-tab') === tabId) b.classList.add('active');
        });

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    bottomNavBtns.forEach(btn => {
        btn.addEventListener('click', () => activateTab(btn.getAttribute('data-tab')));
    });

    desktopTabBtns.forEach(btn => {
        btn.addEventListener('click', () => activateTab(btn.getAttribute('data-tab')));
    });

    // =============================
    // COPY REKENING
    // =============================
    function copyRekening() {
        const rekText = '<?php echo addslashes(htmlspecialchars($settings['nomor_rekening'] ?? '')); ?>';
        navigator.clipboard.writeText(rekText).then(() => {
            const toast = document.getElementById('copyToast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }).catch(() => {
            alert('Nomor Rekening: ' + rekText);
        });
    }

    // =============================
    // SCROLL TO BANK INFO
    // =============================
    function scrollToBankInfo() {
        activateTab('spp');
        setTimeout(() => {
            const bankEl = document.querySelector('.bank-info-box');
            if (bankEl) bankEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    }
</script>
</body>
</html>
