<?php
$path_prefix = '../';
$page_title = 'Detail Profil Siswa';
$active_menu = 'siswa';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

checkLogin();

$siswa = null;
$documents = [];

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $siswa = $stmt->fetch();
        
        if (!$siswa) {
            $_SESSION['error_message'] = 'Siswa tidak ditemukan.';
            header("Location: index.php");
            exit();
        }

        // Fetch student documents
        $doc_stmt = $pdo->prepare("SELECT * FROM dokumen WHERE tipe_data = 'siswa' AND data_id = ? ORDER BY tanggal_upload DESC");
        $doc_stmt->execute([$id]);
        $documents = $doc_stmt->fetchAll();

        // Fetch student grades
        $nilai_stmt = $pdo->prepare("SELECT * FROM nilai WHERE siswa_id = ? ORDER BY tahun_ajaran DESC, semester DESC, mata_pelajaran ASC");
        $nilai_stmt->execute([$id]);
        $grades = $nilai_stmt->fetchAll();

        // Fetch student SPP payments
        $spp_stmt = $pdo->prepare("SELECT * FROM spp_pembayaran WHERE siswa_id = ? ORDER BY tahun DESC, bulan DESC");
        $spp_stmt->execute([$id]);
        $spp_payments = $spp_stmt->fetchAll();

        // Fetch student attendance summary
        $att_summary_stmt = $pdo->prepare("
            SELECT status, COUNT(*) as jumlah 
            FROM presensi_siswa 
            WHERE siswa_id = ? 
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

        // Fetch recent student attendance logs
        $att_logs_stmt = $pdo->prepare("
            SELECT tanggal, status, keterangan 
            FROM presensi_siswa 
            WHERE siswa_id = ? 
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
    $_SESSION['error_message'] = 'ID Siswa tidak valid.';
    header("Location: index.php");
    exit();
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between gap-2 mb-4 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold mb-0">Profil Siswa</h4>
    </div>
    
    <div class="d-flex gap-2">
        <a href="print.php?id=<?php echo $siswa['id']; ?>" target="_blank" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-printer"></i> Cetak Profil
        </a>
        <?php if (hasPermission(['super_admin', 'operator'])): ?>
            <a href="edit.php?id=<?php echo $siswa['id']; ?>" class="btn btn-primary d-flex align-items-center gap-2">
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
                        <?php if (!empty($siswa['foto']) && file_exists('../' . $siswa['foto'])): ?>
                            <img src="../<?php echo htmlspecialchars($siswa['foto']); ?>" alt="Foto" class="profile-img">
                        <?php else: ?>
                            <i class="bi bi-person text-secondary" style="font-size: 4.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center text-sm-start">
                        <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Diatur'); ?></span>
                        <h4 class="fw-bold mb-1 text-dark-emphasis"><?php echo htmlspecialchars($siswa['nama']); ?></h4>
                        <p class="text-muted small mb-3">NIS: <?php echo htmlspecialchars($siswa['nis']); ?> &nbsp;|&nbsp; NISN: <?php echo htmlspecialchars($siswa['nisn']); ?></p>
                        <div class="d-flex flex-wrap justify-content-center justify-content-sm-start gap-2">
                            <span class="badge bg-light text-dark border"><i class="bi bi-calendar-check text-primary me-1"></i> Masuk: <?php echo htmlspecialchars($siswa['tahun_masuk']); ?></span>
                            <span class="badge bg-light text-dark border"><i class="bi bi-gender-ambiguous text-info me-1"></i> <?php echo $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></span>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold my-4 text-dark-emphasis">Informasi Detail</h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Tempat & Tanggal Lahir</span>
                        <span class="fw-semibold"><?php echo htmlspecialchars($siswa['tempat_lahir'] . ', ' . date('d F Y', strtotime($siswa['tanggal_lahir']))); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Agama</span>
                        <span class="fw-semibold"><?php echo htmlspecialchars($siswa['agama']); ?></span>
                    </div>
                    
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Nomor HP / WA</span>
                        <span class="fw-semibold"><?php echo htmlspecialchars($siswa['no_hp']); ?></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Email</span>
                        <span class="fw-semibold"><?php echo !empty($siswa['email']) ? htmlspecialchars($siswa['email']) : '-'; ?></span>
                    </div>
                    
                    <div class="col-12">
                        <span class="text-muted small d-block">Alamat Lengkap</span>
                        <span class="fw-semibold d-block mt-1 p-2 bg-light rounded text-dark-emphasis" style="font-size: 14px; min-height: 50px;">
                            <?php echo nl2br(htmlspecialchars($siswa['alamat'])); ?>
                        </span>
                    </div>
                </div>

                <h5 class="fw-bold my-4 text-dark-emphasis border-top pt-4">Data Orang Tua / Wali</h5>
                <div class="row g-3">
                    <div class="col-sm-6 border-end">
                        <span class="text-muted small d-block fw-semibold mb-2 text-primary">Detail Ayah</span>
                        <div class="mb-2">
                            <span class="text-muted small d-block">Nama Ayah</span>
                            <span class="fw-semibold"><?php echo !empty($siswa['nama_ayah']) ? htmlspecialchars($siswa['nama_ayah']) : '-'; ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted small d-block">NIK Ayah</span>
                            <span class="fw-semibold"><?php echo !empty($siswa['nik_ayah']) ? htmlspecialchars($siswa['nik_ayah']) : '-'; ?></span>
                        </div>
                        <div>
                            <span class="text-muted small d-block">Pekerjaan Ayah</span>
                            <span class="fw-semibold"><?php echo !empty($siswa['pekerjaan_ayah']) ? htmlspecialchars($siswa['pekerjaan_ayah']) : '-'; ?></span>
                        </div>
                    </div>
                    
                    <div class="col-sm-6">
                        <span class="text-muted small d-block fw-semibold mb-2 text-success">Detail Ibu</span>
                        <div class="mb-2">
                            <span class="text-muted small d-block">Nama Ibu</span>
                            <span class="fw-semibold"><?php echo !empty($siswa['nama_ibu']) ? htmlspecialchars($siswa['nama_ibu']) : '-'; ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted small d-block">NIK Ibu</span>
                            <span class="fw-semibold"><?php echo !empty($siswa['nik_ibu']) ? htmlspecialchars($siswa['nik_ibu']) : '-'; ?></span>
                        </div>
                        <div>
                            <span class="text-muted small d-block">Pekerjaan Ibu</span>
                            <span class="fw-semibold"><?php echo !empty($siswa['pekerjaan_ibu']) ? htmlspecialchars($siswa['pekerjaan_ibu']) : '-'; ?></span>
                        </div>
                    </div>
                    
                    <div class="col-sm-6 mt-4">
                        <span class="text-muted small d-block">Nomor HP Orang Tua</span>
                        <span class="fw-semibold"><?php echo !empty($siswa['no_hp_ortu']) ? htmlspecialchars($siswa['no_hp_ortu']) : '-'; ?></span>
                    </div>
                    
                    <div class="col-12 mt-3">
                        <span class="text-muted small d-block">Alamat Orang Tua</span>
                        <span class="fw-semibold d-block mt-1 p-2 bg-light rounded text-dark-emphasis" style="font-size: 13px; min-height: 45px;">
                            <?php 
                            if (!empty($siswa['alamat_ortu'])) {
                                echo nl2br(htmlspecialchars($siswa['alamat_ortu']));
                            } else {
                                echo '<span class="text-muted italic">Sama dengan alamat siswa</span>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dokumen Locker Card -->
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-folder2-open text-primary me-2"></i> Dokumen Digital</h5>
            </div>
            
            <div class="card-body p-4">
                <?php if (hasPermission(['super_admin', 'operator'])): ?>
                    <!-- Form Upload Dokumen -->
                    <form action="../dokumen/upload.php" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded mb-4">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="tipe_data" value="siswa">
                        <input type="hidden" name="data_id" value="<?php echo $siswa['id']; ?>">
                        
                        <h6 class="fw-bold text-dark-emphasis mb-3 small"><i class="bi bi-cloud-arrow-up"></i> Upload Dokumen Baru</h6>
                        
                        <div class="mb-3">
                            <label for="kategori" class="form-label small fw-semibold">Kategori Dokumen</label>
                            <select class="form-select form-select-sm" id="kategori" name="kategori" required>
                                <option value="" disabled selected>Pilih Kategori...</option>
                                <option value="Akta Kelahiran">Akta Kelahiran</option>
                                <option value="Kartu Keluarga">Kartu Keluarga</option>
                                <option value="KTP Ayah">KTP Ayah</option>
                                <option value="KTP Ibu">KTP Ibu</option>
                                <option value="KTP Wali">KTP Wali</option>
                                <option value="Rapor">Rapor</option>
                                <option value="Ijazah">Ijazah</option>
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
                                    <!-- Preview Link (in new tab) -->
                                    <a href="../dokumen/view.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn btn-sm btn-light border" title="Preview Dokumen">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <!-- Download Link -->
                                    <a href="../dokumen/view.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-light border text-primary" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <!-- Delete Link -->
                                    <?php if (hasPermission(['super_admin', 'operator'])): ?>
                                        <a href="../dokumen/delete.php?id=<?php echo $doc['id']; ?>&siswa_id=<?php echo $siswa['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus file dokumen digital ini?')" title="Hapus Dokumen">
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

<!-- Transkrip Nilai Akademik Card -->
<div class="row mt-4 mb-4" id="transkrip">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-journal-bookmark-fill text-success me-2"></i> Transkrip Nilai Akademik</h5>
                <?php if (hasPermission(['super_admin', 'operator', 'guru'])): ?>
                    <a href="nilai_input.php?siswa_id=<?php echo $siswa['id']; ?>" class="btn btn-sm btn-success d-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle"></i> Input Nilai Baru
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4">
                <?php 
                // Group grades by Tahun Ajaran & Semester
                $grouped_grades = [];
                foreach ($grades as $grade) {
                    $group_key = $grade['tahun_ajaran'] . ' - ' . $grade['semester'];
                    $grouped_grades[$group_key][] = $grade;
                }
                
                if (empty($grouped_grades)): 
                ?>
                    <div class="text-center py-4 text-muted">
                        Belum ada nilai akademik yang diinputkan untuk siswa ini.
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_grades as $semester_name => $semester_grades): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center" style="font-size: 15px;">
                                <span><i class="bi bi-calendar3 me-2"></i> Tahun Ajaran <?php echo htmlspecialchars($semester_name); ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                    Rata-rata: <?php 
                                    $sum = 0;
                                    foreach ($semester_grades as $sg) $sum += (float)$sg['nilai_akhir'];
                                    echo number_format($sum / count($semester_grades), 2);
                                    ?>
                                </span>
                            </h6>
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">No</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Nilai Tugas (30%)</th>
                                            <th>Nilai UTS (30%)</th>
                                            <th>Nilai UAS (40%)</th>
                                            <th>Nilai Akhir</th>
                                            <th>Predikat</th>
                                            <th>Keterangan</th>
                                            <?php if (hasPermission(['super_admin', 'operator', 'guru'])): ?>
                                                <th class="px-4 text-end" style="width: 160px;">Aksi</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($semester_grades as $grade):
                                            $final = (float)$grade['nilai_akhir'];
                                            if ($final >= 85) {
                                                $badge_class = 'bg-success-subtle text-success';
                                                $predikat = 'A (Sangat Baik)';
                                            } elseif ($final >= 75) {
                                                $badge_class = 'bg-primary-subtle text-primary';
                                                $predikat = 'B (Baik)';
                                            } elseif ($final >= 60) {
                                                $badge_class = 'bg-warning-subtle text-warning';
                                                $predikat = 'C (Cukup)';
                                            } else {
                                                $badge_class = 'bg-danger-subtle text-danger';
                                                $predikat = 'D (Kurang)';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td class="fw-semibold text-dark-emphasis"><?php echo htmlspecialchars($grade['mata_pelajaran']); ?></td>
                                                <td><?php echo number_format($grade['nilai_tugas'], 2); ?></td>
                                                <td><?php echo number_format($grade['nilai_uts'], 2); ?></td>
                                                <td><?php echo number_format($grade['nilai_uas'], 2); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?> font-monospace px-3 py-1">
                                                        <?php echo number_format($grade['nilai_akhir'], 2); ?>
                                                    </span>
                                                </td>
                                                <td><span class="small fw-semibold"><?php echo $predikat; ?></span></td>
                                                <td class="small text-muted text-wrap" style="max-width: 200px;">
                                                    <?php echo !empty($grade['keterangan']) ? htmlspecialchars($grade['keterangan']) : '-'; ?>
                                                </td>
                                                <?php if (hasPermission(['super_admin', 'operator', 'guru'])): ?>
                                                    <td class="px-4 text-end">
                                                        <div class="d-flex justify-content-end gap-1">
                                                            <a href="nilai_input.php?siswa_id=<?php echo $siswa['id']; ?>&id=<?php echo $grade['id']; ?>" class="btn btn-sm btn-outline-warning text-dark border-warning" title="Edit Nilai">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="nilai_delete.php?id=<?php echo $grade['id']; ?>&siswa_id=<?php echo $siswa['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus data nilai ini?')" title="Hapus Nilai">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SPP Payments History Card -->
<div class="row mb-4" id="spp_history">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-wallet2 text-primary me-2"></i> Riwayat Pembayaran SPP</h5>
                <?php if (hasPermission(['super_admin', 'operator'])): ?>
                    <a href="../spp/create.php" class="btn btn-sm btn-primary d-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle"></i> Input Pembayaran Baru
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4">
                <?php if (empty($spp_payments)): ?>
                    <div class="text-center py-4 text-muted">
                        Belum ada riwayat pembayaran SPP yang tercatat untuk siswa ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">No</th>
                                    <th>Periode SPP</th>
                                    <th>Jumlah Bayar</th>
                                    <th>Tanggal Bayar</th>
                                    <th>Status</th>
                                    <th>Penerima</th>
                                    <th class="px-4 text-end" style="width: 160px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $s_no = 1;
                                $spp_month_names = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                                    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                                foreach ($spp_payments as $sp):
                                ?>
                                    <tr>
                                        <td><?php echo $s_no++; ?></td>
                                        <td class="fw-semibold text-dark-emphasis"><?php echo $spp_month_names[$sp['bulan']] . ' ' . $sp['tahun']; ?></td>
                                        <td class="fw-bold text-primary">Rp <?php echo number_format($sp['jumlah_bayar'], 0, ',', '.'); ?></td>
                                        <td class="small text-muted"><?php echo date('d/m/Y', strtotime($sp['tanggal_bayar'])); ?></td>
                                        <td>
                                            <?php if ($sp['status_bayar'] === 'Lunas'): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> Lunas</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-clock"></i> Belum Lunas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($sp['penerima_oleh'] ?? '-'); ?></td>
                                        <td class="px-4 text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="../spp/print.php?id=<?php echo $sp['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Kuitansi">
                                                    <i class="bi bi-printer"></i> Cetak Kuitansi
                                                </a>
                                            </div>
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

<!-- Presensi Harian Card -->
<div class="row mb-4" id="presensi_history">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check text-warning me-2"></i> Rekapitulasi Presensi Harian</h5>
                <?php if (hasPermission(['super_admin', 'operator', 'guru'])): ?>
                    <a href="../presensi/siswa.php?kelas_id=<?php echo $siswa['kelas_id']; ?>" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square"></i> Kelola Presensi Kelas
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
                        $rate_badge = 'bg-success';
                        if ($rate < 90) $rate_badge = 'bg-warning text-dark';
                        if ($rate < 75) $rate_badge = 'bg-danger';
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
                        Belum ada catatan presensi untuk siswa ini.
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
