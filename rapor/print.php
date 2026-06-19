<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth check
checkLogin();

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
if (!$siswa_id) {
    die("ID Siswa tidak valid.");
}

$semester = isset($_GET['semester']) ? $_GET['semester'] : 'Ganjil';
$tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '2025/2026';

try {
    // 1. Fetch Student details
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$siswa_id]);
    $siswa = $stmt->fetch();
    
    if (!$siswa) {
        die("Siswa tidak ditemukan.");
    }

    // 2. Fetch School settings
    $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();

    // 3. Fetch Rapor Notes
    $r_stmt = $pdo->prepare("SELECT * FROM rapor_catatan WHERE siswa_id = ? AND semester = ? AND tahun_ajaran = ?");
    $r_stmt->execute([$siswa_id, $semester, $tahun_ajaran]);
    $rapor = $r_stmt->fetch();

    // 4. Fetch Academic Grades
    $g_stmt = $pdo->prepare("SELECT * FROM nilai WHERE siswa_id = ? AND semester = ? AND tahun_ajaran = ? ORDER BY mata_pelajaran ASC");
    $g_stmt->execute([$siswa_id, $semester, $tahun_ajaran]);
    $grades = $g_stmt->fetchAll();

    // 5. Fetch Wali Kelas name
    $wali_name = '';
    $wali_nip = '';
    $wk_stmt = $pdo->prepare("
        SELECT g.nama, g.nip 
        FROM wali_kelas wk
        INNER JOIN guru g ON wk.guru_id = g.id
        WHERE wk.kelas_id = ?
    ");
    $wk_stmt->execute([$siswa['kelas_id']]);
    $wk_info = $wk_stmt->fetch();
    if ($wk_info) {
        $wali_name = $wk_info['nama'];
        $wali_nip = $wk_info['nip'];
    }

    // 6. Fetch Attendance Summary
    list($year_start, $year_end) = explode('/', $tahun_ajaran);
    if ($semester === 'Ganjil') {
        $date_start = $year_start . '-07-01';
        $date_end = $year_start . '-12-31';
    } else {
        $date_start = $year_end . '-01-01';
        $date_end = $year_end . '-06-30';
    }

    $att_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as jumlah 
        FROM presensi_siswa 
        WHERE siswa_id = ? AND tanggal BETWEEN ? AND ? 
        GROUP BY status
    ");
    $att_stmt->execute([$siswa_id, $date_start, $date_end]);
    $att_rows = $att_stmt->fetchAll();
    $att_summary = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
    foreach ($att_rows as $ar) {
        $att_summary[$ar['status']] = (int)$ar['jumlah'];
    }

} catch (PDOException $e) {
    die("Kesalahan database: " . $e->getMessage());
}

// Convert trait letter to description text helper
function getTraitLabel($letter) {
    switch ($letter) {
        case 'A': return 'Sangat Baik';
        case 'B': return 'Baik';
        case 'C': return 'Cukup';
        case 'D': return 'Kurang';
        default: return 'Baik';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor Siswa - <?php echo htmlspecialchars($siswa['nama']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
            color: #000000;
        }
        .print-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            border: 1px solid #dee2e6;
        }
        .header-kop {
            border-bottom: 3px double #000000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .table-rapor th, .table-rapor td {
            border: 1px solid #000000 !important;
            padding: 6px 10px !important;
        }
        .signature-section {
            margin-top: 40px;
        }
        .sig-box {
            width: 30%;
            text-align: center;
        }
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
                margin: 0;
            }
            .print-container {
                border: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- Floating Print Panel (Screen Only) -->
<div class="container max-width-800 no-print mt-3 text-center">
    <div class="alert alert-light border shadow-sm d-inline-flex gap-2 align-items-center py-2 px-4">
        <span>Aplikasi siap mencetak Rapor Digital Siswa.</span>
        <button class="btn btn-sm btn-primary fw-bold px-3" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Rapor
        </button>
        <button class="btn btn-sm btn-secondary fw-semibold" onclick="window.close()">
            <i class="bi bi-x-lg"></i> Tutup
        </button>
    </div>
</div>

<div class="print-container shadow-sm bg-white">
    <!-- Kop Surat Sekolah -->
    <div class="header-kop text-center">
        <h4 class="fw-bold m-0"><?php echo htmlspecialchars($settings['nama_sekolah'] ?? 'NAMA SEKOLAH'); ?></h4>
        <p class="m-0 small"><?php echo htmlspecialchars($settings['alamat_sekolah'] ?? 'Alamat Sekolah'); ?></p>
        <p class="m-0 small">Telp: <?php echo htmlspecialchars($settings['no_telp'] ?? '-'); ?> &bull; Email: <?php echo htmlspecialchars($settings['email_sekolah'] ?? '-'); ?></p>
    </div>

    <h5 class="text-center fw-bold text-uppercase mb-4">LAPORAN HASIL BELAJAR SISWA (RAPOR)</h5>

    <!-- Student Info Row -->
    <div class="row g-2 mb-4">
        <div class="col-7">
            <table class="w-100 table-sm table-borderless">
                <tr>
                    <td style="width: 120px;">Nama Siswa</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold"><?php echo htmlspecialchars($siswa['nama']); ?></td>
                </tr>
                <tr>
                    <td>NIS / NISN</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['nis'] . ' / ' . $siswa['nisn']); ?></td>
                </tr>
                <tr>
                    <td>Kelas</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Diatur'); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-5">
            <table class="w-100 table-sm table-borderless">
                <tr>
                    <td style="width: 120px;">Semester</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold"><?php echo htmlspecialchars($semester); ?></td>
                </tr>
                <tr>
                    <td>Tahun Ajaran</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($tahun_ajaran); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 1. Academic Grades Table -->
    <h6 class="fw-bold border-bottom pb-1 mb-2">A. NILAI AKADEMIK</h6>
    <table class="table table-bordered table-rapor align-middle mb-4">
        <thead class="table-light text-center fw-bold">
            <tr>
                <th style="width: 50px;">No</th>
                <th>Mata Pelajaran</th>
                <th style="width: 80px;">Tugas (30%)</th>
                <th style="width: 80px;">UTS (30%)</th>
                <th style="width: 80px;">UAS (40%)</th>
                <th style="width: 90px;">Nilai Akhir</th>
                <th style="width: 80px;">Predikat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($grades)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted italic small py-3">Belum ada data nilai akademik semester ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($grades as $index => $g): 
                    $final = (float)$g['nilai_akhir'];
                    if ($final >= 85) { $pred = 'A (Sangat Baik)'; }
                    elseif ($final >= 75) { $pred = 'B (Baik)'; }
                    elseif ($final >= 60) { $pred = 'C (Cukup)'; }
                    else { $pred = 'D (Kurang)'; }
                ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($g['mata_pelajaran']); ?></td>
                        <td class="text-center"><?php echo number_format($g['nilai_tugas'], 1); ?></td>
                        <td class="text-center"><?php echo number_format($g['nilai_uts'], 1); ?></td>
                        <td class="text-center"><?php echo number_format($g['nilai_uas'], 1); ?></td>
                        <td class="text-center fw-bold"><?php echo number_format($final, 1); ?></td>
                        <td class="text-center fw-semibold"><?php echo $pred; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row g-4 mb-4">
        <!-- 2. Personality Traits Table -->
        <div class="col-6">
            <h6 class="fw-bold border-bottom pb-1 mb-2">B. ASPEK KEPRIBADIAN & SIKAP</h6>
            <table class="table table-bordered table-rapor mb-0">
                <thead class="table-light fw-bold">
                    <tr>
                        <th style="width: 40px;" class="text-center">No</th>
                        <th>Aspek Kepribadian</th>
                        <th style="width: 120px;" class="text-center">Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">1</td>
                        <td>Kelakuan</td>
                        <td class="text-center fw-semibold"><?php echo getTraitLabel($rapor['kelakuan'] ?? 'B'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-center">2</td>
                        <td>Kerajinan</td>
                        <td class="text-center fw-semibold"><?php echo getTraitLabel($rapor['kerajinan'] ?? 'B'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-center">3</td>
                        <td>Kerapihan</td>
                        <td class="text-center fw-semibold"><?php echo getTraitLabel($rapor['kerapihan'] ?? 'B'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 3. Attendance Counter Table -->
        <div class="col-6">
            <h6 class="fw-bold border-bottom pb-1 mb-2">C. KETIDAKHADIRAN</h6>
            <table class="table table-bordered table-rapor mb-0">
                <thead class="table-light fw-bold">
                    <tr>
                        <th style="width: 40px;" class="text-center">No</th>
                        <th>Alasan Ketidakhadiran</th>
                        <th style="width: 120px;" class="text-center">Jumlah Hari</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">1</td>
                        <td>Sakit (S)</td>
                        <td class="text-center fw-bold"><?php echo $att_summary['Sakit']; ?> Hari</td>
                    </tr>
                    <tr>
                        <td class="text-center">2</td>
                        <td>Izin (I)</td>
                        <td class="text-center fw-bold"><?php echo $att_summary['Izin']; ?> Hari</td>
                    </tr>
                    <tr>
                        <td class="text-center">3</td>
                        <td>Tanpa Keterangan / Alpa (A)</td>
                        <td class="text-center fw-bold"><?php echo $att_summary['Alpa']; ?> Hari</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. Extracurricular List -->
    <h6 class="fw-bold border-bottom pb-1 mb-2">D. KEGIATAN EKSTRAKURIKULER</h6>
    <div class="p-3 border rounded mb-4 bg-light bg-opacity-25" style="min-height: 60px; font-size: 13px;">
        <?php 
        if (!empty($rapor['ekstrakurikuler'])) {
            echo nl2br(htmlspecialchars($rapor['ekstrakurikuler']));
        } else {
            echo '<span class="text-muted italic">Tidak ada kegiatan ekstrakurikuler yang diikuti.</span>';
        }
        ?>
    </div>

    <!-- 5. Advisor Remarks -->
    <h6 class="fw-bold border-bottom pb-1 mb-2">E. CATATAN WALI KELAS</h6>
    <div class="p-3 border rounded mb-5 bg-light bg-opacity-25 font-monospace" style="min-height: 80px; font-size: 13px; line-height: 1.5;">
        <?php 
        if (!empty($rapor['catatan'])) {
            echo nl2br(htmlspecialchars($rapor['catatan']));
        } else {
            echo '<span class="text-muted italic">-</span>';
        }
        ?>
    </div>

    <!-- 6. Signatures Section -->
    <div class="d-flex justify-content-between signature-section small">
        <div class="sig-box">
            <p class="mb-5">Mengetahui,<br>Orang Tua / Wali Siswa</p>
            <p class="fw-bold mt-4 mb-0">_____________________</p>
        </div>
        
        <div class="sig-box">
            <?php 
            $sign_date = date('d-m-Y');
            ?>
            <p class="mb-5">Dibuat di: Bandung<br>Tanggal: <?php echo date('d F Y'); ?></p>
            <p class="fw-bold m-0 text-decoration-underline"><?php echo htmlspecialchars($wali_name ?: '..........................................'); ?></p>
            <p class="text-muted font-monospace m-0" style="font-size: 11px;">Wali Kelas <?php echo !empty($wali_nip) ? 'NIP. ' . htmlspecialchars($wali_nip) : ''; ?></p>
        </div>

        <div class="sig-box">
            <p class="mb-5">Mengetahui,<br>Kepala Sekolah</p>
            <p class="fw-bold m-0 text-decoration-underline"><?php echo htmlspecialchars($settings['nama_kepsek'] ?? '..........................................'); ?></p>
            <p class="text-muted font-monospace m-0" style="font-size: 11px;">NIP. <?php echo htmlspecialchars($settings['nip_kepsek'] ?? ''); ?></p>
        </div>
    </div>
</div>

<script>
window.addEventListener("load", function() {
    // Auto launch print dialog on load
    window.print();
});
</script>
</body>
</html>
