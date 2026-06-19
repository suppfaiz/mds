<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

checkLogin();

$payment = null;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, sw.nama AS nama_siswa, sw.nis, sw.nisn, k.nama_kelas, k.tarif_spp
            FROM spp_pembayaran s
            JOIN siswa sw ON s.siswa_id = sw.id
            LEFT JOIN kelas k ON sw.kelas_id = k.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            die("Data pembayaran SPP tidak ditemukan.");
        }

        // Fetch school settings
        $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
    } catch (PDOException $e) {
        die("Gagal mengambil data: " . $e->getMessage());
    }
} else {
    die("ID Pembayaran tidak valid.");
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
    <title>Kuitansi SPP - <?php echo htmlspecialchars($payment['nama_siswa']); ?> - <?php echo $month_names[$payment['bulan']]; ?> <?php echo $payment['tahun']; ?></title>
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
            max-width: 650px;
            margin: 20px auto;
            padding: 25px;
            border: 1px dashed #000000;
        }
        .header-kop {
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .detail-table td {
            padding: 4px 8px;
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
    <button class="btn btn-primary btn-sm px-4" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Kuitansi</button>
    <button class="btn btn-secondary btn-sm px-4" onclick="window.close()">Tutup</button>
</div>

<div class="print-container">
    <!-- Kop Surat Sekolah -->
    <div class="header-kop d-flex align-items-center gap-3 justify-content-center">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo Sekolah" style="width: 70px; height: 70px; object-fit: contain;">
        <?php endif; ?>
        <div class="text-center">
            <h4 class="fw-bold m-0 text-uppercase"><?php echo htmlspecialchars($settings['nama_sekolah']); ?></h4>
            <p class="m-0 small text-muted" style="font-size: 11px;">
                <?php echo htmlspecialchars($settings['alamat_sekolah']); ?> <br>
                <?php echo !empty($settings['no_telp']) ? ' Telp: ' . htmlspecialchars($settings['no_telp']) : ''; ?> 
                <?php echo !empty($settings['email_sekolah']) ? ' Email: ' . htmlspecialchars($settings['email_sekolah']) : ''; ?>
                <?php echo !empty($settings['website']) ? ' Web: ' . htmlspecialchars($settings['website']) : ''; ?>
            </p>
        </div>
    </div>
    
    <div class="text-center mb-3">
        <h5 class="fw-bold text-uppercase text-decoration-underline mb-0" style="letter-spacing: 1px;">Kuitansi Pembayaran SPP</h5>
        <small class="text-muted">No. Resi SPP: <?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></small>
    </div>
    
    <!-- Meta Details Block -->
    <table class="table table-sm table-borderless detail-table mb-3">
        <tr>
            <td style="width: 160px;">Telah Diterima Dari</td>
            <td style="width: 15px;">:</td>
            <td class="fw-bold text-uppercase"><?php echo htmlspecialchars($payment['nama_siswa']); ?></td>
        </tr>
        <tr>
            <td>NIS / NISN</td>
            <td>:</td>
            <td><?php echo htmlspecialchars($payment['nis']); ?> / <?php echo htmlspecialchars($payment['nisn']); ?></td>
        </tr>
        <tr>
            <td>Kelas</td>
            <td>:</td>
            <td><?php echo htmlspecialchars($payment['nama_kelas'] ?? 'Belum Diatur'); ?></td>
        </tr>
        <tr>
            <td>Untuk Pembayaran</td>
            <td>:</td>
            <td>SPP Bulanan Periode <strong class="text-primary-emphasis"><?php echo $month_names[$payment['bulan']]; ?> <?php echo $payment['tahun']; ?></strong></td>
        </tr>
        <tr>
            <td>Jumlah Nominal</td>
            <td>:</td>
            <td><strong class="fs-5 text-dark-emphasis">Rp <?php echo number_format($payment['jumlah_bayar'], 0, ',', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Terbilang</td>
            <td>:</td>
            <td class="fst-italic fw-semibold text-secondary">"<?php echo penyebut((int)$payment['jumlah_bayar']); ?>"</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>:</td>
            <td>
                <span class="fw-bold text-uppercase text-success">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($payment['status_bayar']); ?>
                </span>
            </td>
        </tr>
        <?php if (!empty($payment['catatan'])): ?>
            <tr>
                <td>Catatan</td>
                <td>:</td>
                <td class="small text-muted"><?php echo htmlspecialchars($payment['catatan']); ?></td>
            </tr>
        <?php endif; ?>
    </table>
    
    <!-- Signatures -->
    <div class="row text-center mt-4 pt-2" style="font-size: 12px;">
        <div class="col-6">
            <p class="mb-5">Siswa / Penyetor,</p>
            <br>
            <p class="fw-bold text-decoration-underline m-0"><?php echo htmlspecialchars($payment['nama_siswa']); ?></p>
            <p class="small text-muted m-0">Penyetor</p>
        </div>
        
        <div class="col-6">
            <p class="m-0">Kota Mandiri, <?php echo date('d F Y', strtotime($payment['tanggal_bayar'])); ?></p>
            <p class="mb-5">Bendahara Sekolah,</p>
            <br>
            <p class="fw-bold text-decoration-underline m-0"><?php echo htmlspecialchars($settings['nama_bendahara']); ?></p>
            <p class="small text-muted m-0">NIP. <?php echo htmlspecialchars($settings['nip_bendahara']); ?></p>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        window.print();
    }
</script>
</body>
</html>
