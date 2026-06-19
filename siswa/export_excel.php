<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Auth validation - All logged in users can export
checkLogin();

// Retrieve filters from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_kelas = isset($_GET['kelas_id']) ? $_GET['kelas_id'] : '';

// Build query
$query = "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter_kelas !== '') {
    $query .= " AND s.kelas_id = ?";
    $params[] = $filter_kelas;
}

$query .= " ORDER BY k.nama_kelas ASC, s.nama ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}

// Define filenames and set response headers
$filename = "Data_Siswa_" . date('Ymd_His') . ".xls";
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
            <th>Nama Lengkap</th>
            <th>Kelas</th>
            <th>Jenis Kelamin</th>
            <th>Tempat Lahir</th>
            <th>Tanggal Lahir</th>
            <th>Agama</th>
            <th>Nomor HP</th>
            <th>Email</th>
            <th>Nama Ayah</th>
            <th>NIK Ayah</th>
            <th>Pekerjaan Ayah</th>
            <th>Nama Ibu</th>
            <th>NIK Ibu</th>
            <th>Pekerjaan Ibu</th>
            <th>Nomor HP Orang Tua</th>
            <th>Alamat Orang Tua</th>
            <th>Alamat</th>
            <th>Tahun Masuk</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        foreach ($students as $student): 
        ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <!-- Use string formatting prefix to prevent Excel from removing leading zeros of NIS/NISN -->
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($student['nis']); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($student['nisn']); ?></td>
                <td><?php echo htmlspecialchars($student['nama']); ?></td>
                <td><?php echo htmlspecialchars($student['nama_kelas'] ?? 'Belum Diatur'); ?></td>
                <td><?php echo $student['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                <td><?php echo htmlspecialchars($student['tempat_lahir']); ?></td>
                <td><?php echo htmlspecialchars($student['tanggal_lahir']); ?></td>
                <td><?php echo htmlspecialchars($student['agama']); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($student['no_hp']); ?></td>
                <td><?php echo htmlspecialchars($student['email']); ?></td>
                <td><?php echo htmlspecialchars($student['nama_ayah'] ?? '-'); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($student['nik_ayah'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($student['pekerjaan_ayah'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($student['nama_ibu'] ?? '-'); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($student['nik_ibu'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($student['pekerjaan_ibu'] ?? '-'); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo htmlspecialchars($student['no_hp_ortu'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($student['alamat_ortu'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($student['alamat']); ?></td>
                <td><?php echo htmlspecialchars($student['tahun_masuk']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
