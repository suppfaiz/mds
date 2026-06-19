<?php
$path_prefix = '../';
$page_title = 'Tambah Payroll / Gaji';
$active_menu = 'payroll';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';
require_once $path_prefix . 'includes/audit.php';

// Protect page
checkRole(['super_admin', 'operator']);

$error = '';
$success = '';

// Fetch all teachers and employees for dynamic selection
try {
    $teachers = $pdo->query("SELECT id, nama, jabatan FROM guru ORDER BY nama ASC")->fetchAll();
    $employees = $pdo->query("SELECT id, nama, jabatan FROM karyawan ORDER BY nama ASC")->fetchAll();
} catch (PDOException $e) {
    die("Gagal memuat database penerima: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $tipe_penerima = $_POST['tipe_penerima'] ?? '';
    $penerima_id = isset($_POST['penerima_id']) ? (int)$_POST['penerima_id'] : 0;
    $bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
    $tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
    
    $gaji_pokok = isset($_POST['gaji_pokok']) ? (float)$_POST['gaji_pokok'] : 0.0;
    $tunjangan = isset($_POST['tunjangan']) ? (float)$_POST['tunjangan'] : 0.0;
    $potongan = isset($_POST['potongan']) ? (float)$_POST['potongan'] : 0.0;
    $gaji_bersih = $gaji_pokok + $tunjangan - $potongan;
    
    $status_bayar = $_POST['status_bayar'] ?? 'Belum Dibayar';
    $tanggal_bayar = !empty($_POST['tanggal_bayar']) ? $_POST['tanggal_bayar'] : null;
    $catatan = trim($_POST['catatan'] ?? '');

    // Server-side validation
    if (!in_array($tipe_penerima, ['guru', 'karyawan']) || $penerima_id <= 0 || $bulan < 1 || $bulan > 12 || $tahun <= 0) {
        $error = 'Harap lengkapi semua data wajib.';
    } else {
        try {
            // Check uniqueness for the recipient in this period
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE tipe_penerima = ? AND penerima_id = ? AND bulan = ? AND tahun = ?");
            $check_stmt->execute([$tipe_penerima, $penerima_id, $bulan, $tahun]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $error = 'Gaji untuk penerima tersebut pada bulan/tahun terpilih sudah terdaftar.';
            } else {
                // If status is Dibayar but no payment date, set it to today
                if ($status_bayar === 'Dibayar' && $tanggal_bayar === null) {
                    $tanggal_bayar = date('Y-m-d');
                }
                if ($status_bayar === 'Belum Dibayar') {
                    $tanggal_bayar = null;
                }

                // Insert payroll record
                $stmt = $pdo->prepare("INSERT INTO payroll 
                    (tipe_penerima, penerima_id, bulan, tahun, gaji_pokok, tunjangan, potongan, gaji_bersih, status_bayar, tanggal_bayar, catatan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $tipe_penerima, $penerima_id, $bulan, $tahun, 
                    $gaji_pokok, $tunjangan, $potongan, $gaji_bersih, 
                    $status_bayar, $tanggal_bayar, $catatan
                ]);
                
                $payroll_id = $pdo->lastInsertId();
                
                // Get recipient name for log
                $p_name = 'Penerima';
                if ($tipe_penerima === 'guru') {
                    $n_stmt = $pdo->prepare("SELECT nama FROM guru WHERE id = ?");
                    $n_stmt->execute([$penerima_id]);
                    $p_name = $n_stmt->fetchColumn() ?: 'Guru';
                } else {
                    $n_stmt = $pdo->prepare("SELECT nama FROM karyawan WHERE id = ?");
                    $n_stmt->execute([$penerima_id]);
                    $p_name = $n_stmt->fetchColumn() ?: 'Karyawan';
                }

                logActivity($pdo, 'Tambah Payroll', 'Menginput gaji ' . $p_name . ' (' . $tipe_penerima . ') bulan ' . $bulan . '/' . $tahun . ', Bersih: Rp' . number_format($gaji_bersih, 0, ',', '.') . ' (ID: ' . $payroll_id . ')');
                
                $_SESSION['success_message'] = 'Gaji untuk ' . htmlspecialchars($p_name) . ' berhasil ditambahkan.';
                header("Location: index.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Kesalahan database: ' . $e->getMessage();
        }
    }
    }
}

$month_names = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <h4 class="fw-bold mb-0">Tambah Data Gaji</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form action="" method="POST" id="payrollForm">
    <?php echo csrf_field(); ?>
    <div class="row g-4">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">Rincian Komponen Gaji</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="tipe_penerima" class="form-label fw-semibold small">Tipe Penerima <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipe_penerima" name="tipe_penerima" onchange="updateRecipientList()" required>
                                <option value="" disabled selected>Pilih Tipe...</option>
                                <option value="guru">Guru (Pendidik)</option>
                                <option value="karyawan">Karyawan (Staf Non-Guru)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="penerima_id" class="form-label fw-semibold small">Nama Penerima <span class="text-danger">*</span></label>
                            <select class="form-select" id="penerima_id" name="penerima_id" onchange="autoFillBaseSalary()" disabled required>
                                <option value="" disabled selected>Pilih Penerima...</option>
                            </select>
                            <span class="text-muted small d-block mt-1" id="jabatan_penerima_label" style="font-size: 11px;"></span>
                        </div>

                        <div class="col-md-6">
                            <label for="bulan" class="form-label fw-semibold small">Bulan Gaji <span class="text-danger">*</span></label>
                            <select class="form-select" id="bulan" name="bulan" required>
                                <option value="" disabled selected>Pilih Bulan...</option>
                                <?php foreach ($month_names as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo date('m') == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="tahun" class="form-label fw-semibold small">Tahun Gaji <span class="text-danger">*</span></label>
                            <select class="form-select" id="tahun" name="tahun" required>
                                <option value="" disabled selected>Pilih Tahun...</option>
                                <?php for ($y = date('Y')-2; $y <= date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo date('Y') == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Salary components -->
                        <div class="col-md-4 mt-4">
                            <label for="gaji_pokok" class="form-label fw-semibold small text-primary">Gaji Pokok (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control fw-bold text-primary" id="gaji_pokok" name="gaji_pokok" value="0" oninput="calculateNet()" required>
                        </div>

                        <div class="col-md-4 mt-4">
                            <label for="tunjangan" class="form-label fw-semibold small text-success">Total Tunjangan (Rp)</label>
                            <input type="number" step="1" min="0" class="form-control fw-bold text-success" id="tunjangan" name="tunjangan" value="0" oninput="calculateNet()">
                        </div>

                        <div class="col-md-4 mt-4">
                            <label for="potongan" class="form-label fw-semibold small text-danger">Total Potongan (Rp)</label>
                            <input type="number" step="1" min="0" class="form-control fw-bold text-danger" id="potongan" name="potongan" value="0" oninput="calculateNet()">
                        </div>

                        <!-- Catatan -->
                        <div class="col-12 mt-4">
                            <label for="catatan" class="form-label fw-semibold small">Catatan Pembayaran / Slip</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="2" placeholder="Masukkan rincian tambahan (misal: rincian tunjangan, potongan absensi, dll)"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Column -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 mb-4 bg-light">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 text-center">
                    <h5 class="fw-bold mb-0">Total Gaji Bersih</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <!-- Net pay Display -->
                    <h2 class="fw-bold text-dark-emphasis my-3" id="gaji_bersih_display">Rp 0</h2>
                    <input type="hidden" id="gaji_bersih" name="gaji_bersih" value="0">
                    <div class="text-muted small">Pokok + Tunjangan - Potongan</div>
                </div>
            </div>

            <!-- Payment Details Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold mb-0">Status Pembayaran</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="status_bayar" class="form-label fw-semibold small">Status</label>
                        <select class="form-select" id="status_bayar" name="status_bayar" onchange="togglePayDate()" required>
                            <option value="Belum Dibayar" selected>Belum Dibayar (Pending)</option>
                            <option value="Dibayar">Dibayar (Paid)</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="tanggal_bayar_container">
                        <label for="tanggal_bayar" class="form-label fw-semibold small">Tanggal Pembayaran</label>
                        <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 mb-4 p-3 bg-light">
                <button type="submit" class="btn btn-primary w-100 fw-bold mb-2"><i class="bi bi-wallet2"></i> Simpan Data Gaji</button>
                <a href="index.php" class="btn btn-outline-secondary w-100 fw-bold">Batal</a>
            </div>
        </div>
    </div>
</form>

<script>
// JSON arrays for recipients
const teachers = <?php echo json_encode($teachers); ?>;
const employees = <?php echo json_encode($employees); ?>;

// Salary mappings based on jabatan
const salaryMap = {
    // Teachers
    'Kepala Sekolah': 5000000,
    'Wakil Kepala Sekolah': 4500000,
    'Guru Kelas': 4000000,
    'Wali Kelas': 4000000,
    'Guru Mapel': 3500000,
    'Guru Honorer': 3000000,
    // Employees
    'Staf TU (Tata Usaha)': 3000000,
    'Staf Perpustakaan': 2800000,
    'Laboran': 2800000,
    'Petugas Keamanan': 2500000,
    'Petugas Kebersihan': 2200000,
    'Supir': 2200000,
    'Lainnya': 2000000
};

function updateRecipientList() {
    const type = document.getElementById('tipe_penerima').value;
    const select = document.getElementById('penerima_id');
    const label = document.getElementById('jabatan_penerima_label');
    
    // Clear list
    select.innerHTML = '<option value="" disabled selected>Pilih Penerima...</option>';
    label.innerText = '';
    
    let list = [];
    if (type === 'guru') {
        list = teachers;
    } else if (type === 'karyawan') {
        list = employees;
    }
    
    if (list.length > 0) {
        list.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.text = item.nama;
            option.setAttribute('data-jabatan', item.jabatan);
            select.appendChild(option);
        });
        select.disabled = false;
    } else {
        select.disabled = true;
    }
    
    // Reset salary values
    document.getElementById('gaji_pokok').value = 0;
    calculateNet();
}

function autoFillBaseSalary() {
    const select = document.getElementById('penerima_id');
    const label = document.getElementById('jabatan_penerima_label');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const jabatan = selectedOption.getAttribute('data-jabatan');
        label.innerText = 'Jabatan: ' + jabatan;
        
        // Find base salary from map
        let baseSalary = 2000000; // default minimum
        for (const [key, value] of Object.entries(salaryMap)) {
            if (jabatan.toLowerCase().includes(key.toLowerCase())) {
                baseSalary = value;
                break;
            }
        }
        
        document.getElementById('gaji_pokok').value = baseSalary;
    } else {
        label.innerText = '';
        document.getElementById('gaji_pokok').value = 0;
    }
    
    calculateNet();
}

function calculateNet() {
    const pokok = parseFloat(document.getElementById('gaji_pokok').value) || 0;
    const tunjangan = parseFloat(document.getElementById('tunjangan').value) || 0;
    const potongan = parseFloat(document.getElementById('potongan').value) || 0;
    
    const bersih = pokok + tunjangan - potongan;
    
    document.getElementById('gaji_bersih').value = bersih;
    document.getElementById('gaji_bersih_display').innerText = formatRupiah(bersih);
}

function formatRupiah(angka) {
    var reverse = angka.toString().split('').reverse().join(''),
        ribuan = reverse.match(/\d{1,3}/g);
    ribuan = ribuan.join('.').split('').reverse().join('');
    return 'Rp ' + ribuan;
}

function togglePayDate() {
    const status = document.getElementById('status_bayar').value;
    const container = document.getElementById('tanggal_bayar_container');
    
    if (status === 'Dibayar') {
        container.classList.remove('d-none');
    } else {
        container.classList.add('d-none');
    }
}
</script>

<?php include $path_prefix . 'includes/footer.php'; ?>
