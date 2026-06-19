<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth validation - All logged in users can export
checkLogin();

// Retrieve filters from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "SELECT * FROM guru WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (nama LIKE ? OR nip LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY nama ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}

// Define filenames and set response headers
$filename = "Data_Guru_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta charset="utf-8">
<table border="1">
    <thead>
        <tr style="background-color: #059669; color: #ffffff; font-weight: bold;">
            <th>No</th>
            <th>NIP</th>
            <th>Nama Lengkap</th>
            <th>Mata Pelajaran</th>
            <th>Jabatan</th>
            <th>Pendidikan Terakhir</th>
            <th>Nomor HP</th>
            <th>Email</th>
            <th>Alamat</th>
            <th>Tanggal Registrasi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($teachers as $teacher): 
        ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <!-- Use string formatting prefix to prevent Excel from removing leading zeros of NIP -->
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($teacher['nip'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($teacher['nama']); ?></td>
                <td><?php echo htmlspecialchars($teacher['mata_pelajaran']); ?></td>
                <td><?php echo htmlspecialchars($teacher['jabatan']); ?></td>
                <td><?php echo htmlspecialchars($teacher['pendidikan_terakhir']); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($teacher['no_hp']); ?></td>
                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                <td><?php echo htmlspecialchars($teacher['alamat']); ?></td>
                <td><?php echo htmlspecialchars($teacher['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
