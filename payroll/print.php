<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

checkLogin();

$payroll = null;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT p.*, 
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.nama 
                        ELSE k.nama 
                     END AS nama_penerima,
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.jabatan 
                        ELSE k.jabatan 
                     END AS jabatan_penerima,
                     CASE 
                        WHEN p.tipe_penerima = 'guru' THEN g.nip 
                        ELSE k.nik 
                     END AS identitas_penerima
              FROM payroll p 
              LEFT JOIN guru g ON p.tipe_penerima = 'guru' AND p.penerima_id = g.id
              LEFT JOIN karyawan k ON p.tipe_penerima = 'karyawan' AND p.penerima_id = k.id
              WHERE p.id = ?");
        $stmt->execute([$id]);
        $payroll = $stmt->fetch();
        
        if (!$payroll) {
            die("Data payroll tidak ditemukan.");
        }

        // Fetch school settings
        $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
    } catch (PDOException $e) {
        die("Gagal mengambil data: " . $e->getMessage());
    }
} else {
    die("ID Payroll tidak valid.");
}

// Permission lock: Guru can only view their own slip
if ($_SESSION['role'] === 'guru') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM guru WHERE LOWER(REPLACE(nama, ' ', '')) = LOWER(REPLACE(?, ' ', ''))");
        $stmt->execute([$_SESSION['nama_lengkap']]);
        $teacher = $stmt->fetch();
        $teacher_id = $teacher ? $teacher['id'] : 0;
        
        if ($payroll['tipe_penerima'] !== 'guru' || $payroll['penerima_id'] != $teacher_id) {
            die("Otorisasi Gagal: Anda tidak diizinkan untuk melihat/mencetak slip gaji orang lain.");
        }
    } catch (PDOException $e) {
        die("Kesalahan otorisasi.");
    }
}

// Dynamic Indonesian Number-to-Words spelling
function terbilang($nilai) {
    $nilai = abs($nilai);
    $huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
    $temp = "";
    if ($nilai < 12) {
        $temp = " " . $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = terbilang($nilai - 10). " belas";
    } else if ($nilai < 100) {
        $temp = terbilang($nilai/10)." puluh". terbilang($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " seratus" . terbilang($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = terbilang($nilai/100) . " ratus" . terbilang($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " seribu" . terbilang($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = terbilang($nilai/1000) . " ribu" . terbilang($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = terbilang($nilai/1000000) . " juta" . terbilang($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = terbilang($nilai/1000000000) . " milyar" . terbilang(fmod($nilai,1000000000));
    }
    return $temp;
}

function penyebut($nilai) {
    if($nilai < 0) {
        $hasil = "minus ". trim(terbilang($nilai));
    } else {
        $hasil = trim(terbilang($nilai));
    }
    return ucwords($hasil) . " Rupiah";
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?php echo htmlspecialchars($payroll['nama_penerima']); ?> - <?php echo $month_names[$payroll['bulan']]; ?> <?php echo $payroll['tahun']; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Times New Roman', Times, serif;
            font-size: 14px;
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
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .table-component td, .table-component th {
            padding: 8px 12px;
            border: 1px solid #000000 !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-container {
                border: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container no-print my-3 text-center">
    <button class="btn btn-primary btn-sm px-4" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Slip Gaji</button>
    <button class="btn btn-secondary btn-sm px-4" onclick="window.close()">Tutup</button>
</div>

<div class="print-container shadow-sm">
    <!-- Kop Surat Sekolah -->
    <div class="header-kop d-flex align-items-center gap-3 justify-content-center">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo Sekolah" style="width: 80px; height: 80px; object-fit: contain;">
        <?php endif; ?>
        <div class="text-center">
            <h3 class="fw-bold m-0 text-uppercase"><?php echo htmlspecialchars($settings['nama_sekolah']); ?></h3>
            <p class="m-0 small text-muted">
                <?php echo htmlspecialchars($settings['alamat_sekolah']); ?> 
                <?php echo !empty($settings['no_telp']) ? ' Telp: ' . htmlspecialchars($settings['no_telp']) : ''; ?> 
                <?php echo !empty($settings['email_sekolah']) ? ' Email: ' . htmlspecialchars($settings['email_sekolah']) : ''; ?>
                <?php echo !empty($settings['website']) ? ' Web: ' . htmlspecialchars($settings['website']) : ''; ?>
            </p>
        </div>
    </div>
    
    <div class="text-center mb-4">
        <h5 class="fw-bold text-uppercase text-decoration-underline mb-1">Slip Gaji Bulanan Karyawan / Guru</h5>
        <p class="m-0 text-uppercase fw-semibold">Periode: <?php echo $month_names[$payroll['bulan']]; ?> <?php echo $payroll['tahun']; ?></p>
    </div>
    
    <!-- Meta Details Block -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <table class="table table-sm table-borderless m-0">
                <tr>
                    <td style="width: 150px;">Nama Penerima</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold"><?php echo htmlspecialchars($payroll['nama_penerima']); ?></td>
                </tr>
                <tr>
                    <td>No. Identitas (NIP/NIK)</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($payroll['identitas_penerima'] ?: '-'); ?></td>
                </tr>
                <tr>
                    <td>Jabatan / Posisi</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($payroll['jabatan_penerima'] ?: '-'); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-sm table-borderless m-0">
                <tr>
                    <td style="width: 150px;">Status Pembayaran</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold text-uppercase <?php echo $payroll['status_bayar'] === 'Dibayar' ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $payroll['status_bayar']; ?>
                    </td>
                </tr>
                <tr>
                    <td>Tanggal Bayar</td>
                    <td>:</td>
                    <td><?php echo $payroll['tanggal_bayar'] ? date('d F Y', strtotime($payroll['tanggal_bayar'])) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Tipe Penerima</td>
                    <td>:</td>
                    <td class="text-capitalize"><?php echo $payroll['tipe_penerima']; ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Components Table -->
    <div class="row g-0 mb-4">
        <div class="col-12">
            <table class="table table-bordered table-component">
                <thead class="table-light text-center">
                    <tr style="border-bottom: 2px solid #000000;">
                        <th style="width: 50%;">PENERIMAAN (EARNINGS)</th>
                        <th style="width: 50%;">POTONGAN (DEDUCTIONS)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="align-top" style="min-height: 120px;">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Gaji Pokok</span>
                                <span>Rp <?php echo number_format($payroll['gaji_pokok'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tunjangan</span>
                                <span>Rp <?php echo number_format($payroll['tunjangan'], 2, ',', '.'); ?></span>
                            </div>
                        </td>
                        <td class="align-top">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Potongan</span>
                                <span>Rp <?php echo number_format($payroll['potongan'], 2, ',', '.'); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr class="table-light fw-bold" style="border-top: 2px solid #000000;">
                        <td>
                            <div class="d-flex justify-content-between">
                                <span>TOTAL PENERIMAAN (A)</span>
                                <span>Rp <?php echo number_format($payroll['gaji_pokok'] + $payroll['tunjangan'], 2, ',', '.'); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between">
                                <span>TOTAL POTONGAN (B)</span>
                                <span>Rp <?php echo number_format($payroll['potongan'], 2, ',', '.'); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr class="fw-bold" style="background-color: #f1f5f9; font-size: 16px;">
                        <td colspan="2" class="py-3">
                            <div class="d-flex justify-content-between">
                                <span>GAJI BERSIH DITERIMA (A - B)</span>
                                <span>Rp <?php echo number_format($payroll['gaji_bersih'], 2, ',', '.'); ?></span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Written Number (Terbilang) -->
    <div class="bg-light p-3 rounded mb-4 border">
        <span class="text-muted small d-block mb-1">Terbilang (Amount in Words):</span>
        <span class="fw-bold fs-6 text-dark-emphasis italic">"<?php echo penyebut((int)$payroll['gaji_bersih']); ?>"</span>
    </div>

    <!-- Catatan -->
    <?php if (!empty($payroll['catatan'])): ?>
        <div class="mb-5">
            <span class="text-muted small d-block">Catatan Slip:</span>
            <div class="p-2 border rounded bg-light bg-opacity-50 small" style="min-height: 40px;">
                <?php echo nl2br(htmlspecialchars($payroll['catatan'])); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Signatures -->
    <div class="row text-center mt-5">
        <div class="col-4">
            <p class="mb-5">Penerima,</p>
            <br>
            <p class="fw-bold text-decoration-underline m-0"><?php echo htmlspecialchars($payroll['nama_penerima']); ?></p>
            <p class="small text-muted m-0"><?php echo htmlspecialchars($payroll['identitas_penerima'] ?: '-'); ?></p>
        </div>
        
        <div class="col-4 offset-4">
            <p class="m-0">Kota Mandiri, <?php echo date('d M Y'); ?></p>
            <p class="mb-5">Bendahara Sekolah,</p>
            <br>
            <p class="fw-bold text-decoration-underline m-0"><?php echo htmlspecialchars($settings['nama_bendahara']); ?></p>
            <p class="small text-muted m-0">NIP. <?php echo htmlspecialchars($settings['nip_bendahara']); ?></p>
        </div>
    </div>
</div>

<script>
    // Auto launch print
    window.onload = function() {
        window.print();
    }
</script>
</body>
</html>
