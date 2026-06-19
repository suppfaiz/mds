<?php
$path_prefix = '../';
$page_title = 'Detail Profil Karyawan';
$active_menu = 'karyawan';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

checkLogin();

$employee = null;
$documents = [];

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Fetch employee details
        $stmt = $pdo->prepare("SELECT * FROM karyawan WHERE id = ?");
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        
        if (!$employee) {
            $_SESSION['error_message'] = 'Karyawan tidak ditemukan.';
            header("Location: index.php");
            exit();
        }

        // Fetch employee documents
        $doc_stmt = $pdo->prepare("SELECT * FROM dokumen WHERE tipe_data = 'karyawan' AND data_id = ? ORDER BY tanggal_upload DESC");
        $doc_stmt->execute([$id]);
        $documents = $doc_stmt->fetchAll();

        // Fetch employee attendance summary
        $att_summary_stmt = $pdo->prepare("
            SELECT status, COUNT(*) as jumlah 
            FROM presensi_pegawai 
            WHERE tipe_penerima = 'karyawan' AND penerima_id = ? 
            GROUP BY status
        ");
        $att_summary_stmt->execute([$id]);
        $att_summary_raw = $att_summary_stmt->fetchAll();
        $att_summary = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
        $total_att_days = 0;
        foreach ($att_summary_raw as $raw) {
            $att_summary[$raw['status']] = (int)$raw['jumlah'];
            $total_att_days += (int)$raw['jumlah'];
        }

        // Fetch recent employee attendance logs
        $att_logs_stmt = $pdo->prepare("
            SELECT tanggal, status, keterangan 
            FROM presensi_pegawai 
            WHERE tipe_penerima = 'karyawan' AND penerima_id = ? 
            ORDER BY tanggal DESC 
            LIMIT 30
        ");
        $att_logs_stmt->execute([$id]);
        $att_logs = $att_logs_stmt->fetchAll();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Gagal memuat profil: ' . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = 'ID Karyawan tidak valid.';
    header("Location: index.php");
    exit();
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between gap-2 mb-4 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Profil Karyawan</h4>
    </div>
    
    <div class="d-flex gap-2">
        <?php if (hasPermission(['super_admin', 'operator'])): ?>
            <a href="edit.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-pencil-square"></i> Edit Profil
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Profil Info Card -->
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-4 pb-4 border-bottom">
                    <div class="profile-img-container bg-light d-flex align-items-center justify-content-center border" style="width: 140px; height: 140px; margin: 0;">
                        <?php if (!empty($employee['foto']) && file_exists('../' . $employee['foto'])): ?>
                            <img src="../<?php echo htmlspecialchars($employee['foto']); ?>" alt="Foto" class="profile-img">
                        <?php else: ?>
                            <i class="bi bi-person text-secondary" style="font-size: 4.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center text-sm-start">
                        <span class="badge bg-info mb-2"><?php echo htmlspecialchars($employee['jabatan']); ?></span>
                        <h4 class="fw-bold mb-1 text-dark-emphasis"><?php echo htmlspecialchars($employee['nama']); ?></h4>
                        <p class="text-muted small mb-3">NIK: <?php echo htmlspecialchars($employee['nik']); ?></p>
                        <div class="d-flex flex-wrap justify-content-center justify-content-sm-start gap-2">
                            <span class="badge bg-light text-dark border"><i class="bi bi-calendar-check text-primary me-1"></i> Terdaftar: <?php echo date('d/m/Y', strtotime($employee['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold my-4 text-dark-emphasis">Informasi Detail Kepegawaian</h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Nomor Handphone / WhatsApp</span>
                        <span class="fw-semibold"><?php echo htmlspecialchars($employee['no_hp']); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Alamat Email</span>
                        <span class="fw-semibold"><?php echo !empty($employee['email']) ? htmlspecialchars($employee['email']) : '-'; ?></span>
                    </div>
                    
                    <div class="col-12">
                        <span class="text-muted small d-block">Alamat Lengkap Rumah</span>
                        <span class="fw-semibold d-block mt-1 p-2 bg-light rounded text-dark-emphasis" style="font-size: 14px; min-height: 70px;">
                            <?php echo nl2br(htmlspecialchars($employee['alamat'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dokumen Locker Card -->
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-folder2-open text-primary me-2"></i> Dokumen Digital Karyawan</h5>
            </div>
            
            <div class="card-body p-4">
                <?php if (hasPermission(['super_admin', 'operator'])): ?>
                    <!-- Form Upload Dokumen (Only Admins, Operators can upload employee docs) -->
                    <form action="../dokumen/upload.php" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded mb-4">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="tipe_data" value="karyawan">
                        <input type="hidden" name="data_id" value="<?php echo $employee['id']; ?>">
                        
                        <h6 class="fw-bold text-dark-emphasis mb-3 small"><i class="bi bi-cloud-arrow-up"></i> Upload Dokumen Baru</h6>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label small fw-semibold">Kategori Dokumen</label>
                            <select class="form-select form-select-sm" id="kategori" name="kategori" required>
                                <option value="" disabled selected>Pilih Kategori...</option>
                                <option value="KTP">KTP</option>
                                <option value="NPWP">NPWP</option>
                                <option value="Ijazah">Ijazah</option>
                                <option value="Kontrak Kerja">Kontrak Kerja</option>
                                <option value="Sertifikat">Sertifikat</option>
                                <option value="Dokumen Lain">Dokumen Lain</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="file_dokumen" class="form-label small fw-semibold">Pilih File (PDF, JPG, PNG)</label>
                            <input class="form-control form-control-sm" type="file" id="file_dokumen" name="file_dokumen" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text" style="font-size: 10px;">Format: PDF, JPG, PNG. Ukuran maks: 5MB.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                            <i class="bi bi-upload"></i> Unggah Dokumen
                        </button>
                    </form>
                <?php endif; ?>

                <!-- List Dokumen -->
                <div class="d-flex flex-column gap-3">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-4 text-muted border border-dashed rounded bg-light bg-opacity-50">
                            <i class="bi bi-file-earmark-lock2 fs-2 text-secondary"></i>
                            <p class="mt-2 small mb-0">Belum ada dokumen digital diunggah.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): 
                            $ext = strtolower(pathinfo($doc['nama_file'], PATHINFO_EXTENSION));
                            $icon_class = 'bi-file-earmark-text text-secondary';
                            if ($ext === 'pdf') $icon_class = 'bi-file-earmark-pdf-fill text-danger';
                            elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) $icon_class = 'bi-file-earmark-image-fill text-success';
                        ?>
                            <div class="d-flex align-items-center justify-content-between p-3 border rounded">
                                <div class="d-flex align-items-center gap-3 text-truncate">
                                    <i class="bi <?php echo $icon_class; ?> fs-3"></i>
                                    <div class="text-truncate">
                                        <h6 class="mb-1 fw-bold text-dark-emphasis small text-truncate" title="<?php echo htmlspecialchars($doc['kategori']); ?>">
                                            <?php echo htmlspecialchars($doc['kategori']); ?>
                                        </h6>
                                        <small class="text-muted text-truncate d-block" style="font-size: 11px;">
                                            <?php echo htmlspecialchars($doc['nama_file']); ?>
                                        </small>
                                        <small class="text-muted" style="font-size: 10px;">
                                            Diunggah: <?php echo date('d/m/Y H:i', strtotime($doc['tanggal_upload'])); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-1 ms-2">
                                    <a href="../dokumen/view.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn btn-sm btn-light border" title="Preview Dokumen">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="../dokumen/view.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-light border text-primary" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <?php if (hasPermission(['super_admin', 'operator'])): ?>
                                        <a href="../dokumen/delete.php?id=<?php echo $doc['id']; ?>&karyawan_id=<?php echo $employee['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus file dokumen digital ini?')" title="Hapus Dokumen">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kehadiran Section Card -->
<div class="row mb-4" id="kehadiran_history">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-calendar2-range text-warning me-2"></i> Rekapitulasi Kehadiran Karyawan</h5>
                <?php if (hasPermission(['super_admin', 'operator'])): ?>
                    <a href="../presensi/pegawai.php" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square"></i> Kelola Presensi Pegawai
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4">
                <!-- Summary Widgets -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 bg-success-subtle text-success-emphasis text-center">
                            <span class="d-block small text-muted">Hadir</span>
                            <h3 class="fw-bold m-0"><?php echo $att_summary['Hadir']; ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="border rounded p-3 bg-primary-subtle text-primary-emphasis text-center">
                            <span class="d-block small text-muted">Sakit</span>
                            <h3 class="fw-bold m-0"><?php echo $att_summary['Sakit']; ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="border rounded p-3 bg-warning-subtle text-warning-emphasis text-center">
                            <span class="d-block small text-muted">Izin</span>
                            <h3 class="fw-bold m-0"><?php echo $att_summary['Izin']; ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="border rounded p-3 bg-danger-subtle text-danger-emphasis text-center">
                            <span class="d-block small text-muted">Alpa</span>
                            <h3 class="fw-bold m-0"><?php echo $att_summary['Alpa']; ?></h3>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <?php 
                        $rate = $total_att_days > 0 ? round(($att_summary['Hadir'] / $total_att_days) * 100) : 100;
                        ?>
                        <div class="border rounded p-3 bg-dark text-white text-center">
                            <span class="d-block small text-white-50">Persentase Kehadiran</span>
                            <h3 class="fw-bold m-0 <?php echo $rate < 90 ? 'text-warning' : 'text-success'; ?>"><?php echo $rate; ?>%</h3>
                        </div>
                    </div>
                </div>

                <!-- Recent Logs Table -->
                <h6 class="fw-bold mb-3 text-dark-emphasis"><i class="bi bi-clock-history me-1"></i> 30 Log Kehadiran Terakhir</h6>
                <?php if (empty($att_logs)): ?>
                    <div class="text-center py-4 text-muted border border-dashed rounded">
                        Belum ada catatan kehadiran untuk karyawan ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 60px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $a_no = 1;
                                $day_names = [
                                    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
                                    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                                ];
                                foreach ($att_logs as $log):
                                    $day_en = date('l', strtotime($log['tanggal']));
                                    $day_id = $day_names[$day_en] ?? $day_en;
                                    $formatted_date = $day_id . ', ' . date('d-m-Y', strtotime($log['tanggal']));
                                    
                                    $badge_class = 'bg-secondary';
                                    if ($log['status'] === 'Hadir') $badge_class = 'bg-success-subtle text-success-emphasis';
                                    elseif ($log['status'] === 'Sakit') $badge_class = 'bg-primary-subtle text-primary-emphasis';
                                    elseif ($log['status'] === 'Izin') $badge_class = 'bg-warning-subtle text-warning-emphasis';
                                    elseif ($log['status'] === 'Alpa') $badge_class = 'bg-danger-subtle text-danger-emphasis';
                                ?>
                                    <tr>
                                        <td><?php echo $a_no++; ?></td>
                                        <td class="fw-semibold text-dark-emphasis"><?php echo $formatted_date; ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['status']); ?></span>
                                        </td>
                                        <td class="small text-muted text-wrap" style="max-width: 300px;">
                                            <?php echo !empty($log['keterangan']) ? htmlspecialchars($log['keterangan']) : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
