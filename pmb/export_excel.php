<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth check: Block Guru
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(nama LIKE :search OR no_pendaftaran LIKE :search OR asal_sekolah LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($status)) {
    $where_clauses[] = "status = :status";
    $params['status'] = $status;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

try {
    $query_sql = "SELECT * FROM pmb_pendaftar $where_sql ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query_sql);
    $stmt->execute($params);
    $applicants = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memproses data ekspor: " . $e->getMessage());
}

// Set Headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Data_Pendaftar_PMB_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        .table {
            border-collapse: collapse;
            width: 100%;
        }
        .table th, .table td {
            border: 1px solid #000000;
            padding: 8px;
            font-family: sans-serif;
            font-size: 11px;
        }
        .table th {
            background-color: #e2e8f0;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div style="text-align: center; font-family: sans-serif; margin-bottom: 20px;">
        <h3>LAPORAN PENDAFTARAN MURID BARU (PMB)</h3>
        <p style="font-size: 11px;">Tanggal Ekspor: <?php echo date('d-m-Y H:i:s'); ?></p>
        <?php if (!empty($status)): ?>
            <p style="font-size: 11px;">Saringan Status: <?php echo $status; ?></p>
        <?php endif; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th style="width: 140px;">No. Pendaftaran</th>
                <th style="width: 200px;">Nama Calon Murid</th>
                <th style="width: 100px;">Gender</th>
                <th style="width: 150px;">Tempat Lahir</th>
                <th style="width: 100px;">Tanggal Lahir</th>
                <th style="width: 180px;">Asal Sekolah</th>
                <th style="width: 180px;">Nama Orang Tua</th>
                <th style="width: 120px;">No. Kontak HP</th>
                <th style="width: 250px;">Alamat Domisili</th>
                <th style="width: 100px;">Status</th>
                <th style="width: 120px;">Waktu Daftar</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applicants)): ?>
                <tr>
                    <td colspan="12" class="text-center">Tidak ada data pendaftar.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($applicants as $index => $a): ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($a['no_pendaftaran']); ?></td>
                        <td><?php echo htmlspecialchars($a['nama']); ?></td>
                        <td class="text-center"><?php echo $a['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                        <td><?php echo htmlspecialchars($a['tempat_lahir']); ?></td>
                        <td class="text-center"><?php echo date('d-m-Y', strtotime($a['tanggal_lahir'])); ?></td>
                        <td><?php echo htmlspecialchars($a['asal_sekolah']); ?></td>
                        <td><?php echo htmlspecialchars($a['nama_ortu']); ?></td>
                        <td>'<?php echo htmlspecialchars($a['no_hp']); ?></td> <!-- added quote prefix to avoid excel dropping leading zero -->
                        <td><?php echo htmlspecialchars($a['alamat']); ?></td>
                        <td class="text-center"><?php echo $a['status']; ?></td>
                        <td class="text-center"><?php echo date('d-m-Y H:i', strtotime($a['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
