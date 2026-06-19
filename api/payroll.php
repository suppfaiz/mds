<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/audit.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
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
                             END AS jabatan_penerima
                      FROM payroll p 
                      LEFT JOIN guru g ON p.tipe_penerima = 'guru' AND p.penerima_id = g.id
                      LEFT JOIN karyawan k ON p.tipe_penerima = 'karyawan' AND p.penerima_id = k.id
                      WHERE p.id = ?");
                $stmt->execute([$id]);
                $payroll = $stmt->fetch();
                
                if ($payroll) {
                    http_response_code(200);
                    echo json_encode([
                        "status" => "success",
                        "data" => $payroll
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Data payroll tidak ditemukan."
                    ]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // Get filtered list
            $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
            $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
            $tipe_penerima = $_GET['tipe_penerima'] ?? '';
            $status_bayar = $_GET['status_bayar'] ?? '';
            
            $query = "SELECT p.*, 
                             CASE 
                                WHEN p.tipe_penerima = 'guru' THEN g.nama 
                                ELSE k.nama 
                             END AS nama_penerima,
                             CASE 
                                WHEN p.tipe_penerima = 'guru' THEN g.jabatan 
                                ELSE k.jabatan 
                             END AS jabatan_penerima
                      FROM payroll p 
                      LEFT JOIN guru g ON p.tipe_penerima = 'guru' AND p.penerima_id = g.id
                      LEFT JOIN karyawan k ON p.tipe_penerima = 'karyawan' AND p.penerima_id = k.id
                      WHERE 1=1";
            $params = [];
            
            if ($bulan > 0) {
                $query .= " AND p.bulan = ?";
                $params[] = $bulan;
            }
            if ($tahun > 0) {
                $query .= " AND p.tahun = ?";
                $params[] = $tahun;
            }
            if (!empty($tipe_penerima)) {
                $query .= " AND p.tipe_penerima = ?";
                $params[] = $tipe_penerima;
            }
            if (!empty($status_bayar)) {
                $query .= " AND p.status_bayar = ?";
                $params[] = $status_bayar;
            }
            
            $query .= " ORDER BY p.id DESC";
            
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $payrolls = $stmt->fetchAll();
                
                http_response_code(200);
                echo json_encode([
                    "status" => "success",
                    "count" => count($payrolls),
                    "data" => $payrolls
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;

    case 'POST':
        // Create payroll record
        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) {
            $input = $_POST;
        }

        $tipe_penerima = $input['tipe_penerima'] ?? '';
        $penerima_id = isset($input['penerima_id']) ? (int)$input['penerima_id'] : 0;
        $bulan = isset($input['bulan']) ? (int)$input['bulan'] : 0;
        $tahun = isset($input['tahun']) ? (int)$input['tahun'] : 0;
        
        $gaji_pokok = isset($input['gaji_pokok']) ? (float)$input['gaji_pokok'] : 0.0;
        $tunjangan = isset($input['tunjangan']) ? (float)$input['tunjangan'] : 0.0;
        $potongan = isset($input['potongan']) ? (float)$input['potongan'] : 0.0;
        $gaji_bersih = $gaji_pokok + $tunjangan - $potongan;
        
        $status_bayar = $input['status_bayar'] ?? 'Belum Dibayar';
        $tanggal_bayar = !empty($input['tanggal_bayar']) ? $input['tanggal_bayar'] : null;
        $catatan = trim($input['catatan'] ?? '');

        if (!in_array($tipe_penerima, ['guru', 'karyawan']) || $penerima_id <= 0 || $bulan < 1 || $bulan > 12 || $tahun <= 0) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Gagal menyimpan. Isian wajib (tipe_penerima, penerima_id, bulan, tahun) tidak boleh kosong."
            ]);
            exit();
        }

        try {
            // Check uniqueness
            $check = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE tipe_penerima = ? AND penerima_id = ? AND bulan = ? AND tahun = ?");
            $check->execute([$tipe_penerima, $penerima_id, $bulan, $tahun]);
            if ($check->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    "status" => "error",
                    "message" => "Gagal menyimpan. Gaji untuk penerima tersebut pada periode ini sudah terdaftar."
                ]);
                exit();
            }

            if ($status_bayar === 'Dibayar' && $tanggal_bayar === null) {
                $tanggal_bayar = date('Y-m-d');
            }
            if ($status_bayar === 'Belum Dibayar') {
                $tanggal_bayar = null;
            }

            $stmt = $pdo->prepare("INSERT INTO payroll 
                (tipe_penerima, penerima_id, bulan, tahun, gaji_pokok, tunjangan, potongan, gaji_bersih, status_bayar, tanggal_bayar, catatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $tipe_penerima, $penerima_id, $bulan, $tahun,
                $gaji_pokok, $tunjangan, $potongan, $gaji_bersih,
                $status_bayar, $tanggal_bayar, $catatan
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // Fetch name for log
            $p_name = 'Penerima';
            if ($tipe_penerima === 'guru') {
                $n_stmt = $pdo->prepare("SELECT nama FROM guru WHERE id = ?");
                $n_stmt->execute([$penerima_id]);
                $p_name = $n_stmt->fetchColumn() ?: 'Guru';
            } else {
                $n_stmt = $pdo->prepare("SELECT nama FROM karyawan WHERE id = ?");
                $n_stmt->execute([$penerima_id]);
                $p_name = $n_stmt->fetchColumn() ?: 'Karyawan';
            }

            logActivity($pdo, 'API: Tambah Payroll', 'Menginput gaji via API untuk ' . $p_name . ' (' . $tipe_penerima . ') periode ' . $bulan . '/' . $tahun . ' (ID: ' . $new_id . ')');

            http_response_code(201); // Created
            echo json_encode([
                "status" => "success",
                "message" => "Data gaji berhasil ditambahkan.",
                "id" => $new_id
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update payroll record
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID Payroll tidak valid."]);
            exit();
        }

        try {
            // Verify record exists
            $check = $pdo->prepare("SELECT * FROM payroll WHERE id = ?");
            $check->execute([$id]);
            $payroll = $check->fetch();

            if (!$payroll) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Data payroll tidak ditemukan."]);
                exit();
            }

            $gaji_pokok = isset($input['gaji_pokok']) ? (float)$input['gaji_pokok'] : (float)$payroll['gaji_pokok'];
            $tunjangan = isset($input['tunjangan']) ? (float)$input['tunjangan'] : (float)$payroll['tunjangan'];
            $potongan = isset($input['potongan']) ? (float)$input['potongan'] : (float)$payroll['potongan'];
            $gaji_bersih = $gaji_pokok + $tunjangan - $potongan;
            
            $status_bayar = $input['status_bayar'] ?? $payroll['status_bayar'];
            $tanggal_bayar = isset($input['tanggal_bayar']) ? $input['tanggal_bayar'] : $payroll['tanggal_bayar'];
            $catatan = isset($input['catatan']) ? trim($input['catatan']) : $payroll['catatan'];

            if ($status_bayar === 'Dibayar' && $tanggal_bayar === null) {
                $tanggal_bayar = date('Y-m-d');
            }
            if ($status_bayar === 'Belum Dibayar') {
                $tanggal_bayar = null;
            }

            $stmt = $pdo->prepare("UPDATE payroll SET 
                gaji_pokok = ?, tunjangan = ?, potongan = ?, gaji_bersih = ?, 
                status_bayar = ?, tanggal_bayar = ?, catatan = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $gaji_pokok, $tunjangan, $potongan, $gaji_bersih,
                $status_bayar, $tanggal_bayar, $catatan, $id
            ]);

            logActivity($pdo, 'API: Edit Payroll', 'Mengupdate gaji via API untuk ID: ' . $id);
            
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Data gaji berhasil diupdate."
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete payroll record
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID Payroll tidak valid."]);
            exit();
        }

        try {
            // Verify record exists
            $stmt = $pdo->prepare("SELECT id FROM payroll WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Data payroll tidak ditemukan."]);
                exit();
            }

            $del_stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
            $del_stmt->execute([$id]);

            logActivity($pdo, 'API: Hapus Payroll', 'Menghapus data gaji via API untuk ID: ' . $id);

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Data gaji berhasil dihapus."
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Metode HTTP tidak diizinkan."]);
        break;
}
?>
