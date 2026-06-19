<?php
$path_prefix = '../';
$page_title = 'Import Data Siswa';
$active_menu = 'siswa';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';
$skipped_rows = [];
$success_count = 0;

// Fetch active classes for lookup list
try {
    $stmt = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $classes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $file_name = $_FILES['file_csv']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($file_ext !== 'csv') {
            $error = 'Format file tidak valid. Silakan unggah file dengan format .csv';
        } else {
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                // Read first line to auto-detect delimiter (, or ;)
                $first_line = fgets($handle);
                $delimiter = ',';
                if (strpos($first_line, ';') !== false) {
                    $delimiter = ';';
                }
                
                // Rewind file pointer to beginning
                rewind($handle);
                
                // Skip header row
                $headers = fgetcsv($handle, 2000, $delimiter);
                
                try {
                    $pdo->beginTransaction();
                    
                    $row_num = 1; // logical row tracker (excluding header)
                    
                    // Prepare insertion statement
                    $insert_stmt = $pdo->prepare("INSERT INTO siswa 
                        (nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, agama, kelas_id, tahun_masuk, no_hp, email, nama_ayah, nik_ayah, pekerjaan_ayah, nama_ibu, nik_ibu, pekerjaan_ibu, no_hp_ortu, alamat_ortu) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    // Keep track of NIS/NISN processed in this batch to avoid duplicates within the same CSV upload
                    $batch_nis = [];
                    $batch_nisn = [];

                    while (($row = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
                        $row_num++;
                        
                        // Skip completely empty rows
                        if (empty($row) || count($row) < 3 || empty(trim($row[0]))) {
                            continue;
                        }
                        
                        // Pad row columns if row is shorter than headers count
                        while (count($row) < 20) {
                            $row[] = '';
                        }
                        
                        // Trim cell values
                        $nis = trim($row[0]);
                        $nisn = trim($row[1]);
                        $nama = trim($row[2]);
                        $jenis_kelamin = strtoupper(trim($row[3]));
                        $tempat_lahir = trim($row[4]);
                        $tanggal_lahir = trim($row[5]);
                        $alamat = trim($row[6]);
                        $agama = trim($row[7]);
                        $kelas_id = trim($row[8]) !== '' ? (int)$row[8] : null;
                        $tahun_masuk = trim($row[9]) !== '' ? (int)$row[9] : (int)date('Y');
                        $no_hp = trim($row[10]);
                        $email = trim($row[11]);
                        
                        // Parent fields
                        $nama_ayah = trim($row[12]);
                        $nik_ayah = trim($row[13]) !== '' ? trim($row[13]) : null;
                        $pekerjaan_ayah = trim($row[14]) !== '' ? trim($row[14]) : null;
                        $nama_ibu = trim($row[15]);
                        $nik_ibu = trim($row[16]) !== '' ? trim($row[16]) : null;
                        $pekerjaan_ibu = trim($row[17]) !== '' ? trim($row[17]) : null;
                        $no_hp_ortu = trim($row[18]) !== '' ? trim($row[18]) : null;
                        $alamat_ortu = trim($row[19]) !== '' ? trim($row[19]) : null;

                        // Validation checks
                        if (empty($nis) || empty($nisn) || empty($nama) || empty($jenis_kelamin) || empty($tempat_lahir) || empty($tanggal_lahir) || empty($alamat) || empty($agama) || empty($no_hp)) {
                            $skipped_rows[] = "Baris $row_num (Nama: " . ($nama ?: 'Tanpa Nama') . "): Kolom wajib (nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, agama, no_hp) tidak lengkap.";
                            continue;
                        }
                        
                        if (!in_array($jenis_kelamin, ['L', 'P'])) {
                            $skipped_rows[] = "Baris $row_num (Nama: $nama): Jenis Kelamin harus 'L' atau 'P'.";
                            continue;
                        }
                        
                        // Validate date format YYYY-MM-DD
                        if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $tanggal_lahir)) {
                            $skipped_rows[] = "Baris $row_num (Nama: $nama): Format tanggal lahir tidak valid (harus YYYY-MM-DD).";
                            continue;
                        }

                        // Check duplicate in current batch
                        if (in_array($nis, $batch_nis) || in_array($nisn, $batch_nisn)) {
                            $skipped_rows[] = "Baris $row_num (Nama: $nama): NIS ($nis) atau NISN ($nisn) duplikat di dalam file CSV ini.";
                            continue;
                        }

                        // Check duplicate in Database
                        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ? OR nisn = ?");
                        $check_stmt->execute([$nis, $nisn]);
                        if ($check_stmt->fetchColumn() > 0) {
                            $skipped_rows[] = "Baris $row_num (Nama: $nama): NIS ($nis) atau NISN ($nisn) sudah terdaftar di sistem.";
                            continue;
                        }
                        
                        // Verify class ID exists
                        if ($kelas_id !== null) {
                            $check_kelas = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE id = ?");
                            $check_kelas->execute([$kelas_id]);
                            if ($check_kelas->fetchColumn() == 0) {
                                $skipped_rows[] = "Baris $row_num (Nama: $nama): ID Kelas ($kelas_id) tidak ditemukan di database.";
                                continue;
                            }
                        }

                        // Record valid NIS/NISN to batch arrays
                        $batch_nis[] = $nis;
                        $batch_nisn[] = $nisn;

                        // Insert student record
                        $insert_stmt->execute([
                            $nis, $nisn, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir,
                            $alamat, $agama, $kelas_id, $tahun_masuk, $no_hp, $email,
                            $nama_ayah, $nik_ayah, $pekerjaan_ayah, $nama_ibu, $nik_ibu, $pekerjaan_ibu, $no_hp_ortu, $alamat_ortu
                        ]);
                        $success_count++;
                    }
                    
                    $pdo->commit();
                    
                    if ($success_count > 0) {
                        logActivity($pdo, 'Import Siswa', 'Mengimpor ' . $success_count . ' data siswa via CSV');
                        $success = "Berhasil mengimpor $success_count data siswa.";
                    } else {
                        $error = "Tidak ada data siswa baru yang diimpor.";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Kesalahan database: ' . $e->getMessage();
                }
                
                fclose($handle);
            } else {
                $error = 'Gagal membuka file CSV.';
            }
        }
    } else {
        $error = 'Terjadi kesalahan unggah file atau tidak ada file dipilih.';
    }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <h4 class="fw-bold mb-0">Import Data Siswa</h4>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($skipped_rows)): ?>
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning-subtle text-warning-emphasis fw-bold py-2">
            <i class="bi bi-exclamation-circle-fill me-1"></i> Data yang dilewati (Skipped Rows) - <?php echo count($skipped_rows); ?> baris
        </div>
        <div class="card-body p-3 scrollable-div" style="max-height: 200px; overflow-y: auto;">
            <ul class="mb-0 text-danger font-monospace small">
                <?php foreach ($skipped_rows as $skip): ?>
                    <li><?php echo htmlspecialchars($skip); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Import Column -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Unggah File Excel (CSV)</h5>
            </div>
            <div class="card-body p-4">
                <!-- Instructions -->
                <h6 class="fw-bold text-dark-emphasis mb-2 small">Petunjuk Impor Data:</h6>
                <ol class="small text-muted mb-4">
                    <li>Unduh template file CSV resmi melalui tombol di samping kanan.</li>
                    <li>Buka template menggunakan Microsoft Excel, Google Sheets, atau aplikasi sejenis.</li>
                    <li>Isi data siswa sesuai format contoh pada baris kedua template.</li>
                    <li><strong>Kolom Wajib Diisi:</strong> <code>nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, agama, no_hp</code>.</li>
                    <li><strong>Kolom Jenis Kelamin:</strong> Hanya diisi huruf <code>L</code> untuk Laki-laki atau <code>P</code> untuk Perempuan.</li>
                    <li><strong>Format Tanggal Lahir:</strong> Harus berformat <code>YYYY-MM-DD</code> (contoh: <code>2010-08-15</code>).</li>
                    <li>Jika sudah selesai mengisi, simpan sebagai file <strong>CSV (Comma Delimited)</strong>.</li>
                    <li>Unggah berkas CSV tersebut menggunakan form di bawah ini.</li>
                </ol>

                <hr class="my-4">

                <!-- Upload Form -->
                <form action="" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="file_csv" class="form-label fw-semibold small">Pilih File CSV (*.csv)</label>
                        <input class="form-control" type="file" id="file_csv" name="file_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-upload"></i> Proses Import Data</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Lookup Table Column -->
    <div class="col-12 col-lg-4">
        <!-- Template Download Card -->
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body p-4 text-center">
                <i class="bi bi-file-earmark-spreadsheet text-success fs-1 mb-2"></i>
                <h6 class="fw-bold mb-1">Unduh Template CSV</h6>
                <p class="text-muted small mb-3">Gunakan template resmi agar struktur kolom sesuai.</p>
                <a href="import_template.php" class="btn btn-success btn-sm w-100 fw-bold"><i class="bi bi-download"></i> Unduh Template</a>
            </div>
        </div>

        <!-- Class ID Lookup List Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-key me-1 text-primary"></i> Daftar ID Kelas (Lookup)</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-2">Gunakan angka ID berikut pada kolom <code>kelas_id</code> di template:</p>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th>ID Kelas</th>
                                <th>Nama Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">Belum ada kelas.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td class="fw-bold text-primary font-monospace"><?php echo $class['id']; ?></td>
                                        <td><?php echo htmlspecialchars($class['nama_kelas']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
