<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth validation - Admin, Operator, and Principal can export
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

// Filters
$search = trim($_GET['search'] ?? '');
$kelas_id = $_GET['kelas_id'] ?? '';
$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? '';
$status = $_GET['status'] ?? '';

// Build Query
$query = "
    SELECT s.bulan, s.tahun, s.jumlah_bayar, s.tanggal_bayar, s.status_bayar, s.penerima_oleh, s.catatan,
           sw.nama AS nama_siswa, sw.nis, sw.nisn, k.nama_kelas, k.tarif_spp
    FROM spp_pembayaran s
    JOIN siswa sw ON s.siswa_id = sw.id
    LEFT JOIN kelas k ON sw.kelas_id = k.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (sw.nama LIKE ? OR sw.nis LIKE ? OR sw.nisn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kelas_id)) {
    $query .= " AND sw.kelas_id = ?";
    $params[] = (int)$kelas_id;
}

if (!empty($bulan)) {
    $query .= " AND s.bulan = ?";
    $params[] = (int)$bulan;
}

if (!empty($tahun)) {
    $query .= " AND s.tahun = ?";
    $params[] = (int)$tahun;
}

if (!empty($status)) {
    $query .= " AND s.status_bayar = ?";
    $params[] = $status;
}

$query .= " ORDER BY s.tanggal_bayar DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$filename = "Laporan_SPP_Siswa_" . date('Ymd_His') . ".xls";
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
            <th>NIS</th>
            <th>NISN</th>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Bulan SPP</th>
            <th>Tahun SPP</th>
            <th>Tarif SPP Kelas (Rp)</th>
            <th>Jumlah Dibayar (Rp)</th>
            <th>Tanggal Bayar</th>
            <th>Status Pembayaran</th>
            <th>Penerima (Kasir)</th>
            <th>Catatan</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($payments as $p): 
        ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($p['nis']); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($p['nisn']); ?></td>
                <td><?php echo htmlspecialchars($p['nama_siswa']); ?></td>
                <td><?php echo htmlspecialchars($p['nama_kelas'] ?? 'Belum Diatur'); ?></td>
                <td><?php echo $month_names[$p['bulan']]; ?></td>
                <td><?php echo $p['tahun']; ?></td>
                <td style="vnd.ms-excel.numberformat:#,##0"><?php echo (int)($p['tarif_spp'] ?? 0); ?></td>
                <td style="vnd.ms-excel.numberformat:#,##0"><?php echo (int)$p['jumlah_bayar']; ?></td>
                <td><?php echo date('Y-m-d', strtotime($p['tanggal_bayar'])); ?></td>
                <td><?php echo htmlspecialchars($p['status_bayar']); ?></td>
                <td><?php echo htmlspecialchars($p['penerima_oleh']); ?></td>
                <td><?php echo htmlspecialchars($p['catatan']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
