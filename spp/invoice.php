<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';

$payment = null;
$settings = null;
$error = '';

if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
    $error = 'Tautan tagihan tidak valid atau token tidak ditemukan.';
} else {
    $token = trim($_GET['token']);
    try {
        // Fetch payment details based on token
        $stmt = $pdo->prepare("
            SELECT s.*, sw.nama AS nama_siswa, sw.nis, sw.nisn, k.nama_kelas
            FROM spp_pembayaran s
            JOIN siswa sw ON s.siswa_id = sw.id
            LEFT JOIN kelas k ON sw.kelas_id = k.id
            WHERE s.invoice_token = ?
        ");
        $stmt->execute([$token]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            $error = 'Tagihan atau kuitansi pembayaran tidak ditemukan.';
        } else {
            // Fetch school settings for headers & bank details
            $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
        }
    } catch (PDOException $e) {
        $error = 'Kesalahan database: ' . $e->getMessage();
    }
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan SPP Online - <?php echo $payment ? htmlspecialchars($payment['nama_siswa']) : 'Detail Tagihan'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --success-color: #10b981;
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --warning-color: #f59e0b;
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --card-shadow: 0 20px 40px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        
        body {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%), 
                radial-gradient(at 50% 0%, rgba(16, 185, 129, 0.04) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(99, 102, 241, 0.03) 0px, transparent 50%);
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .invoice-container {
            animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .invoice-card {
            background-color: #ffffff;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
            position: relative;
        }

        .school-header {
            border-bottom: 2px dashed #cbd5e1;
            padding: 30px;
            background-color: #fafafa;
        }

        .receipt-cut {
            position: relative;
        }
        .receipt-cut::before, .receipt-cut::after {
            content: '';
            position: absolute;
            bottom: -10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #f8fafc;
            z-index: 10;
        }
        .receipt-cut::before { left: -10px; }
        .receipt-cut::after { right: -10px; }

        .status-badge {
            font-size: 12px;
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .instruction-box {
            background-color: #fafbff;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px 12px 12px 4px;
        }

        .copy-btn {
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f1f5f9;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
        }
        .copy-btn:hover {
            background: #e2e8f0;
            color: var(--primary-color) !important;
        }

        .amount-display {
            font-size: 2.25rem;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.5px;
        }

        .stamp-box {
            position: absolute;
            top: 25px;
            right: 30px;
            border: 3px double;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 8px;
            transform: rotate(-10deg);
            opacity: 0.85;
            letter-spacing: 1.5px;
            font-family: 'Courier New', Courier, monospace;
            pointer-events: none;
            z-index: 5;
        }
        .stamp-lunas {
            border-color: #10b981;
            color: #10b981;
            background-color: rgba(16, 185, 129, 0.05);
        }
        .stamp-belum {
            border-color: #f59e0b;
            color: #f59e0b;
            background-color: rgba(245, 158, 11, 0.05);
        }

        @media (max-width: 576px) {
            .amount-display {
                font-size: 1.75rem;
            }
            .stamp-box {
                position: relative;
                top: 0;
                right: 0;
                display: inline-block;
                margin-top: 10px;
                transform: none;
            }
        }

        @media print {
            body {
                background: #ffffff !important;
            }
            .invoice-card {
                border: none !important;
                box-shadow: none !important;
            }
            .btn, .copy-btn {
                display: none !important;
            }
            .stamp-box {
                top: 10px !important;
                right: 10px !important;
                opacity: 1 !important;
            }
        }
    </style>
</head>
<body class="py-4 py-md-5">

<div class="container invoice-container" style="max-width: 600px;">
    <?php if (!empty($error)): ?>
        <div class="card invoice-card border-0 text-center p-5 shadow">
            <div class="card-body">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-3"></i>
                <h4 class="fw-bold mb-2">Akses Gagal</h4>
                <p class="text-muted mb-4"><?php echo htmlspecialchars($error); ?></p>
                <div class="d-grid col-sm-6 mx-auto">
                    <button class="btn btn-secondary fw-bold btn-sm" onclick="window.close()"><i class="bi bi-x-circle"></i> Tutup Halaman</button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card invoice-card border-0 mb-4">
            
            <!-- Dynamic Rubber Ink Stamp -->
            <?php if ($payment['status_bayar'] === 'Lunas'): ?>
                <div class="stamp-box stamp-lunas">LUNAS / PAID</div>
            <?php else: ?>
                <div class="stamp-box stamp-belum">UNPAID / TAGIHAN</div>
            <?php endif; ?>

            <!-- Header School Kop -->
            <div class="school-header receipt-cut">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
                        <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="width: 55px; height: 55px; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;">
                            <i class="bi bi-mortarboard fs-3"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h6 class="fw-bold mb-0 text-uppercase text-dark-emphasis" style="letter-spacing: 0.5px;"><?php echo htmlspecialchars($settings['nama_sekolah']); ?></h6>
                        <p class="mb-0 text-muted small" style="font-size: 10.5px; line-height: 1.4;">
                            <?php echo htmlspecialchars($settings['alamat_sekolah']); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 p-md-5 pt-3">
                <!-- Status & Invoice ID -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="text-muted small d-block">No. Tagihan SPP</span>
                        <span class="fw-bold text-secondary font-monospace" style="font-size: 13px;">#SPP-<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div>
                        <?php if ($payment['status_bayar'] === 'Lunas'): ?>
                            <span class="badge bg-success-subtle text-success status-badge"><i class="bi bi-check-circle-fill me-1"></i> Lunas</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis status-badge"><i class="bi bi-clock-fill me-1"></i> Menunggu Pembayaran</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Main Bill display -->
                <div class="text-center bg-light p-4 rounded-4 mb-4 border border-light-subtle">
                    <span class="text-muted small text-uppercase fw-semibold d-block mb-1" style="font-size: 10.5px; letter-spacing: 1px;">Jumlah yang Ditagihkan</span>
                    <h2 class="amount-display mb-1">Rp <?php echo number_format($payment['jumlah_bayar'], 0, ',', '.'); ?></h2>
                    <span class="text-muted small d-block">Pembayaran Bulan: <strong><?php echo $month_names[$payment['bulan']] . ' ' . $payment['tahun']; ?></strong></span>
                </div>

                <!-- Student Info Section -->
                <h6 class="fw-bold text-dark-emphasis mb-3 small text-uppercase" style="letter-spacing: 0.5px;"><i class="bi bi-person-circle me-1 text-primary"></i> Identitas Siswa</h6>
                <div class="table-responsive mb-4 bg-light rounded-3 p-3 border border-light-subtle">
                    <table class="table table-sm table-borderless mb-0 small" style="font-size: 12px;">
                        <tr>
                            <td class="text-muted py-1" style="width: 120px;">Nama Siswa</td>
                            <td class="py-1" style="width: 10px;">:</td>
                            <td class="fw-bold text-dark-emphasis py-1"><?php echo htmlspecialchars($payment['nama_siswa']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">NIS / NISN</td>
                            <td class="py-1">:</td>
                            <td class="fw-semibold py-1"><?php echo htmlspecialchars($payment['nis']); ?> / <?php echo htmlspecialchars($payment['nisn']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">Kelas / Room</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><span class="badge bg-primary-subtle text-primary" style="font-size: 10px;"><?php echo htmlspecialchars($payment['nama_kelas'] ?? 'Belum Diatur'); ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">Tanggal Log</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?php echo date('d F Y', strtotime($payment['tanggal_bayar'])); ?></td>
                        </tr>
                        <?php if (!empty($payment['catatan'])): ?>
                            <tr>
                                <td class="text-muted py-1">Catatan Staf</td>
                                <td class="py-1">:</td>
                                <td class="fst-italic py-1 text-secondary"><?php echo htmlspecialchars($payment['catatan']); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Payment Instructions -->
                <?php if ($payment['status_bayar'] !== 'Lunas'): ?>
                    <div class="instruction-box p-3 mb-4 border border-light-subtle">
                        <h6 class="fw-bold text-dark-emphasis mb-2 small"><i class="bi bi-bank me-1 text-primary"></i> Rekening Transfer Pembayaran</h6>
                        <p class="small text-muted mb-3" style="font-size: 11px;">Silakan selesaikan pembayaran SPP melalui transfer ke rekening sekolah berikut:</p>
                        
                        <div class="d-flex flex-column gap-2 small bg-white p-3 rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Nama Bank</span>
                                <strong class="text-dark-emphasis"><?php echo htmlspecialchars($settings['nama_bank'] ?? '-'); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Nama Rekening</span>
                                <strong class="text-dark-emphasis"><?php echo htmlspecialchars($settings['nama_rekening'] ?? '-'); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                <span class="text-muted">Nomor Rekening</span>
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="text-primary fs-6 font-monospace" id="accountNo"><?php echo htmlspecialchars($settings['nomor_rekening'] ?? '-'); ?></strong>
                                    <button class="text-muted copy-btn d-flex align-items-center gap-1 text-decoration-none small" onclick="copyAccount()" title="Salin Rekening">
                                        <i class="bi bi-copy" id="copyIcon" style="font-size: 11px;"></i> <span style="font-size: 10px;" id="copyTextSpan">Salin</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success p-3 rounded-3 mb-4 border-success border-opacity-10 bg-success bg-opacity-10 text-success-emphasis">
                        <div class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-1 small text-success">Pembayaran Sukses</h6>
                                <p class="small mb-0 text-secondary" style="font-size: 11px; line-height: 1.4;">
                                    Pembayaran SPP untuk bulan <?php echo $month_names[$payment['bulan']] . ' ' . $payment['tahun']; ?> telah lunas diterima oleh bendahara sekolah. Simpan bukti digital ini sebagai tanda terima sah Anda.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Barcode Mockup for visual authenticity -->
                <div class="d-flex flex-column align-items-center mt-4 pt-3 border-top border-light-subtle">
                    <div class="barcode mb-1" style="height: 35px; width: 220px; background: repeating-linear-gradient(90deg, #1e293b, #1e293b 2px, transparent 2px, transparent 6px, #1e293b 6px, #1e293b 8px, transparent 8px, transparent 12px, #1e293b 12px, #1e293b 16px, transparent 16px, transparent 18px); opacity: 0.7;"></div>
                    <span class="text-muted font-monospace" style="font-size: 9.5px;">*TOKEN-<?php echo strtoupper(substr($payment['invoice_token'] ?? 'MDSINVOICE', 0, 10)); ?>*</span>
                </div>

                <div class="text-center mt-4 border-top pt-4">
                    <p class="text-muted mb-0" style="font-size: 11px;">Butuh bantuan? Hubungi WhatsApp kami di <strong><?php echo htmlspecialchars($settings['no_telp']); ?></strong> atau email <strong><?php echo htmlspecialchars($settings['email_sekolah']); ?></strong></p>
                </div>
            </div>
        </div>
        
        <div class="text-center mb-5">
            <button class="btn btn-sm btn-light border shadow-sm fw-bold px-3 py-2 text-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak Tanda Terima
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
function copyAccount() {
    const copyText = document.getElementById("accountNo").innerText;
    const icon = document.getElementById("copyIcon");
    const textSpan = document.getElementById("copyTextSpan");
    
    navigator.clipboard.writeText(copyText).then(() => {
        // Change icon and text
        icon.className = "bi bi-check2 text-success";
        textSpan.innerText = "Tersalin";
        textSpan.classList.add("text-success");
        
        setTimeout(() => {
            icon.className = "bi bi-copy";
            textSpan.innerText = "Salin";
            textSpan.classList.remove("text-success");
        }, 2000);
    }).catch(err => {
        console.error("Gagal menyalin text: ", err);
    });
}
</script>
</body>
</html>
