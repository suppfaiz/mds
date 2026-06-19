<?php
/**
 * SMTP MAIL HELPER
 * Menggunakan PHPMailer untuk mengirim email verifikasi OTP.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

/**
 * Mengirim email verifikasi pendaftaran akun PMB (OTP 6-digit)
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $otpCode
 * @return bool
 */
function sendVerificationEmail($toEmail, $toName, $otpCode) {
    // Ambil konfigurasi SMTP dari $_ENV (yang diload via config/db.php)
    $host   = $_ENV['SMTP_HOST'] ?? '';
    $port   = $_ENV['SMTP_PORT'] ?? 587;
    $user   = $_ENV['SMTP_USER'] ?? '';
    $pass   = $_ENV['SMTP_PASS'] ?? '';
    $secure = $_ENV['SMTP_SECURE'] ?? 'tls';

    if (empty($host) || empty($user) || empty($pass)) {
        error_log("Gagal mengirim email: Konfigurasi SMTP di .env belum diatur.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Konfigurasi Server SMTP
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        
        // Atur enkripsi berdasarkan preferensi .env
        if (strtolower($secure) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (Port 465)
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS (Port 587)
        }
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';

        // Penerima & Pengirim
        $mail->setFrom($user, 'PMB Master Data Sekolah');
        $mail->addAddress($toEmail, $toName);

        // Konten Email (HTML)
        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi Pendaftaran Akun PMB - ' . $otpCode;
        
        // Template Email HTML Responsif
        $mail->Body    = "
            <div style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;\">
                <div style=\"text-align: center; margin-bottom: 25px;\">
                    <h2 style=\"color: #4f46e5; margin: 0; font-size: 24px; font-weight: 700;\">Master Data Sekolah</h2>
                    <p style=\"color: #64748b; font-size: 14px; margin: 5px 0 0 0;\">Penerimaan Murid Baru (PMB)</p>
                </div>
                <hr style=\"border: 0; border-top: 1px solid #f1f5f9; margin: 20px 0;\">
                <p style=\"font-size: 15px; color: #334155; line-height: 1.5;\">Halo <strong>" . htmlspecialchars($toName) . "</strong>,</p>
                <p style=\"font-size: 15px; color: #334155; line-height: 1.5;\">Terima kasih telah melakukan pendaftaran akun Wali Murid di portal PMB. Untuk mengaktifkan akun Anda, silakan masukkan 6-digit kode verifikasi (OTP) berikut:</p>
                
                <div style=\"text-align: center; margin: 30px 0;\">
                    <span style=\"font-family: monospace; font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #4f46e5; background-color: #f8fafc; padding: 12px 24px; border-radius: 12px; border: 1px dashed #cbd5e1; display: inline-block;\">" . $otpCode . "</span>
                </div>
                
                <p style=\"color: #ef4444; font-size: 13px; font-weight: 500;\">* Kode verifikasi ini berlaku sementara dan bersifat rahasia. Jangan bagikan kode ini kepada siapa pun.</p>
                <hr style=\"border: 0; border-top: 1px solid #f1f5f9; margin: 20px 0;\">
                <p style=\"font-size: 11px; color: #94a3b8; text-align: center; margin: 0;\">Email ini dikirim secara otomatis oleh sistem PMB Master Data Sekolah. Mohon untuk tidak membalas email ini.</p>
            </div>
        ";

        $mail->AltBody = "Halo " . $toName . ",\n\nKode verifikasi pendaftaran akun PMB Anda adalah: " . $otpCode . "\n\nTerima kasih.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Gagal mengirim email verifikasi ke $toEmail: " . $mail->ErrorInfo);
        return false;
    }
}
