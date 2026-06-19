<?php
$path_prefix = '../';
$page_title = 'Rekapitulasi Presensi Siswa';
$active_menu = 'presensi_siswa';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth check
checkRole(['super_admin', 'operator', 'guru', 'kepala_sekolah']);

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get filters
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Fetch all classes
try {
    $classes = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
    if (!$kelas_id && !empty($classes)) {
        $kelas_id = (int)$classes[0]['id'];
    }
} catch (PDOException $e) {
    $classes = [];
}

$num_days = (int)date('t', mktime(0, 0, 0, $bulan, 1, $tahun));

// Data arrays
$students = [];
$attendance_map = [];
$class_name = '';

if ($kelas_id) {
    try {
        // Fetch class name
        $c_stmt = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $c_stmt->execute([$kelas_id]);
        $class_name = $c_stmt->fetchColumn() ?: '';

        // Fetch students in class
        $s_stmt = $pdo->prepare("SELECT id, nama, nis FROM siswa WHERE kelas_id = ? ORDER BY nama ASC");
        $s_stmt->execute([$kelas_id]);
        $students = $s_stmt->fetchAll();

        // Fetch attendance map for the selected month and year
        $att_stmt = $pdo->prepare("
            SELECT ps.siswa_id, ps.tanggal, ps.status
            FROM presensi_siswa ps
            INNER JOIN siswa s ON ps.siswa_id = s.id
            WHERE s.kelas_id = ? AND MONTH(ps.tanggal) = ? AND YEAR(ps.tanggal) = ?
        ");
        $att_stmt->execute([$kelas_id, $bulan, $tahun]);
        $att_rows = $att_stmt->fetchAll();
        foreach ($att_rows as $row) {
            $day = (int)date('d', strtotime($row['tanggal']));
            $attendance_map[$row['siswa_id']][$day] = $row['status'];
        }
    } catch (PDOException $e) {
        $error = 'Kesalahan memuat data: ' . $e->getMessage();
    }
}

// Excel Export logic
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $kelas_id) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=rekap_presensi_{$class_name}_{$bulan}_{$tahun}.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="content-type" content="application/xhtml+xml; charset=UTF-8" />
        <style>
            .text-center { text-align: center; }
            .bg-gray { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h3>REKAPITULASI PRESENSI SISWA - <?php echo htmlspecialchars(strtoupper($class_name)); ?></h3>
        <table>
            <tr><td>Bulan / Tahun:</td><td><strong><?php echo strtoupper($month_names[$bulan]) . ' / ' . $tahun; ?></strong></td></tr>
            <tr><td>Tanggal Cetak:</td><td><?php echo date('d-m-Y H:i'); ?></td></tr>
        </table>
        <br>
        <table border="1" style="border-collapse: collapse;">
            <thead>
                <tr class="bg-gray">
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama Siswa</th>
                    <?php for ($d = 1; $d <= $num_days; $d++): ?>
                        <th width="30"><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th width="35" style="background-color: #d1e7dd;">H</th>
                    <th width="35" style="background-color: #cfe2ff;">S</th>
                    <th width="35" style="background-color: #fff3cd;">I</th>
                    <th width="35" style="background-color: #f8d7da;">A</th>
                    <th width="45">Persentase</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $index => $s): ?>
                    <?php 
                    $h_count = 0; $s_count = 0; $i_count = 0; $a_count = 0;
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td>'<?php echo htmlspecialchars($s['nis']); ?></td>
                        <td><?php echo htmlspecialchars($s['nama']); ?></td>
                        <?php for ($d = 1; $d <= $num_days; $d++): ?>
                            <?php 
                            $status = isset($attendance_map[$s['id']][$d]) ? $attendance_map[$s['id']][$d] : '';
                            $status_abbr = '';
                            if ($status === 'Hadir') { $h_count++; $status_abbr = 'H'; }
                            elseif ($status === 'Sakit') { $s_count++; $status_abbr = 'S'; }
                            elseif ($status === 'Izin') { $i_count++; $status_abbr = 'I'; }
                            elseif ($status === 'Alpa') { $a_count++; $status_abbr = 'A'; }
                            ?>
                            <td class="text-center"><?php echo $status_abbr; ?></td>
                        <?php endfor; ?>
                        <td class="text-center" style="background-color: #e8f5e9;"><?php echo $h_count; ?></td>
                        <td class="text-center" style="background-color: #e3f2fd;"><?php echo $s_count; ?></td>
                        <td class="text-center" style="background-color: #fffde7;"><?php echo $i_count; ?></td>
                        <td class="text-center" style="background-color: #ffebee;"><?php echo $a_count; ?></td>
                        <?php 
                        $total_logs = $h_count + $s_count + $i_count + $a_count;
                        $pct = $total_logs > 0 ? round(($h_count / $total_logs) * 100) : 0;
                        ?>
                        <td class="text-center" font-weight="bold"><?php echo $pct; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit();
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="siswa.php?kelas_id=<?php echo $kelas_id; ?>&tanggal=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Presensi
        </a>
        <h4 class="fw-bold mb-0">Rekapitulasi Presensi</h4>
    </div>
    <?php if ($kelas_id && !empty($students)): ?>
        <a href="?kelas_id=<?php echo $kelas_id; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&export=excel" class="btn btn-success btn-sm fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel
        </a>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="kelas_id" class="form-label fw-semibold small">Kelas</label>
                <select class="form-select" id="kelas_id" name="kelas_id" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $kelas_id === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label for="bulan" class="form-label fw-semibold small">Bulan</label>
                <select class="form-select" id="bulan" name="bulan" required>
                    <?php foreach ($month_names as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $bulan === $num ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="tahun" class="form-label fw-semibold small">Tahun</label>
                <input type="number" class="form-control" id="tahun" name="tahun" value="<?php echo $tahun; ?>" min="2000" max="2100" required>
            </div>

            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-filter"></i> Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<!-- Recap Board -->
<?php if ($kelas_id): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-0 pt-3 px-3">
            <h6 class="fw-bold mb-0">Matriks Presensi Kelas <?php echo htmlspecialchars($class_name); ?></h6>
            <small class="text-muted">Periode: <?php echo $month_names[$bulan] . ' ' . $tahun; ?></small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-info-circle fs-3 mb-2"></i>
                    <p class="mb-0">Tidak ada siswa terdaftar di kelas ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0 text-center" style="font-size: 12px;">
                        <thead class="table-light align-middle">
                            <tr>
                                <th style="min-width: 40px;">No</th>
                                <th style="min-width: 180px;" class="text-start">Nama Siswa</th>
                                <?php for ($d = 1; $d <= $num_days; $d++): ?>
                                    <th style="min-width: 28px; width: 28px; padding: 4px; font-size: 10px;"><?php echo $d; ?></th>
                                <?php endfor; ?>
                                <th class="table-success" style="min-width: 32px; padding: 4px;" title="Hadir">H</th>
                                <th class="table-primary" style="min-width: 32px; padding: 4px;" title="Sakit">S</th>
                                <th class="table-warning" style="min-width: 32px; padding: 4px;" title="Izin">I</th>
                                <th class="table-danger" style="min-width: 32px; padding: 4px;" title="Alpa">A</th>
                                <th style="min-width: 45px; padding: 4px;">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $s): ?>
                                <?php 
                                $h_count = 0; $s_count = 0; $i_count = 0; $a_count = 0;
                                ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $index + 1; ?></td>
                                    <td class="text-start fw-bold text-dark-emphasis"><?php echo htmlspecialchars($s['nama']); ?></td>
                                    <?php for ($d = 1; $d <= $num_days; $d++): ?>
                                        <?php 
                                        $status = isset($attendance_map[$s['id']][$d]) ? $attendance_map[$s['id']][$d] : '';
                                        $cell_class = '';
                                        $cell_text = '';
                                        
                                        if ($status === 'Hadir') {
                                            $h_count++;
                                            $cell_class = 'text-success fw-bold';
                                            $cell_text = 'H';
                                        } elseif ($status === 'Sakit') {
                                            $s_count++;
                                            $cell_class = 'text-primary fw-bold';
                                            $cell_text = 'S';
                                        } elseif ($status === 'Izin') {
                                            $i_count++;
                                            $cell_class = 'text-warning fw-bold';
                                            $cell_text = 'I';
                                        } elseif ($status === 'Alpa') {
                                            $a_count++;
                                            $cell_class = 'text-danger fw-bold';
                                            $cell_text = 'A';
                                        }
                                        ?>
                                        <td class="<?php echo $cell_class; ?>" style="padding: 4px; font-size: 10px;"><?php echo $cell_text; ?></td>
                                    <?php endfor; ?>
                                    
                                    <td class="table-success fw-semibold"><?php echo $h_count; ?></td>
                                    <td class="table-primary fw-semibold"><?php echo $s_count; ?></td>
                                    <td class="table-warning fw-semibold"><?php echo $i_count; ?></td>
                                    <td class="table-danger fw-semibold"><?php echo $a_count; ?></td>
                                    
                                    <?php 
                                    $total_logs = $h_count + $s_count + $i_count + $a_count;
                                    $pct = $total_logs > 0 ? round(($h_count / $total_logs) * 100) : 0;
                                    
                                    $pct_badge_class = 'bg-secondary';
                                    if ($pct >= 90) $pct_badge_class = 'bg-success';
                                    elseif ($pct >= 75) $pct_badge_class = 'bg-warning text-dark';
                                    elseif ($total_logs > 0) $pct_badge_class = 'bg-danger';
                                    ?>
                                    <td>
                                        <span class="badge <?php echo $pct_badge_class; ?>" style="font-size: 10px; padding: 4px 6px;">
                                            <?php echo $pct; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer bg-light small text-muted px-3 py-2 no-print">
                    <div class="row g-2 text-center text-md-start">
                        <div class="col-12 col-md-3"><strong>Keterangan Singkatan:</strong></div>
                        <div class="col-6 col-md-2"><span class="text-success fw-bold">H</span> = Hadir (Present)</div>
                        <div class="col-6 col-md-2"><span class="text-primary fw-bold">S</span> = Sakit (Sick)</div>
                        <div class="col-6 col-md-2"><span class="text-warning fw-bold">I</span> = Izin (Excused)</div>
                        <div class="col-6 col-md-2"><span class="text-danger fw-bold">A</span> = Alpa (Absent)</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm text-center py-5 text-muted">
        <i class="bi bi-file-earmark-spreadsheet fs-1 mb-3 text-secondary-emphasis"></i>
        <h5 class="fw-bold">Pilih Kelas Terlebih Dahulu</h5>
        <p class="small text-muted mb-0">Daftar rekapitulasi presensi bulanan akan ditampilkan setelah kelas dipilih.</p>
    </div>
<?php endif; ?>

<?php include $path_prefix . 'includes/footer.php'; ?>
