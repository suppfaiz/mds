<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth: Block Guru
checkRole(['super_admin', 'operator', 'kepala_sekolah']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tipe = isset($_GET['tipe']) ? trim($_GET['tipe']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(keterangan LIKE :search OR kategori LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($tipe)) {
    $where_clauses[] = "tipe = :tipe";
    $params['tipe'] = $tipe;
}
if (!empty($kategori)) {
    $where_clauses[] = "kategori = :kategori";
    $params['kategori'] = $kategori;
}
if (!empty($start_date)) {
    $where_clauses[] = "tanggal >= :start_date";
    $params['start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "tanggal <= :end_date";
    $params['end_date'] = $end_date;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

try {
    $query_sql = "SELECT * FROM keuangan_transaksi $where_sql ORDER BY tanggal DESC, id DESC";
    $stmt = $pdo->prepare($query_sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal memproses data ekspor: " . $e->getMessage());
}

// Set Headers for Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Buku_Kas_Umum_" . date('Ymd_His') . ".xls");
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
        .text-end { text-align: right; }
    </style>
</head>
<body>
    <div style="text-align: center; font-family: sans-serif; margin-bottom: 20px;">
        <h3>LAPORAN ARUS BUKU KAS UMUM</h3>
        <p style="font-size: 11px;">Tanggal Ekspor: <?php echo date('d-m-Y H:i:s'); ?></p>
        <?php if (!empty($start_date) || !empty($end_date)): ?>
            <p style="font-size: 11px;">Periode: <?php echo $start_date ?: '*'; ?> s/d <?php echo $end_date ?: '*'; ?></p>
        <?php endif; ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th style="width: 100px;">Tanggal</th>
                <th style="width: 100px;">Tipe</th>
                <th style="width: 150px;">Kategori</th>
                <th style="width: 150px;">Nominal (Rp)</th>
                <th style="width: 300px;">Keterangan</th>
                <th style="width: 120px;">Pencatat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="7" class="text-center">Tidak ada data transaksi.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $index => $t): ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td class="text-center"><?php echo date('d-m-Y', strtotime($t['tanggal'])); ?></td>
                        <td class="text-center"><?php echo $t['tipe']; ?></td>
                        <td><?php echo htmlspecialchars($t['kategori']); ?></td>
                        <td class="text-end"><?php echo number_format($t['nominal'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($t['keterangan'] ?: '-'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($t['pencatat']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
