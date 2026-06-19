<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth validation - Admins, Operators, and Kepsek can export reports
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

// Retrieve filters from GET
$filter_month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$filter_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$filter_type = isset($_GET['tipe_penerima']) ? $_GET['tipe_penerima'] : '';
$filter_status = isset($_GET['status_bayar']) ? $_GET['status_bayar'] : '';

// Build query
$query = "SELECT p.*, 
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
          WHERE 1=1";
$params = [];

if ($filter_month > 0) {
    $query .= " AND p.bulan = ?";
    $params[] = $filter_month;
}
if ($filter_year > 0) {
    $query .= " AND p.tahun = ?";
    $params[] = $filter_year;
}
if ($filter_type !== '') {
    $query .= " AND p.tipe_penerima = ?";
    $params[] = $filter_type;
}
if ($filter_status !== '') {
    $query .= " AND p.status_bayar = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY p.tahun DESC, p.bulan DESC, nama_penerima ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payrolls = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Define filenames and set response headers
$filename = "Laporan_Payroll_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta charset="utf-8">
<table border="1">
    <thead>
        <tr style="background-color: #4f46e5; color: #ffffff; font-weight: bold;">
            <th>No</th>
            <th>Nama Penerima</th>
            <th>NIP/NIK</th>
            <th>Jabatan</th>
            <th>Tipe</th>
            <th>Bulan</th>
            <th>Tahun</th>
            <th>Gaji Pokok (Rp)</th>
            <th>Tunjangan (Rp)</th>
            <th>Potongan (Rp)</th>
            <th>Gaji Bersih (Rp)</th>
            <th>Status Pembayaran</th>
            <th>Tanggal Pembayaran</th>
            <th>Catatan</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($payrolls as $payroll): 
        ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($payroll['nama_penerima'] ?? 'Tidak Dikenal'); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($payroll['identitas_penerima'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($payroll['jabatan_penerima'] ?? '-'); ?></td>
                <td style="text-transform: capitalize;"><?php echo htmlspecialchars($payroll['tipe_penerima']); ?></td>
                <td><?php echo $month_names[$payroll['bulan']]; ?></td>
                <td><?php echo $payroll['tahun']; ?></td>
                <td style="vnd.ms-excel.numberformat:#,##0"><?php echo (int)$payroll['gaji_pokok']; ?></td>
                <td style="vnd.ms-excel.numberformat:#,##0"><?php echo (int)$payroll['tunjangan']; ?></td>
                <td style="vnd.ms-excel.numberformat:#,##0"><?php echo (int)$payroll['potongan']; ?></td>
                <td style="vnd.ms-excel.numberformat:#,##0"><?php echo (int)$payroll['gaji_bersih']; ?></td>
                <td><?php echo htmlspecialchars($payroll['status_bayar']); ?></td>
                <td><?php echo $payroll['tanggal_bayar'] ?: '-'; ?></td>
                <td><?php echo htmlspecialchars($payroll['catatan']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
