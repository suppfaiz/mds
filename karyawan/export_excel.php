<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth validation - All logged in users can export
checkLogin();

// Retrieve filters from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "SELECT * FROM karyawan WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (nama LIKE ? OR nik LIKE ? OR jabatan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY nama ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}

// Define filenames and set response headers
$filename = "Data_Karyawan_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta charset="utf-8">
<table border="1">
    <thead>
        <tr style="background-color: #06b6d4; color: #ffffff; font-weight: bold;">
            <th>No</th>
            <th>NIK</th>
            <th>Nama Lengkap</th>
            <th>Jabatan</th>
            <th>Nomor HP</th>
            <th>Email</th>
            <th>Alamat</th>
            <th>Tanggal Registrasi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($employees as $employee): 
        ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <!-- Use string formatting prefix to prevent Excel from removing leading zeros of NIK -->
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($employee['nik']); ?></td>
                <td><?php echo htmlspecialchars($employee['nama']); ?></td>
                <td><?php echo htmlspecialchars($employee['jabatan']); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($employee['no_hp']); ?></td>
                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                <td><?php echo htmlspecialchars($employee['alamat']); ?></td>
                <td><?php echo htmlspecialchars($employee['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
