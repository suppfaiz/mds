<?php
$path_prefix = '../';
$page_title = 'Manajemen User';
$active_menu = 'users';

require_once $path_prefix . 'config/db.php';
require_once $path_prefix . 'includes/auth_check.php';

// Protect the page - Only Super Admin can access
checkRole(['super_admin']);

try {
    $stmt = $pdo->query("SELECT id, username, role, nama_lengkap, created_at FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Gagal memuat data user: ' . $e->getMessage();
    $users = [];
}

include $path_prefix . 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Daftar Pengguna Sistem</h4>
    <a href="create.php" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-person-plus"></i> Tambah User Baru
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3" style="width: 80px;">No</th>
                        <th class="py-3">Nama Lengkap</th>
                        <th class="py-3">Username</th>
                        <th class="py-3">Role / Hak Akses</th>
                        <th class="py-3">Tanggal Terdaftar</th>
                        <th class="px-4 py-3 text-end" style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada user terdaftar.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($users as $user): 
                        ?>
                            <tr>
                                <td class="px-4"><?php echo $no++; ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                                <td>
                                    <span class="badge bg-secondary text-capitalize">
                                        <?php 
                                        $role_labels = [
                                            'super_admin' => 'Super Admin',
                                            'operator' => 'Operator',
                                            'guru' => 'Guru',
                                            'kepala_sekolah' => 'Kepala Sekolah'
                                        ];
                                        echo $role_labels[$user['role']] ?? $user['role']; 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit User">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="delete.php?id=<?php echo $user['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus user ini?')" title="Hapus User">
                                                <i class="bi bi-trash"></i> Hapus
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="Anda tidak bisa menghapus diri sendiri">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include $path_prefix . 'includes/footer.php'; ?>
