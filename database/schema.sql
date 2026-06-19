-- CREATE DATABASE IF NOT EXISTS master_data_sekolah;
-- USE master_data_sekolah;

-- 1. Table users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'operator', 'guru', 'kepala_sekolah') NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table kelas
CREATE TABLE IF NOT EXISTS `kelas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kelas` VARCHAR(50) NOT NULL UNIQUE,
  `tarif_spp` DECIMAL(12,2) NOT NULL DEFAULT 500000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table siswa
CREATE TABLE IF NOT EXISTS `siswa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nis` VARCHAR(20) NOT NULL UNIQUE,
  `nisn` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `jenis_kelamin` ENUM('L', 'P') NOT NULL,
  `tempat_lahir` VARCHAR(50) NOT NULL,
  `tanggal_lahir` DATE NOT NULL,
  `alamat` TEXT NOT NULL,
  `agama` VARCHAR(30) NOT NULL,
  `kelas_id` INT DEFAULT NULL,
  `tahun_masuk` INT NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `nama_ayah` VARCHAR(100) NOT NULL,
  `nama_ibu` VARCHAR(100) NOT NULL,
  `pekerjaan_ayah` VARCHAR(100) DEFAULT NULL,
  `pekerjaan_ibu` VARCHAR(100) DEFAULT NULL,
  `no_hp_ortu` VARCHAR(20) DEFAULT NULL,
  `alamat_ortu` TEXT DEFAULT NULL,
  `nik_ayah` VARCHAR(20) DEFAULT NULL,
  `nik_ibu` VARCHAR(20) DEFAULT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`kelas_id`) REFERENCES `kelas`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table guru
CREATE TABLE IF NOT EXISTS `guru` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nip` VARCHAR(20) DEFAULT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `mata_pelajaran` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `pendidikan_terakhir` VARCHAR(50) NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `alamat` TEXT NOT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table dokumen
CREATE TABLE IF NOT EXISTS `dokumen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipe_data` ENUM('siswa', 'guru', 'karyawan') NOT NULL,
  `data_id` INT NOT NULL,
  `kategori` VARCHAR(50) NOT NULL,
  `nama_file` VARCHAR(255) NOT NULL,
  `lokasi_file` VARCHAR(255) NOT NULL,
  `tanggal_upload` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table audit_log
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `username` VARCHAR(50) DEFAULT NULL,
  `aktivitas` VARCHAR(100) NOT NULL,
  `detail` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `tanggal_akses` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table nilai
CREATE TABLE IF NOT EXISTS `nilai` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `siswa_id` INT NOT NULL,
  `mata_pelajaran` VARCHAR(100) NOT NULL,
  `nilai_tugas` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `nilai_uts` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `nilai_uas` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `nilai_akhir` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `semester` ENUM('Ganjil', 'Genap') NOT NULL,
  `tahun_ajaran` VARCHAR(20) NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`siswa_id`) REFERENCES `siswa`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial seeds
INSERT IGNORE INTO `users` (`id`, `username`, `password`, `role`, `nama_lengkap`) VALUES
(1, 'admin', '$2y$12$4fCmQG1SdyFR862lnr.Qw.P9/K9tJsVNA6DznFmnqDOryQxdd.p5C', 'super_admin', 'Administrator Super'),
(2, 'operator', '$2y$12$4H7yFPRExpXSqtLARsxp2ODh/4sreHDnzmIePAIv1CaE8LCkVIkmq', 'operator', 'Operator Sekolah'),
(3, 'guru', '$2y$12$gmea2Un/.L/DHnT3ZfGyO.G1BqmhmWM4A99vyuABEkSAs6.KPYbPS', 'guru', 'Guru Pengajar'),
(4, 'kepsek', '$2y$12$.C8j4sm1uTw3uotHg2KJkO.ZqfrBXLwjs.416jRNQ9FEBVPICtjV.', 'kepala_sekolah', 'Kepala Sekolah');

INSERT IGNORE INTO `kelas` (`id`, `nama_kelas`) VALUES
(1, 'Kelas X-A'),
(2, 'Kelas X-B'),
(3, 'Kelas XI-IPA'),
(4, 'Kelas XI-IPS'),
(5, 'Kelas XII-IPA'),
(6, 'Kelas XII-IPS');

-- Siswa seeds
INSERT IGNORE INTO `siswa` (`id`, `nis`, `nisn`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `agama`, `kelas_id`, `tahun_masuk`, `no_hp`, `email`, `nama_ayah`, `nama_ibu`) VALUES
(1, '20260001', '0091234567', 'Budi Santoso', 'L', 'Jakarta', '2009-08-15', 'Jl. Merdeka No. 123, Jakarta', 'Islam', 1, 2025, '081234567890', 'budi.santoso@siswa.sch.id', 'Ahmad Santoso', 'Siti Aminah');

-- Guru seeds
INSERT IGNORE INTO `guru` (`id`, `nip`, `nama`, `mata_pelajaran`, `jabatan`, `pendidikan_terakhir`, `no_hp`, `email`, `alamat`) VALUES
(1, '198503122010121002', 'Aisyah Rahmawati, S.Pd.', 'Matematika', 'Wali Kelas', 'D4 / S1', '089876543210', 'aisyah.rahma@guru.sch.id', 'Jl. Melati No. 45, Bandung');

-- Nilai seeds
INSERT IGNORE INTO `nilai` (`id`, `siswa_id`, `mata_pelajaran`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `nilai_akhir`, `semester`, `tahun_ajaran`, `keterangan`) VALUES
(1, 1, 'Matematika', 80.00, 85.00, 90.00, 85.50, 'Ganjil', '2025/2026', 'Pertahankan prestasi Anda!');

-- Dokumen seeds
INSERT IGNORE INTO `dokumen` (`id`, `tipe_data`, `data_id`, `kategori`, `nama_file`, `lokasi_file`) VALUES
(1, 'siswa', 1, 'Akta Kelahiran', 'akta_kelahiran_budi.pdf', 'uploads/siswa/akta_kelahiran_budi.pdf'),
(2, 'guru', 1, 'KTP', 'ktp_aisyah.pdf', 'uploads/guru/ktp_aisyah.pdf');

-- 8. Table karyawan
CREATE TABLE IF NOT EXISTS `karyawan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nik` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `alamat` TEXT NOT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table payroll
CREATE TABLE IF NOT EXISTS `payroll` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipe_penerima` ENUM('guru', 'karyawan') NOT NULL,
  `penerima_id` INT NOT NULL,
  `bulan` INT NOT NULL,
  `tahun` INT NOT NULL,
  `gaji_pokok` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tunjangan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `potongan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `gaji_bersih` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status_bayar` ENUM('Belum Dibayar', 'Dibayar') NOT NULL DEFAULT 'Belum Dibayar',
  `tanggal_bayar` DATE DEFAULT NULL,
  `catatan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_penerima_periode` (`tipe_penerima`, `penerima_id`, `bulan`, `tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Karyawan seeds
INSERT IGNORE INTO `karyawan` (`id`, `nik`, `nama`, `jabatan`, `no_hp`, `email`, `alamat`) VALUES
(1, '3201021234560001', 'Slamet Basuki', 'Staf TU (Tata Usaha)', '085712345678', 'slamet.tu@pegawai.sch.id', 'Jl. Kenanga No. 12, Bandung'),
(2, '3201027890120002', 'Bambang Triyono', 'Petugas Keamanan', '085876543210', 'bambang.security@pegawai.sch.id', 'Jl. Mawar No. 34, Bandung');

-- Payroll seeds
INSERT IGNORE INTO `payroll` (`tipe_penerima`, `penerima_id`, `bulan`, `tahun`, `gaji_pokok`, `tunjangan`, `potongan`, `gaji_bersih`, `status_bayar`, `tanggal_bayar`, `catatan`) VALUES
('guru', 1, 6, 2026, 4000000.00, 500000.00, 150000.00, 4350000.00, 'Dibayar', '2026-06-15', 'Gaji bulan Juni 2026'),
('karyawan', 1, 6, 2026, 3000000.00, 300000.00, 100000.00, 3200000.00, 'Dibayar', '2026-06-15', 'Gaji bulan Juni 2026'),
('karyawan', 2, 6, 2026, 2500000.00, 200000.00, 80000.00, 2620000.00, 'Belum Dibayar', NULL, 'Gaji bulan Juni 2026');

-- 10. Table pengaturan
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `nama_sekolah` VARCHAR(100) NOT NULL,
  `alamat_sekolah` TEXT NOT NULL,
  `no_telp` VARCHAR(20) NOT NULL,
  `email_sekolah` VARCHAR(100) NOT NULL,
  `website` VARCHAR(100) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `nama_kepsek` VARCHAR(100) NOT NULL,
  `nip_kepsek` VARCHAR(50) NOT NULL,
  `nama_bendahara` VARCHAR(100) NOT NULL,
  `nip_bendahara` VARCHAR(50) NOT NULL,
  `nama_bank` VARCHAR(100) DEFAULT NULL,
  `nama_rekening` VARCHAR(100) DEFAULT NULL,
  `nomor_rekening` VARCHAR(50) DEFAULT NULL,
  `pmb_status` ENUM('Buka', 'Tutup') DEFAULT 'Tutup',
  `pmb_mulai` DATE DEFAULT NULL,
  `pmb_selesai` DATE DEFAULT NULL,
  `pmb_kuota` INT DEFAULT 100,
  CONSTRAINT `chk_single_row` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings seeds
INSERT INTO `pengaturan` (`id`, `nama_sekolah`, `alamat_sekolah`, `no_telp`, `email_sekolah`, `website`, `logo`, `nama_kepsek`, `nip_kepsek`, `nama_bendahara`, `nip_bendahara`, `nama_bank`, `nama_rekening`, `nomor_rekening`)
VALUES (1, 'SMA NEGERI NUSANTARA', 'Jl. Pendidikan No. 1, Kota Mandiri', '08123456789', 'info@smanusantara.sch.id', 'www.smanusantara.sch.id', NULL, 'Drs. H. Mulyadi, M.Pd.', '19700512 199503 1 002', 'Indah Permata, S.E.', '19881024 201212 2 003', 'Bank Mandiri', 'SMA NEGERI NUSANTARA', '1234567890')
ON DUPLICATE KEY UPDATE id=1;

-- 11. Table spp_pembayaran
CREATE TABLE IF NOT EXISTS `spp_pembayaran` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `siswa_id` INT NOT NULL,
  `bulan` INT NOT NULL,
  `tahun` INT NOT NULL,
  `jumlah_bayar` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tanggal_bayar` DATE NOT NULL,
  `status_bayar` ENUM('Belum Lunas', 'Lunas') NOT NULL DEFAULT 'Belum Lunas',
  `penerima_oleh` VARCHAR(100) DEFAULT NULL,
  `catatan` TEXT DEFAULT NULL,
  `invoice_token` VARCHAR(64) UNIQUE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`siswa_id`) REFERENCES `siswa`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_siswa_spp_periode` (`siswa_id`, `bulan`, `tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SPP seeds
INSERT INTO `spp_pembayaran` (`siswa_id`, `bulan`, `tahun`, `jumlah_bayar`, `tanggal_bayar`, `status_bayar`, `penerima_oleh`, `catatan`, `invoice_token`)
VALUES (1, 6, 2026, 500000.00, '2026-06-10', 'Lunas', 'admin', 'Pembayaran SPP Budi Bulan Juni 2026', 'spp_tok_1_default123')
ON DUPLICATE KEY UPDATE jumlah_bayar = 500000.00;

-- 12. Table presensi_siswa
CREATE TABLE IF NOT EXISTS `presensi_siswa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `siswa_id` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `status` ENUM('Hadir', 'Sakit', 'Izin', 'Alpa') NOT NULL,
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`siswa_id`) REFERENCES `siswa`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_siswa_tanggal` (`siswa_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Table presensi_pegawai
CREATE TABLE IF NOT EXISTS `presensi_pegawai` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipe_penerima` ENUM('guru', 'karyawan') NOT NULL,
  `penerima_id` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `status` ENUM('Hadir', 'Sakit', 'Izin', 'Alpa') NOT NULL,
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_penerima_tanggal` (`tipe_penerima`, `penerima_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance seeds
INSERT IGNORE INTO `presensi_siswa` (`siswa_id`, `tanggal`, `status`, `keterangan`) VALUES
(1, '2026-06-15', 'Hadir', 'Tepat waktu'),
(1, '2026-06-16', 'Hadir', 'Tepat waktu'),
(1, '2026-06-17', 'Hadir', 'Tepat waktu');

INSERT IGNORE INTO `presensi_pegawai` (`tipe_penerima`, `penerima_id`, `tanggal`, `status`, `keterangan`) VALUES
('guru', 1, '2026-06-17', 'Hadir', 'Mengajar Matematika'),
('karyawan', 1, '2026-06-17', 'Hadir', 'Tugas TU'),
('karyawan', 2, '2026-06-17', 'Izin', 'Urusan keluarga');

-- 14. Table wali_kelas
CREATE TABLE IF NOT EXISTS `wali_kelas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `guru_id` INT NOT NULL,
  `kelas_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`guru_id`) REFERENCES `guru`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`kelas_id`) REFERENCES `kelas`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_kelas_wali` (`kelas_id`),
  UNIQUE KEY `idx_guru_wali` (`guru_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Table rapor_catatan
CREATE TABLE IF NOT EXISTS `rapor_catatan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `siswa_id` INT NOT NULL,
  `semester` ENUM('Ganjil', 'Genap') NOT NULL,
  `tahun_ajaran` VARCHAR(20) NOT NULL,
  `ekstrakurikuler` TEXT DEFAULT NULL,
  `kelakuan` ENUM('A', 'B', 'C', 'D') NOT NULL DEFAULT 'B',
  `kerajinan` ENUM('A', 'B', 'C', 'D') NOT NULL DEFAULT 'B',
  `kerapihan` ENUM('A', 'B', 'C', 'D') NOT NULL DEFAULT 'B',
  `catatan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`siswa_id`) REFERENCES `siswa`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_siswa_rapor_periode` (`siswa_id`, `semester`, `tahun_ajaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rapor & Wali seeds
INSERT IGNORE INTO `wali_kelas` (`guru_id`, `kelas_id`) VALUES (1, 1);

INSERT IGNORE INTO `rapor_catatan` (`siswa_id`, `semester`, `tahun_ajaran`, `ekstrakurikuler`, `kelakuan`, `kerajinan`, `kerapihan`, `catatan`) VALUES
(1, 'Ganjil', '2025/2026', 'Pramuka: Baik, Futsal: Cukup', 'A', 'B', 'A', 'Pertahankan prestasi akademik Anda!');

-- 16. Table keuangan_transaksi
CREATE TABLE IF NOT EXISTS `keuangan_transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tanggal` DATE NOT NULL,
  `tipe` ENUM('Pemasukan', 'Pengeluaran') NOT NULL,
  `kategori` VARCHAR(100) NOT NULL,
  `nominal` DECIMAL(12,2) NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `pencatat` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16b. Table pmb_akun
CREATE TABLE IF NOT EXISTS `pmb_akun` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_token` VARCHAR(6) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Table pmb_pendaftar
CREATE TABLE IF NOT EXISTS `pmb_pendaftar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_pendaftaran` VARCHAR(25) UNIQUE NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `jenis_kelamin` ENUM('L', 'P') NOT NULL,
  `tempat_lahir` VARCHAR(50) NOT NULL,
  `tanggal_lahir` DATE NOT NULL,
  `asal_sekolah` VARCHAR(100) NOT NULL,
  `nama_ortu` VARCHAR(100) NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `alamat` TEXT NOT NULL,
  `status` ENUM('Pending', 'Diterima', 'Ditolak') NOT NULL DEFAULT 'Pending',
  `dokumen_bukti` VARCHAR(255) DEFAULT NULL,
  `token` VARCHAR(64) UNIQUE NOT NULL,
  `siswa_id` INT DEFAULT NULL,
  `pmb_akun_id` INT DEFAULT NULL,
  `catatan_panitia` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`siswa_id`) REFERENCES `siswa`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`pmb_akun_id`) REFERENCES `pmb_akun`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Buku Kas seeds
INSERT INTO `keuangan_transaksi` (`id`, `tanggal`, `tipe`, `kategori`, `nominal`, `keterangan`, `pencatat`) VALUES
(1, '2026-06-01', 'Pemasukan', 'Dana BOS', 25000000.00, 'Penerimaan dana BOS Triwulan II', 'admin'),
(2, '2026-06-05', 'Pengeluaran', 'Listrik & Air', 1250000.00, 'Pembayaran tagihan listrik dan air sekolah bulan Mei', 'admin'),
(3, '2026-06-10', 'Pengeluaran', 'Alat Tulis Kantor', 450000.00, 'Pembelian kertas HVS dan spidol papan tulis', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- PMB seeds
INSERT INTO `pmb_pendaftar` (`id`, `no_pendaftaran`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `asal_sekolah`, `nama_ortu`, `no_hp`, `alamat`, `status`, `dokumen_bukti`, `token`) VALUES
(1, 'PMB-2026-0001', 'Rian Hidayat', 'L', 'Bandung', '2010-04-12', 'SMP Negeri 1 Bandung', 'Asep Hidayat', '081234567890', 'Jl. Merdeka No. 10, Bandung', 'Pending', NULL, 'pmb_tok_1_rianhidayat'),
(2, 'PMB-2026-0002', 'Siti Aminah', 'P', 'Bandung', '2010-08-25', 'SMP Pasundan 1', 'Mulyono', '082198765432', 'Jl. Braga No. 45, Bandung', 'Diterima', NULL, 'pmb_tok_2_sitiaminah')
ON DUPLICATE KEY UPDATE id=id;



