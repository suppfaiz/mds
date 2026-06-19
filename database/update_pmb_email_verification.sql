-- SQL Update Script - Menambahkan Kolom Verifikasi Email untuk PMB
-- Jalankan perintah ini di database Anda:
-- mysql -u mds_user -p master_data_sekolah < database/update_pmb_email_verification.sql

ALTER TABLE `pmb_akun` ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) DEFAULT 0;
ALTER TABLE `pmb_akun` ADD COLUMN IF NOT EXISTS `verification_token` VARCHAR(6) DEFAULT NULL;
