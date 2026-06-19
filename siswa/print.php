<?php
$path_prefix = '../';
require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

checkLogin();

$siswa = null;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $siswa = $stmt->fetch();
        
        if (!$siswa) {
            die("Siswa tidak ditemukan.");
        }

        // Fetch school settings
        $settings = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
    } catch (PDOException $e) {
        die("Gagal mengambil data: " . $e->getMessage());
    }
} else {
    die("ID Siswa tidak valid.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Profil Siswa - <?php echo htmlspecialchars($siswa['nama']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            font-family: 'Times New Roman', Times, serif;
            font-size: 14px;
            color: #000000;
        }
        .print-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            border: 1px solid #dee2e6;
        }
        .header-kop {
            border-bottom: 3px double #000000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .profile-img-print {
            width: 120px;
            height: 150px;
            border: 1px solid #000000;
            object-fit: cover;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-container {
                border: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container no-print my-3 text-center">
    <button class="btn btn-primary btn-sm px-4" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Dokumen</button>
    <button class="btn btn-secondary btn-sm px-4" onclick="window.close()">Tutup</button>
</div>

<div class="print-container shadow-sm">
    <!-- Kop Surat Sekolah -->
    <div class="header-kop d-flex align-items-center gap-3 justify-content-center">
        <?php if (!empty($settings['logo']) && file_exists('../' . $settings['logo'])): ?>
            <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo Sekolah" style="width: 80px; height: 80px; object-fit: contain;">
        <?php endif; ?>
        <div class="text-center">
            <h3 class="fw-bold m-0 text-uppercase"><?php echo htmlspecialchars($settings['nama_sekolah']); ?></h3>
            <p class="m-0 small text-muted">
                <?php echo htmlspecialchars($settings['alamat_sekolah']); ?> 
                <?php echo !empty($settings['no_telp']) ? ' Telp: ' . htmlspecialchars($settings['no_telp']) : ''; ?> 
                <?php echo !empty($settings['email_sekolah']) ? ' Email: ' . htmlspecialchars($settings['email_sekolah']) : ''; ?>
                <?php echo !empty($settings['website']) ? ' Web: ' . htmlspecialchars($settings['website']) : ''; ?>
            </p>
        </div>
    </div>
    
    <div class="text-center mb-4">
        <h5 class="fw-bold text-uppercase text-decoration-underline">Biodata Profil Siswa</h5>
        <p class="small text-muted m-0">Tahun Ajaran <?php echo date('Y')-1; ?>/<?php echo date('Y'); ?></p>
    </div>
    
    <div class="row g-4 mb-4">
        <div class="col-8">
            <table class="table table-sm table-borderless align-middle">
                <tr>
                    <td style="width: 180px;">Nama Lengkap</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold"><?php echo htmlspecialchars($siswa['nama']); ?></td>
                </tr>
                <tr>
                    <td>NIS / NISN</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['nis'] . ' / ' . $siswa['nisn']); ?></td>
                </tr>
                <tr>
                    <td>Kelas</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['nama_kelas'] ?? 'Belum Diatur'); ?></td>
                </tr>
                <tr>
                    <td>Tahun Masuk</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['tahun_masuk']); ?></td>
                </tr>
                <tr>
                    <td>Jenis Kelamin</td>
                    <td>:</td>
                    <td><?php echo $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                </tr>
                <tr>
                    <td>Tempat, Tanggal Lahir</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['tempat_lahir'] . ', ' . date('d F Y', strtotime($siswa['tanggal_lahir']))); ?></td>
                </tr>
                <tr>
                    <td>Agama</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['agama']); ?></td>
                </tr>
                <tr>
                    <td>Nomor HP</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($siswa['no_hp']); ?></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['email']) ? htmlspecialchars($siswa['email']) : '-'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-4 text-center">
            <?php if (!empty($siswa['foto']) && file_exists('../' . $siswa['foto'])): ?>
                <img src="../<?php echo htmlspecialchars($siswa['foto']); ?>" alt="Foto Profil" class="profile-img-print shadow-sm">
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center bg-light text-muted border mx-auto profile-img-print" style="width: 120px; height: 150px;">
                    <span class="small">FOTO 3X4</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="fw-bold border-bottom pb-1 mb-2">Alamat Rumah</h6>
            <p><?php echo nl2br(htmlspecialchars($siswa['alamat'])); ?></p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="fw-bold border-bottom pb-1 mb-2">Informasi Orang Tua / Wali</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <td style="width: 180px;">Nama Ayah Kandung</td>
                    <td style="width: 15px;">:</td>
                    <td><?php echo !empty($siswa['nama_ayah']) ? htmlspecialchars($siswa['nama_ayah']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>NIK Ayah</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['nik_ayah']) ? htmlspecialchars($siswa['nik_ayah']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Pekerjaan Ayah</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['pekerjaan_ayah']) ? htmlspecialchars($siswa['pekerjaan_ayah']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Nama Ibu Kandung</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['nama_ibu']) ? htmlspecialchars($siswa['nama_ibu']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>NIK Ibu</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['nik_ibu']) ? htmlspecialchars($siswa['nik_ibu']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Pekerjaan Ibu</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['pekerjaan_ibu']) ? htmlspecialchars($siswa['pekerjaan_ibu']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Nomor HP Orang Tua</td>
                    <td>:</td>
                    <td><?php echo !empty($siswa['no_hp_ortu']) ? htmlspecialchars($siswa['no_hp_ortu']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Alamat Orang Tua</td>
                    <td>:</td>
                    <td>
                        <?php 
                        if (!empty($siswa['alamat_ortu'])) {
                            echo nl2br(htmlspecialchars($siswa['alamat_ortu']));
                        } else {
                            echo 'Sama dengan alamat siswa';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Tanda Tangan/Footer Print -->
    <div class="row text-center mt-5">
        <div class="col-5 offset-7 text-end pe-5">
            <p class="m-0">Kota Mandiri, <?php echo date('d M Y'); ?></p>
            <p class="mb-5">Kepala Sekolah,</p>
            <br>
            <p class="fw-bold text-decoration-underline m-0"><?php echo htmlspecialchars($settings['nama_kepsek']); ?></p>
            <p class="small text-muted m-0">NIP. <?php echo htmlspecialchars($settings['nip_kepsek']); ?></p>
        </div>
    </div>
</div>

<script>
    // Auto launch print
    window.onload = function() {
        window.print();
    }
</script>
</body>
</html>
