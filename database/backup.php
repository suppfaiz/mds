<?php
$path_prefix = '../';
$page_title = 'Backup & Restore Database';
$active_menu = 'backup';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page - Only Super Admin can access
checkRole(['super_admin']);

// 1. BACKUP HANDLER (Triggered by GET ?action=download)
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    try {
        $tables = [
            'users',
            'kelas',
            'siswa',
            'guru',
            'karyawan',
            'dokumen',
            'audit_log',
            'nilai',
            'payroll',
            'pengaturan',
            'spp_pembayaran',
            'presensi_siswa',
            'presensi_pegawai',
            'wali_kelas',
            'rapor_catatan',
            'keuangan_transaksi',
            'pmb_akun',
            'pmb_pendaftar'
        ];
        $sql_dump = "-- Master Data Sekolah - Database Auto Backup\n";
        $sql_dump .= "-- Tanggal: " . date('d-M-Y H:i:s') . "\n";
        $sql_dump .= "-- Host: " . $host . "\n\n";
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch();
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_dump .= $row['Create Table'] . ";\n\n";

            // Get INSERT INTO statements
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll();
            
            if (count($rows) > 0) {
                $sql_dump .= "-- Dump data table `$table`\n";
                foreach ($rows as $data) {
                    $keys = array_keys($data);
                    $escaped_values = [];
                    foreach ($data as $val) {
                        if ($val === null) {
                            $escaped_values[] = 'NULL';
                        } else {
                            $escaped_values[] = $pdo->quote($val);
                        }
                    }
                    $sql_dump .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escaped_values) . ");\n";
                }
                $sql_dump .= "\n";
            }
        }

        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Record log
        logActivity($pdo, 'Backup Database', 'User melakukan ekspor database SQL');

        // Set download headers
        $filename = 'backup_db_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Length: ' . strlen($sql_dump));
        echo $sql_dump;
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal membackup database: ' . $e->getMessage();
        header("Location: backup.php");
        exit();
    }
}

// 2. RESTORE HANDLER (Triggered by POST)
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        if (isset($_FILES['backup_file'])) {
            if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['backup_file']['tmp_name'];
                $file_name = $_FILES['backup_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if ($file_ext !== 'sql') {
                    $error = 'Format berkas tidak valid. Harap unggah berkas berekstensi .sql';
                } else {
                    try {
                        $sql_content = file_get_contents($file_tmp);
                        
                        // Disable foreign keys temporarily
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
                        
                        // Basic cleanup & parsing of statements
                        // Remove comment lines
                        $clean_sql = preg_replace('/--.*\n/', '', $sql_content);
                        $clean_sql = preg_replace('/\/\*.*?\*\//s', '', $clean_sql);
                        
                        // Explode statements by semicolon
                        $statements = explode(';', $clean_sql);
                        $executed = 0;

                        $pdo->beginTransaction();
                        foreach ($statements as $stmt_text) {
                            $stmt_text = trim($stmt_text);
                            if (!empty($stmt_text)) {
                                $pdo->exec($stmt_text);
                                $executed++;
                            }
                        }
                        
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
                        $pdo->commit();

                        logActivity($pdo, 'Restore Database', 'User melakukan restore database dari file: ' . $file_name);
                        
                        $success = 'Database berhasil dipulihkan. Total ' . $executed . ' query berhasil dijalankan.';
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
                        $error = 'Gagal memulihkan database: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Terjadi kesalahan saat mengunggah berkas backup.';
            }
        }
    }
}

include $path_prefix . 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8">
        <h4 class="fw-bold mb-1">Perawatan Database & Sistem</h4>
        <p class="text-muted mb-4 small">Lakukan pencadangan berkala data Anda atau pulihkan sistem dari backup SQL sebelumnya.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Backup Box -->
            <div class="col-12 col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 d-inline-block mb-3">
                                <i class="bi bi-database-down fs-3"></i>
                            </div>
                            <h5 class="fw-bold text-dark-emphasis mb-2">Backup Database (SQL)</h5>
                            <p class="text-muted small mb-4">Mendownload salinan database saat ini dalam bentuk file SQL. Ini mencakup skema tabel siswa, guru, kelas, user, audit log, dan relasi data dokumen.</p>
                        </div>
                        <a href="?action=download" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-download me-2"></i> Unduh File Backup
                        </a>
                    </div>
                </div>
            </div>

            <!-- Restore Box -->
            <div class="col-12 col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 d-inline-block mb-3">
                            <i class="bi bi-database-up fs-3"></i>
                        </div>
                        <h5 class="fw-bold text-dark-emphasis mb-2">Restore Database</h5>
                        <p class="text-muted small mb-3">Unggah file cadangan berekstensi `.sql` untuk memulihkan seluruh struktur database dan records.</p>
                        
                        <form action="" method="POST" enctype="multipart/form-data" onsubmit="return confirm('PERINGATAN: Memulihkan database akan menimpa seluruh tabel yang ada saat ini. Apakah Anda yakin?')">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <input class="form-control form-control-sm" type="file" id="backup_file" name="backup_file" accept=".sql" required>
                                <div class="form-text" style="font-size: 11px;">Unggah file .sql hasil download dari sistem ini.</div>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark">
                                <i class="bi bi-arrow-clockwise me-1"></i> Jalankan Restorasi
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Danger zone warning -->
        <div class="card border-danger border-opacity-25 bg-danger bg-opacity-10 mt-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-octagon-fill text-danger fs-3"></i>
                    <div>
                        <h6 class="fw-bold text-danger-emphasis mb-1">Peringatan Keamanan Database</h6>
                        <p class="text-danger-emphasis small m-0">Restorasi database bersifat merusak dan akan menghapus seluruh data yang bertabrakan. Harap amankan file backup Anda dan hindari membukanya pada media publik.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
