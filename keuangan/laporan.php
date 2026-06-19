<?php
$path_prefix = '../';
$page_title = 'Laporan Laba Rugi Sekolah';
$active_menu = 'keuangan';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Auth: Block Guru
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$error = '';

// Load School settings for printable header
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
} catch (PDOException $e) {
    // Fail silently
}

// Initialize 12 months dataset structure
$report_data = [];
$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

for ($m = 1; $m <= 12; $m++) {
    $report_data[$m] = [
        'nama_bulan' => $month_names[$m],
        'spp_income' => 0.0,
        'manual_income' => 0.0,
        'total_income' => 0.0,
        'payroll_expense' => 0.0,
        'manual_expense' => 0.0,
        'total_expense' => 0.0,
        'net_margin' => 0.0
    ];
}

try {
    // 1. Fetch SPP Incomes group by month
    $stmt_spp = $pdo->prepare("
        SELECT MONTH(tanggal_bayar) AS bulan, SUM(jumlah_bayar) AS total 
        FROM spp_pembayaran 
        WHERE YEAR(tanggal_bayar) = ? AND status_bayar = 'Lunas' 
        GROUP BY MONTH(tanggal_bayar)
    ");
    $stmt_spp->execute([$year]);
    $rows_spp = $stmt_spp->fetchAll();
    foreach ($rows_spp as $r) {
        $report_data[(int)$r['bulan']]['spp_income'] = (float)$r['total'];
    }

    // 2. Fetch Manual Incomes group by month
    $stmt_man_in = $pdo->prepare("
        SELECT MONTH(tanggal) AS bulan, SUM(nominal) AS total 
        FROM keuangan_transaksi 
        WHERE YEAR(tanggal) = ? AND tipe = 'Pemasukan' 
        GROUP BY MONTH(tanggal)
    ");
    $stmt_man_in->execute([$year]);
    $rows_man_in = $stmt_man_in->fetchAll();
    foreach ($rows_man_in as $r) {
        $report_data[(int)$r['bulan']]['manual_income'] = (float)$r['total'];
    }

    // 3. Fetch Payroll Expenses group by month
    $stmt_pay = $pdo->prepare("
        SELECT bulan, SUM(gaji_bersih) AS total 
        FROM payroll 
        WHERE tahun = ? AND status_bayar = 'Dibayar' 
        GROUP BY bulan
    ");
    $stmt_pay->execute([$year]);
    $rows_pay = $stmt_pay->fetchAll();
    foreach ($rows_pay as $r) {
        $report_data[(int)$r['bulan']]['payroll_expense'] = (float)$r['total'];
    }

    // 4. Fetch Manual Expenses group by month
    $stmt_man_ex = $pdo->prepare("
        SELECT MONTH(tanggal) AS bulan, SUM(nominal) AS total 
        FROM keuangan_transaksi 
        WHERE YEAR(tanggal) = ? AND tipe = 'Pengeluaran' 
        GROUP BY MONTH(tanggal)
    ");
    $stmt_man_ex->execute([$year]);
    $rows_man_ex = $stmt_man_ex->fetchAll();
    foreach ($rows_man_ex as $r) {
        $report_data[(int)$r['bulan']]['manual_expense'] = (float)$r['total'];
    }

    // Calculate totals & net margins
    foreach ($report_data as $m => &$data) {
        $data['total_income'] = $data['spp_income'] + $data['manual_income'];
        $data['total_expense'] = $data['payroll_expense'] + $data['manual_expense'];
        $data['net_margin'] = $data['total_income'] - $data['total_expense'];
    }
    unset($data); // Break reference

} catch (PDOException $e) {
    $error = 'Kesalahan memproses laporan laba rugi: ' . $e->getMessage();
}

// Year list for filters
$year_options = [];
for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 2; $y++) {
    $year_options[] = $y;
}

// Print header layout check
$is_print = isset($_GET['print']) && $_GET['print'] == 1;

if ($is_print) {
    // Output simplified print-only page
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Laba Rugi - Tahun <?php echo $year; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #fff; font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
            .header-kop { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 25px; }
            table { border: 1px solid #000 !important; }
            th, td { border: 1px solid #000 !important; padding: 6px 10px !important; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="container py-4">
            <div class="no-print mb-4 text-center">
                <button class="btn btn-primary btn-sm px-4 fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Dokumen</button>
                <button class="btn btn-secondary btn-sm px-4" onclick="window.close()">Tutup</button>
            </div>
            
            <div class="header-kop text-center">
                <h4 class="fw-bold m-0 text-uppercase"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'NAMA SEKOLAH'); ?></h4>
                <p class="m-0 small"><?php echo htmlspecialchars($settings['alamat_sekolah'] ?? 'Alamat Sekolah'); ?></p>
                <p class="m-0 small">Telp: <?php echo htmlspecialchars($settings['no_telp'] ?? '-'); ?> &bull; Email: <?php echo htmlspecialchars($settings['email_sekolah'] ?? '-'); ?></p>
            </div>

            <h5 class="text-center fw-bold mb-4">LAPORAN LABA RUGI / SURPLUS KEUANGAN SEKOLAH<br>TAHUN BUKU: <?php echo $year; ?></h5>

            <table class="table table-bordered align-middle">
                <thead class="table-light text-center fw-bold">
                    <tr>
                        <th rowspan="2" class="align-middle">Bulan</th>
                        <th colspan="3" class="text-center">Penerimaan Kas (Incomes)</th>
                        <th colspan="3" class="text-center">Pengeluaran Kas (Expenses)</th>
                        <th rowspan="2" class="align-middle text-end">Selisih (Surplus/Defisit)</th>
                    </tr>
                    <tr>
                        <th>Pemasukan SPP</th>
                        <th>Pemasukan Lain</th>
                        <th>Total Penerimaan</th>
                        <th>Pengeluaran Gaji</th>
                        <th>Pengeluaran Lain</th>
                        <th>Total Pengeluaran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum_spp = $sum_min = $sum_in = $sum_pay = $sum_mex = $sum_ex = $sum_net = 0;
                    foreach ($report_data as $m => $d) {
                        $sum_spp += $d['spp_income'];
                        $sum_min += $d['manual_income'];
                        $sum_in  += $d['total_income'];
                        $sum_pay += $d['payroll_expense'];
                        $sum_mex += $d['manual_expense'];
                        $sum_ex  += $d['total_expense'];
                        $sum_net += $d['net_margin'];
                        ?>
                        <tr>
                            <td><?php echo $d['nama_bulan']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($d['spp_income'], 0, ',', '.'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($d['manual_income'], 0, ',', '.'); ?></td>
                            <td class="text-end fw-semibold">Rp <?php echo number_format($d['total_income'], 0, ',', '.'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($d['payroll_expense'], 0, ',', '.'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($d['manual_expense'], 0, ',', '.'); ?></td>
                            <td class="text-end fw-semibold">Rp <?php echo number_format($d['total_expense'], 0, ',', '.'); ?></td>
                            <td class="text-end fw-bold <?php echo $d['net_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                Rp <?php echo number_format($d['net_margin'], 0, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>Total Kumulatif</td>
                        <td class="text-end">Rp <?php echo number_format($sum_spp, 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($sum_min, 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($sum_in, 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($sum_pay, 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($sum_mex, 0, ',', '.'); ?></td>
                        <td class="text-end">Rp <?php echo number_format($sum_ex, 0, ',', '.'); ?></td>
                        <td class="text-end <?php echo $sum_net >= 0 ? 'text-success' : 'text-danger'; ?>">
                            Rp <?php echo number_format($sum_net, 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="mt-5 d-flex justify-content-end">
                <div class="text-center" style="width: 250px;">
                    <p class="mb-5">Mengetahui,<br>Kepala Sekolah</p>
                    <p class="fw-bold m-0 text-decoration-underline"><?php echo htmlspecialchars($settings['nama_kepsek'] ?? '..........................................'); ?></p>
                    <p class="text-muted m-0" style="font-size: 11px;">NIP. <?php echo htmlspecialchars($settings['nip_kepsek'] ?? '-'); ?></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Laporan Laba Rugi</h4>
    </div>
    <div class="d-flex gap-2">
        <a href="laporan.php?year=<?php echo $year; ?>&print=1" target="_blank" class="btn btn-outline-secondary shadow-sm btn-sm fw-semibold">
            <i class="bi bi-printer"></i> Cetak Laporan (A4)
        </a>
    </div>
</div>

<!-- Year Filter Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-9">
                <label for="year" class="form-label small fw-semibold">Tahun Anggaran Laporan</label>
                <select class="form-select form-select-sm" id="year" name="year" onchange="this.form.submit()">
                    <?php foreach ($year_options as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold"><i class="bi bi-arrow-repeat"></i> Load Laporan</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Chart Visual -->
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-primary me-1"></i> Perbandingan Penerimaan vs Pengeluaran (Tahun <?php echo $year; ?>)</h6>
            </div>
            <div class="card-body p-3">
                <canvas id="lrChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Brief Cash Status Summary -->
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-bookmark-star text-warning me-1"></i> Evaluasi Keuangan Tahunan</h6>
            </div>
            <div class="card-body">
                <?php 
                $sum_in = $sum_ex = 0;
                foreach ($report_data as $m => $d) {
                    $sum_in  += $d['total_income'];
                    $sum_ex  += $d['total_expense'];
                }
                $net_annual = $sum_in - $sum_ex;
                $pct_margin = $sum_in > 0 ? round(($net_annual / $sum_in) * 100, 1) : 0;
                ?>
                <div class="text-center py-2 mb-4 border-bottom">
                    <span class="text-muted small d-block mb-1">Surplus/Defisit Bersih Kumulatif</span>
                    <h2 class="fw-bold font-monospace <?php echo $net_annual >= 0 ? 'text-success' : 'text-danger'; ?>">
                        Rp <?php echo number_format($net_annual, 0, ',', '.'); ?>
                    </h2>
                    <span class="badge <?php echo $net_annual >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-3 mt-1">
                        Margin Keuangan: <?php echo $pct_margin; ?>%
                    </span>
                </div>
                
                <div class="d-flex flex-column gap-3 small text-muted">
                    <div class="d-flex justify-content-between">
                        <span>Akumulasi Penerimaan Kas:</span>
                        <strong class="text-dark-emphasis">Rp <?php echo number_format($sum_in, 0, ',', '.'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Akumulasi Pengeluaran Kas:</span>
                        <strong class="text-dark-emphasis">Rp <?php echo number_format($sum_ex, 0, ',', '.'); ?></strong>
                    </div>
                    <div class="d-flex flex-column bg-light p-3 rounded border border-light-subtle text-dark-emphasis" style="font-size: 11.5px; line-height: 1.5;">
                        <i class="bi bi-lightbulb-fill text-warning mb-1 fs-6"></i>
                        <span>
                            <?php if ($net_annual > 0): ?>
                                Selamat! Buku keuangan sekolah memiliki <strong>surplus positif</strong>. Dana kas saat ini berada dalam posisi sehat untuk dialokasikan ke pembangunan sarana sekolah.
                            <?php else: ?>
                                Perhatian! Aliran dana kas mengalami <strong>defisit operasional</strong>. Evaluasi kembali alokasi pengeluaran non-prioritas di sisa tahun berjalan.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statement Report Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent border-0 pt-3 px-3">
        <h6 class="fw-bold mb-0">Rincian Laba Rugi Bulanan</h6>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th rowspan="2" class="align-middle px-3">Bulan</th>
                        <th colspan="3" class="text-center border-bottom border-light-subtle">Penerimaan Kas (Incomes)</th>
                        <th colspan="3" class="text-center border-bottom border-light-subtle">Pengeluaran Kas (Expenses)</th>
                        <th rowspan="2" class="align-middle text-end px-3">Surplus/Defisit</th>
                    </tr>
                    <tr>
                        <th>Pemasukan SPP</th>
                        <th>Pemasukan Lain</th>
                        <th>Total Penerimaan</th>
                        <th>Pengeluaran Gaji</th>
                        <th>Pengeluaran Lain</th>
                        <th>Total Pengeluaran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum_spp = $sum_min = $sum_in = $sum_pay = $sum_mex = $sum_ex = $sum_net = 0;
                    foreach ($report_data as $m => $d) {
                        $sum_spp += $d['spp_income'];
                        $sum_min += $d['manual_income'];
                        $sum_in  += $d['total_income'];
                        $sum_pay += $d['payroll_expense'];
                        $sum_mex += $d['manual_expense'];
                        $sum_ex  += $d['total_expense'];
                        $sum_net += $d['net_margin'];
                        ?>
                        <tr>
                            <td class="fw-semibold px-3"><?php echo $d['nama_bulan']; ?></td>
                            <td class="font-monospace">Rp <?php echo number_format($d['spp_income'], 0, ',', '.'); ?></td>
                            <td class="font-monospace">Rp <?php echo number_format($d['manual_income'], 0, ',', '.'); ?></td>
                            <td class="font-monospace fw-bold text-secondary-emphasis">Rp <?php echo number_format($d['total_income'], 0, ',', '.'); ?></td>
                            <td class="font-monospace">Rp <?php echo number_format($d['payroll_expense'], 0, ',', '.'); ?></td>
                            <td class="font-monospace">Rp <?php echo number_format($d['manual_expense'], 0, ',', '.'); ?></td>
                            <td class="font-monospace fw-bold text-secondary-emphasis">Rp <?php echo number_format($d['total_expense'], 0, ',', '.'); ?></td>
                            <td class="text-end font-monospace fw-bold px-3 <?php echo $d['net_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                Rp <?php echo number_format($d['net_margin'], 0, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot class="table-light border-top border-dark-subtle fw-bold">
                    <tr>
                        <td class="px-3">Total Kumulatif</td>
                        <td>Rp <?php echo number_format($sum_spp, 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($sum_min, 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($sum_in, 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($sum_pay, 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($sum_mex, 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($sum_ex, 0, ',', '.'); ?></td>
                        <td class="text-end px-3 <?php echo $sum_net >= 0 ? 'text-success' : 'text-danger'; ?>">
                            Rp <?php echo number_format($sum_net, 0, ',', '.'); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Chart JS Setup Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById("lrChart").getContext("2d");
    
    // Map PHP array to JSON arrays
    const months = <?php echo json_encode(array_values($month_names)); ?>;
    const incomes = <?php echo json_encode(array_map(function($d) { return $d['total_income']; }, $report_data)); ?>;
    const expenses = <?php echo json_encode(array_map(function($d) { return $d['total_expense']; }, $report_data)); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Penerimaan Kas (Rp)',
                    data: incomes,
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Pengeluaran Kas (Rp)',
                    data: expenses,
                    backgroundColor: 'rgba(244, 63, 94, 0.75)',
                    borderColor: 'rgb(244, 63, 94)',
                    borderWidth: 1.5,
                    borderRadius: 4
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
                        },
                        font: { size: 9 }
                    }
                },
                x: {
                    ticks: { font: { size: 9 } }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { size: 10, weight: 'bold' } }
                }
            }
        }
    });
});
</script>

<?php include $path_prefix . 'includes/footer.php'; ?>
