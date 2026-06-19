<?php
$prefix = isset($path_prefix) ? $path_prefix : '';
$active = isset($active_menu) ? $active_menu : 'dashboard';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<aside class="sidebar d-flex flex-column">
    <div class="sidebar-header d-flex align-items-center gap-2">
        <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
        <div>
            <h6 class="m-0 fw-bold text-white">MDS</h6>
            <small class="text-white-50" style="font-size: 10px;">Master Data Sekolah</small>
        </div>
    </div>
    
    <div class="flex-grow-1 py-3 overflow-y-auto">
        <div class="px-3 mb-2 text-uppercase text-white-50 small" style="font-size: 11px; letter-spacing: 1px;">Menu Utama</div>
        <ul class="nav flex-column">
            <!-- Dashboard Link -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>index.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Group: Data Akademik -->
            <?php $is_akademik_active = in_array($active, ['siswa', 'guru', 'karyawan', 'kelas', 'wali_kelas']); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_akademik_active ? '' : 'collapsed'; ?>" 
                   data-bs-toggle="collapse" 
                   href="#menu-akademik" 
                   role="button" 
                   aria-expanded="<?php echo $is_akademik_active ? 'true' : 'false'; ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-mortarboard"></i>
                        <span>Data Akademik</span>
                    </div>
                    <i class="bi bi-chevron-down small chevron-icon"></i>
                </a>
                <div class="collapse <?php echo $is_akademik_active ? 'show' : ''; ?>" id="menu-akademik">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'siswa' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>siswa/index.php">
                                <i class="bi bi-people"></i>
                                <span>Data Siswa</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'guru' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>guru/index.php">
                                <i class="bi bi-person-badge"></i>
                                <span>Data Guru</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'karyawan' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>karyawan/index.php">
                                <i class="bi bi-people-fill"></i>
                                <span>Data Karyawan</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'kelas' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>kelas/index.php">
                                <i class="bi bi-building"></i>
                                <span>Data Kelas</span>
                            </a>
                        </li>
                        <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'wali_kelas' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>kelas/wali.php">
                                <i class="bi bi-person-workspace"></i>
                                <span>Wali Kelas</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>

            <!-- Group: Presensi Harian -->
            <?php $is_presensi_active = in_array($active, ['presensi_siswa', 'presensi_pegawai']); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_presensi_active ? '' : 'collapsed'; ?>" 
                   data-bs-toggle="collapse" 
                   href="#menu-presensi" 
                   role="button" 
                   aria-expanded="<?php echo $is_presensi_active ? 'true' : 'false'; ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-check"></i>
                        <span>Presensi Harian</span>
                    </div>
                    <i class="bi bi-chevron-down small chevron-icon"></i>
                </a>
                <div class="collapse <?php echo $is_presensi_active ? 'show' : ''; ?>" id="menu-presensi">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'presensi_siswa' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>presensi/siswa.php">
                                <i class="bi bi-check-circle"></i>
                                <span>Presensi Siswa</span>
                            </a>
                        </li>
                        <?php if ($role !== 'guru'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'presensi_pegawai' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>presensi/pegawai.php">
                                <i class="bi bi-calendar-range"></i>
                                <span>Presensi Pegawai</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>

            <!-- Group: Rapor Digital -->
            <?php 
            // Determine if logged-in guru is a wali kelas
            $my_wali_kelas_id = null;
            $my_wali_kelas_name = '';
            if ($role === 'guru') {
                try {
                    $stmt_wk = $pdo->prepare("
                        SELECT wk.kelas_id, k.nama_kelas 
                        FROM wali_kelas wk
                        INNER JOIN kelas k ON wk.kelas_id = k.id
                        INNER JOIN guru g ON wk.guru_id = g.id
                        WHERE LOWER(g.nama) = LOWER(?)
                    ");
                    $stmt_wk->execute([$_SESSION['nama_lengkap'] ?? '']);
                    $wk_row = $stmt_wk->fetch();
                    if ($wk_row) {
                        $my_wali_kelas_id = (int)$wk_row['kelas_id'];
                        $my_wali_kelas_name = $wk_row['nama_kelas'];
                    }
                } catch (PDOException $e) {
                    // Ignore database errors quietly
                }
            }
            
            $show_rapor_menu = ($role === 'super_admin' || $role === 'operator' || $role === 'kepala_sekolah' || $my_wali_kelas_id !== null);
            $is_rapor_active = ($active === 'rapor');
            ?>
            
            <?php if ($show_rapor_menu): ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_rapor_active ? '' : 'collapsed'; ?>" 
                   data-bs-toggle="collapse" 
                   href="#menu-rapor" 
                   role="button" 
                   aria-expanded="<?php echo $is_rapor_active ? 'true' : 'false'; ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark"></i>
                        <span>Rapor Digital</span>
                    </div>
                    <i class="bi bi-chevron-down small chevron-icon"></i>
                </a>
                <div class="collapse <?php echo $is_rapor_active ? 'show' : ''; ?>" id="menu-rapor">
                    <ul class="nav flex-column">
                        <?php if ($role === 'guru'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $is_rapor_active ? 'active' : ''; ?>" href="<?php echo $prefix; ?>rapor/index.php?kelas_id=<?php echo $my_wali_kelas_id; ?>">
                                    <i class="bi bi-journal-check"></i>
                                    <span>Rapor Kelas <?php echo htmlspecialchars($my_wali_kelas_name); ?></span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $is_rapor_active ? 'active' : ''; ?>" href="<?php echo $prefix; ?>rapor/index.php">
                                    <i class="bi bi-journals"></i>
                                    <span>Kelola Rapor</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <!-- Group: Keuangan & Payroll -->
            <?php $is_keuangan_active = in_array($active, ['spp', 'payroll', 'keuangan']); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_keuangan_active ? '' : 'collapsed'; ?>" 
                   data-bs-toggle="collapse" 
                   href="#menu-keuangan" 
                   role="button" 
                   aria-expanded="<?php echo $is_keuangan_active ? 'true' : 'false'; ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-wallet2"></i>
                        <span>Keuangan & Gaji</span>
                    </div>
                    <i class="bi bi-chevron-down small chevron-icon"></i>
                </a>
                <div class="collapse <?php echo $is_keuangan_active ? 'show' : ''; ?>" id="menu-keuangan">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'spp' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>spp/index.php">
                                <i class="bi bi-cash"></i>
                                <span>Keuangan SPP</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php if ($role === 'guru'): ?>
                                <a class="nav-link <?php echo $active === 'payroll' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>payroll/index.php">
                                    <i class="bi bi-cash-stack"></i>
                                    <span>Slip Gaji Saya</span>
                                </a>
                            <?php else: ?>
                                <a class="nav-link <?php echo $active === 'payroll' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>payroll/index.php">
                                    <i class="bi bi-cash-stack"></i>
                                    <span>Payroll & Gaji</span>
                                </a>
                            <?php endif; ?>
                        </li>
                        <?php if ($role === 'super_admin' || $role === 'operator' || $role === 'kepala_sekolah'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active === 'keuangan' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>keuangan/index.php">
                                <i class="bi bi-journal-check"></i>
                                <span>Buku Kas Umum</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>

            <!-- Group: PMB (Penerimaan Murid Baru) -->
            <?php if ($role === 'super_admin' || $role === 'operator' || $role === 'kepala_sekolah'): ?>
                <?php $is_pmb_active = in_array($active, ['pmb_data', 'pmb_setting']); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_pmb_active ? '' : 'collapsed'; ?>" 
                       data-bs-toggle="collapse" 
                       href="#menu-pmb" 
                       role="button" 
                       aria-expanded="<?php echo $is_pmb_active ? 'true' : 'false'; ?>">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-plus"></i>
                            <span>Pendaftaran PMB</span>
                        </div>
                        <i class="bi bi-chevron-down small chevron-icon"></i>
                    </a>
                    <div class="collapse <?php echo $is_pmb_active ? 'show' : ''; ?>" id="menu-pmb">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active === 'pmb_data' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>pmb/index.php">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Data Pendaftar</span>
                                </a>
                            </li>
                            <?php if ($role === 'super_admin' || $role === 'operator'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active === 'pmb_setting' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>pmb/pengaturan.php">
                                    <i class="bi bi-gear-fill"></i>
                                    <span>Pengaturan PMB</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </li>
            <?php endif; ?>
            
            <!-- Group: Laporan & Audit -->
            <?php if ($role === 'super_admin' || $role === 'kepala_sekolah'): ?>
                <?php $is_laporan_active = ($active === 'logs'); ?>
                <li class="nav-item mt-3">
                    <div class="px-3 mb-1 text-uppercase text-white-50 small" style="font-size: 10px; letter-spacing: 0.5px;">Laporan</div>
                    <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_laporan_active ? '' : 'collapsed'; ?>" 
                       data-bs-toggle="collapse" 
                       href="#menu-laporan" 
                       role="button" 
                       aria-expanded="<?php echo $is_laporan_active ? 'true' : 'false'; ?>">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span>Laporan & Audit</span>
                        </div>
                        <i class="bi bi-chevron-down small chevron-icon"></i>
                    </a>
                    <div class="collapse <?php echo $is_laporan_active ? 'show' : ''; ?>" id="menu-laporan">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active === 'logs' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>logs/index.php">
                                    <i class="bi bi-journal-text"></i>
                                    <span>Audit Log Aktivitas</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            <?php endif; ?>

            <!-- Group: Sistem & Database -->
            <?php if ($role === 'super_admin'): ?>
                <?php $is_sistem_active = in_array($active, ['users', 'backup', 'pengaturan']); ?>
                <li class="nav-item mt-3">
                    <div class="px-3 mb-1 text-uppercase text-white-50 small" style="font-size: 10px; letter-spacing: 0.5px;">Administrasi</div>
                    <a class="nav-link d-flex align-items-center justify-content-between <?php echo $is_sistem_active ? '' : 'collapsed'; ?>" 
                       data-bs-toggle="collapse" 
                       href="#menu-sistem" 
                       role="button" 
                       aria-expanded="<?php echo $is_sistem_active ? 'true' : 'false'; ?>">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-gear"></i>
                            <span>Sistem & DB</span>
                        </div>
                        <i class="bi bi-chevron-down small chevron-icon"></i>
                    </a>
                    <div class="collapse <?php echo $is_sistem_active ? 'show' : ''; ?>" id="menu-sistem">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active === 'users' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>users/index.php">
                                    <i class="bi bi-person-gear"></i>
                                    <span>Manajemen User</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active === 'backup' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>database/backup.php">
                                    <i class="bi bi-database-down"></i>
                                    <span>Backup & Restore</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active === 'pengaturan' ? 'active' : ''; ?>" href="<?php echo $prefix; ?>pengaturan/index.php">
                                    <i class="bi bi-gear-fill"></i>
                                    <span>Pengaturan Sekolah</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="p-3 border-top border-secondary-subtle mt-auto bg-dark-subtle d-flex align-items-center justify-content-between">
        <div class="d-flex flex-column text-truncate">
            <span class="text-white fw-semibold small text-truncate" style="max-width: 150px;">
                <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
            </span>
            <small class="text-white-50 text-capitalize" style="font-size: 10px;">
                <?php 
                $role_labels = [
                    'super_admin' => 'super admin',
                    'operator' => 'operator',
                    'guru' => 'guru',
                    'kepala_sekolah' => 'kepala sekolah'
                ];
                echo $role_labels[$role] ?? $role; 
                ?>
            </small>
        </div>
        <a href="<?php echo $prefix; ?>auth/logout.php" class="btn btn-sm btn-outline-danger" title="Keluar">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>
