<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle OPTIONS
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
        // Fetch single teacher or list
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            try {
                $stmt = $pdo->prepare("SELECT * FROM guru WHERE id = ?");
                $stmt->execute([$id]);
                $teacher = $stmt->fetch();
                
                if ($teacher) {
                    // Fetch documents
                    $doc_stmt = $pdo->prepare("SELECT id, kategori, nama_file, lokasi_file, tanggal_upload FROM dokumen WHERE tipe_data = 'guru' AND data_id = ? ORDER BY tanggal_upload DESC");
                    $doc_stmt->execute([$id]);
                    $teacher['documents'] = $doc_stmt->fetchAll();

                    http_response_code(200);
                    echo json_encode([
                        "status" => "success",
                        "data" => $teacher
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Guru tidak ditemukan."
                    ]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // Fetch all teachers
            $search = $_GET['search'] ?? '';
            
            $query = "SELECT * FROM guru WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $query .= " AND (nama LIKE ? OR nip LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " ORDER BY id DESC";
            
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $teachers = $stmt->fetchAll();
                
                http_response_code(200);
                echo json_encode([
                    "status" => "success",
                    "count" => count($teachers),
                    "data" => $teachers
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;

    case 'POST':
        // Create teacher record
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (empty($input)) {
            $input = $_POST;
        }

        $nip = trim($input['nip'] ?? '');
        $nama = trim($input['nama'] ?? '');
        $mata_pelajaran = trim($input['mata_pelajaran'] ?? '');
        $jabatan = trim($input['jabatan'] ?? '');
        $pendidikan_terakhir = trim($input['pendidikan_terakhir'] ?? '');
        $no_hp = trim($input['no_hp'] ?? '');
        $email = trim($input['email'] ?? '');
        $alamat = trim($input['alamat'] ?? '');
        
        $nip_db = ($nip !== '') ? $nip : null;

        if (empty($nama) || empty($mata_pelajaran) || empty($jabatan) || empty($pendidikan_terakhir) || empty($no_hp) || empty($alamat)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Gagal menyimpan. Isian wajib (nama, mata_pelajaran, jabatan, pendidikan_terakhir, no_hp, alamat) tidak boleh kosong."
            ]);
            exit();
        }

        try {
            // Check NIP uniqueness if provided
            if ($nip_db !== null) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ?");
                $check->execute([$nip_db]);
                if ($check->fetchColumn() > 0) {
                    http_response_code(409);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Gagal menyimpan. NIP sudah terdaftar."
                    ]);
                    exit();
                }
            }

            $stmt = $pdo->prepare("INSERT INTO guru 
                (nip, nama, mata_pelajaran, jabatan, pendidikan_terakhir, no_hp, email, alamat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $nip_db, $nama, $mata_pelajaran, $jabatan, $pendidikan_terakhir, $no_hp, $email, $alamat
            ]);
            
            $new_id = $pdo->lastInsertId();
            logActivity($pdo, 'API: Tambah Guru', 'Menambahkan guru via API: ' . $nama . ' (ID: ' . $new_id . ')');

            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Data guru berhasil ditambahkan.",
                "id" => $new_id
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update teacher record
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID Guru tidak valid."]);
            exit();
        }

        try {
            // Verify teacher exists
            $check = $pdo->prepare("SELECT * FROM guru WHERE id = ?");
            $check->execute([$id]);
            $teacher = $check->fetch();

            if (!$teacher) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Guru tidak ditemukan."]);
                exit();
            }

            // Fill parameters, keeping existing values if not provided
            $nip = trim($input['nip'] ?? ($teacher['nip'] ?? ''));
            $nama = trim($input['nama'] ?? $teacher['nama']);
            $mata_pelajaran = trim($input['mata_pelajaran'] ?? $teacher['mata_pelajaran']);
            $jabatan = trim($input['jabatan'] ?? $teacher['jabatan']);
            $pendidikan_terakhir = trim($input['pendidikan_terakhir'] ?? $teacher['pendidikan_terakhir']);
            $no_hp = trim($input['no_hp'] ?? $teacher['no_hp']);
            $email = trim($input['email'] ?? $teacher['email']);
            $alamat = trim($input['alamat'] ?? $teacher['alamat']);
            
            $nip_db = ($nip !== '') ? $nip : null;

            // Check NIP uniqueness if provided
            if ($nip_db !== null) {
                $check_uniq = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ? AND id != ?");
                $check_uniq->execute([$nip_db, $id]);
                if ($check_uniq->fetchColumn() > 0) {
                    http_response_code(409);
                    echo json_encode(["status" => "error", "message" => "NIP sudah digunakan oleh guru lain."]);
                    exit();
                }
            }

            $stmt = $pdo->prepare("UPDATE guru SET 
                nip = ?, nama = ?, mata_pelajaran = ?, jabatan = ?, 
                pendidikan_terakhir = ?, no_hp = ?, email = ?, alamat = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $nip_db, $nama, $mata_pelajaran, $jabatan, 
                $pendidikan_terakhir, $no_hp, $email, $alamat, $id
            ]);

            logActivity($pdo, 'API: Edit Guru', 'Mengupdate guru via API: ' . $nama . ' (ID: ' . $id . ')');
            
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Data guru berhasil diupdate."
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete teacher record
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID Guru tidak valid."]);
            exit();
        }

        try {
            // Verify teacher exists
            $stmt = $pdo->prepare("SELECT nama, foto FROM guru WHERE id = ?");
            $stmt->execute([$id]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Guru tidak ditemukan."]);
                exit();
            }

            $pdo->beginTransaction();

            // Unlink photo
            if (!empty($teacher['foto'])) {
                $photo_path = '../' . $teacher['foto'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }

            // Unlink and delete associated documents
            $doc_stmt = $pdo->prepare("SELECT lokasi_file FROM dokumen WHERE tipe_data = 'guru' AND data_id = ?");
            $doc_stmt->execute([$id]);
            $documents = $doc_stmt->fetchAll();
            
            foreach ($documents as $doc) {
                $file_path = '../' . $doc['lokasi_file'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $del_docs = $pdo->prepare("DELETE FROM dokumen WHERE tipe_data = 'guru' AND data_id = ?");
            $del_docs->execute([$id]);

            // Delete payroll records associated with this teacher
            $del_payroll = $pdo->prepare("DELETE FROM payroll WHERE tipe_penerima = 'guru' AND penerima_id = ?");
            $del_payroll->execute([$id]);

            // Delete attendance records associated with this teacher
            $del_presensi = $pdo->prepare("DELETE FROM presensi_pegawai WHERE tipe_penerima = 'guru' AND penerima_id = ?");
            $del_presensi->execute([$id]);

            // Delete record
            $del_guru = $pdo->prepare("DELETE FROM guru WHERE id = ?");
            $del_guru->execute([$id]);

            $pdo->commit();

            logActivity($pdo, 'API: Hapus Guru', 'Menghapus guru via API: ' . $teacher['nama'] . ' (ID: ' . $id . ')');

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Data guru berhasil dihapus."
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
