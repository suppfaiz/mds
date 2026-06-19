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
        // Fetch single student or list
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            try {
                $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
                $stmt->execute([$id]);
                $student = $stmt->fetch();
                
                if ($student) {
                    // Fetch grades
                    $nilai_stmt = $pdo->prepare("SELECT id, mata_pelajaran, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, semester, tahun_ajaran, keterangan FROM nilai WHERE siswa_id = ? ORDER BY tahun_ajaran DESC, semester DESC, mata_pelajaran ASC");
                    $nilai_stmt->execute([$id]);
                    $student['grades'] = $nilai_stmt->fetchAll();

                    // Fetch documents
                    $doc_stmt = $pdo->prepare("SELECT id, kategori, nama_file, lokasi_file, tanggal_upload FROM dokumen WHERE tipe_data = 'siswa' AND data_id = ? ORDER BY tanggal_upload DESC");
                    $doc_stmt->execute([$id]);
                    $student['documents'] = $doc_stmt->fetchAll();

                    http_response_code(200);
                    echo json_encode([
                        "status" => "success",
                        "data" => $student
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Siswa tidak ditemukan."
                    ]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            // Fetch all students
            $search = $_GET['search'] ?? '';
            $kelas_id = $_GET['kelas_id'] ?? '';
            
            $query = "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $query .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
                $search_param = "%$search%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            if (!empty($kelas_id)) {
                $query .= " AND s.kelas_id = ?";
                $params[] = (int)$kelas_id;
            }
            
            $query .= " ORDER BY s.id DESC";
            
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $students = $stmt->fetchAll();
                
                http_response_code(200);
                echo json_encode([
                    "status" => "success",
                    "count" => count($students),
                    "data" => $students
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;

    case 'POST':
        // Create student
        $input = json_decode(file_get_contents("php://input"), true);
        
        // Fallback to $_POST if JSON payload is empty
        if (empty($input)) {
            $input = $_POST;
        }

        $nis = $input['nis'] ?? '';
        $nisn = $input['nisn'] ?? '';
        $nama = $input['nama'] ?? '';
        $jenis_kelamin = $input['jenis_kelamin'] ?? '';
        $tempat_lahir = $input['tempat_lahir'] ?? '';
        $tanggal_lahir = $input['tanggal_lahir'] ?? '';
        $alamat = $input['alamat'] ?? '';
        $agama = $input['agama'] ?? '';
        $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : null;
        $tahun_masuk = isset($input['tahun_masuk']) ? (int)$input['tahun_masuk'] : date('Y');
        $no_hp = $input['no_hp'] ?? '';
        $email = $input['email'] ?? '';
        $nama_ayah = $input['nama_ayah'] ?? '';
        $nik_ayah = $input['nik_ayah'] ?? '';
        $pekerjaan_ayah = $input['pekerjaan_ayah'] ?? '';
        $nama_ibu = $input['nama_ibu'] ?? '';
        $nik_ibu = $input['nik_ibu'] ?? '';
        $pekerjaan_ibu = $input['pekerjaan_ibu'] ?? '';
        $no_hp_ortu = $input['no_hp_ortu'] ?? '';
        $alamat_ortu = $input['alamat_ortu'] ?? '';

        if (empty($nis) || empty($nisn) || empty($nama) || empty($jenis_kelamin) || empty($tempat_lahir) || empty($tanggal_lahir) || empty($alamat) || empty($agama) || empty($no_hp)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Gagal menyimpan. Isian wajib (nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, agama, no_hp) tidak boleh kosong."
            ]);
            exit();
        }

        try {
            // Check uniqueness
            $check = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ? OR nisn = ?");
            $check->execute([$nis, $nisn]);
            if ($check->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    "status" => "error",
                    "message" => "Gagal menyimpan. NIS atau NISN sudah terdaftar."
                ]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO siswa 
                (nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, agama, kelas_id, tahun_masuk, no_hp, email, nama_ayah, nik_ayah, pekerjaan_ayah, nama_ibu, nik_ibu, pekerjaan_ibu, no_hp_ortu, alamat_ortu) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $nis, $nisn, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir,
                $alamat, $agama, $kelas_id, $tahun_masuk, $no_hp, $email, $nama_ayah, $nik_ayah, $pekerjaan_ayah, $nama_ibu, $nik_ibu, $pekerjaan_ibu, $no_hp_ortu, $alamat_ortu
            ]);
            
            $new_id = $pdo->lastInsertId();
            logActivity($pdo, 'API: Tambah Siswa', 'Menambahkan siswa via API: ' . $nama . ' (ID: ' . $new_id . ')');

            http_response_code(210); // Created
            echo json_encode([
                "status" => "success",
                "message" => "Data siswa berhasil ditambahkan.",
                "id" => $new_id
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update student
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID Siswa tidak valid."]);
            exit();
        }

        try {
            // Verify student exists
            $check = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
            $check->execute([$id]);
            $student = $check->fetch();

            if (!$student) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Siswa tidak ditemukan."]);
                exit();
            }

            // Fill parameters, keeping existing values if not provided
            $nis = $input['nis'] ?? $student['nis'];
            $nisn = $input['nisn'] ?? $student['nisn'];
            $nama = $input['nama'] ?? $student['nama'];
            $jenis_kelamin = $input['jenis_kelamin'] ?? $student['jenis_kelamin'];
            $tempat_lahir = $input['tempat_lahir'] ?? $student['tempat_lahir'];
            $tanggal_lahir = $input['tanggal_lahir'] ?? $student['tanggal_lahir'];
            $alamat = $input['alamat'] ?? $student['alamat'];
            $agama = $input['agama'] ?? $student['agama'];
            $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : $student['kelas_id'];
            $tahun_masuk = isset($input['tahun_masuk']) ? (int)$input['tahun_masuk'] : $student['tahun_masuk'];
            $no_hp = $input['no_hp'] ?? $student['no_hp'];
            $email = $input['email'] ?? $student['email'];
            $nama_ayah = $input['nama_ayah'] ?? $student['nama_ayah'];
            $nik_ayah = $input['nik_ayah'] ?? $student['nik_ayah'];
            $pekerjaan_ayah = $input['pekerjaan_ayah'] ?? $student['pekerjaan_ayah'];
            $nama_ibu = $input['nama_ibu'] ?? $student['nama_ibu'];
            $nik_ibu = $input['nik_ibu'] ?? $student['nik_ibu'];
            $pekerjaan_ibu = $input['pekerjaan_ibu'] ?? $student['pekerjaan_ibu'];
            $no_hp_ortu = $input['no_hp_ortu'] ?? $student['no_hp_ortu'];
            $alamat_ortu = $input['alamat_ortu'] ?? $student['alamat_ortu'];

            // Check uniqueness
            $check_uniq = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE (nis = ? OR nisn = ?) AND id != ?");
            $check_uniq->execute([$nis, $nisn, $id]);
            if ($check_uniq->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "NIS atau NISN sudah digunakan oleh siswa lain."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE siswa SET 
                nis = ?, nisn = ?, nama = ?, jenis_kelamin = ?, tempat_lahir = ?, 
                tanggal_lahir = ?, alamat = ?, agama = ?, kelas_id = ?, tahun_masuk = ?, 
                no_hp = ?, email = ?, nama_ayah = ?, nik_ayah = ?, pekerjaan_ayah = ?, nama_ibu = ?, nik_ibu = ?, pekerjaan_ibu = ?, no_hp_ortu = ?, alamat_ortu = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $nis, $nisn, $nama, $jenis_kelamin, $tempat_lahir,
                $tanggal_lahir, $alamat, $agama, $kelas_id, $tahun_masuk,
                $no_hp, $email, $nama_ayah, $nik_ayah, $pekerjaan_ayah, $nama_ibu, $nik_ibu, $pekerjaan_ibu, $no_hp_ortu, $alamat_ortu, $id
            ]);

            logActivity($pdo, 'API: Edit Siswa', 'Mengupdate siswa via API: ' . $nama . ' (ID: ' . $id . ')');
            
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Data siswa berhasil diupdate."
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete student
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['id'] ?? (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID Siswa tidak valid."]);
            exit();
        }

        try {
            // Verify student exists
            $stmt = $pdo->prepare("SELECT nama, foto FROM siswa WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch();

            if (!$student) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Siswa tidak ditemukan."]);
                exit();
            }

            // Unlink profile photo
            if (!empty($student['foto'])) {
                $photo_path = '../' . $student['foto'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }

            // Unlink and delete associated documents
            $doc_stmt = $pdo->prepare("SELECT lokasi_file FROM dokumen WHERE tipe_data = 'siswa' AND data_id = ?");
            $doc_stmt->execute([$id]);
            $documents = $doc_stmt->fetchAll();
            
            foreach ($documents as $doc) {
                $file_path = '../' . $doc['lokasi_file'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $del_docs = $pdo->prepare("DELETE FROM dokumen WHERE tipe_data = 'siswa' AND data_id = ?");
            $del_docs->execute([$id]);

            // Delete record
            $del_siswa = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
            $del_siswa->execute([$id]);

            logActivity($pdo, 'API: Hapus Siswa', 'Menghapus siswa via API: ' . $student['nama'] . ' (ID: ' . $id . ')');

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Data siswa berhasil dihapus."
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
