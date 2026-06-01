<?php
session_start();

if (!isset($_SESSION['id_penjual'])) {
    header("Location: login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userId = $_SESSION['id_penjual'] ?? '';
$userName = $_SESSION['nama_penjual'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$userFoto = $_SESSION['foto_profil'] ?? '';

function getSupabaseImageUrl($path) {
    if (empty($path)) return null;
    return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
}

function curlRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['response' => $response, 'httpCode' => $httpCode];
}

// Ambil daftar pesanan
$getPesanan = curlRequest(
    $supabaseUrl . "/rest/v1/pesanan?select=*,produk(*)&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$semuaPesanan = json_decode($getPesanan['response'], true) ?? [];

// Filter hanya pesanan dari produk penjual ini
$pesananList = [];
foreach ($semuaPesanan as $p) {
    if ($p['produk'] && $p['produk']['id_penjual'] == $userId) {
        $pesananList[] = $p;
    }
}

// Filter status
$filterStatus = $_GET['status'] ?? 'semua';
if ($filterStatus !== 'semua') {
    $pesananList = array_filter($pesananList, function($p) use ($filterStatus) {
        return $p['status'] === $filterStatus;
    });
}

// Search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $pesananList = array_filter($pesananList, function($p) use ($search) {
        return stripos($p['produk']['nama_produk'] ?? '', $search) !== false ||
               stripos($p['alamat_pengiriman'] ?? '', $search) !== false ||
               stripos($p['nomor_resi'] ?? '', $search) !== false;
    });
}

// Pagination
$per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_data = count($pesananList);
$total_pages = ceil($total_data / $per_page);
$start = ($current_page - 1) * $per_page;
$current_data = array_slice($pesananList, $start, $per_page);
$showing_from = $total_data > 0 ? $start + 1 : 0;
$showing_to = min($start + $per_page, $total_data);

function getStatusBadge($status) {
    switch($status) {
        case 'menunggu_pembayaran': return '<span class="badge bg-warning text-dark">Menunggu Pembayaran</span>';
        case 'diproses': return '<span class="badge bg-info text-dark">Diproses</span>';
        case 'dikirim': return '<span class="badge bg-primary">Dikirim</span>';
        case 'selesai': return '<span class="badge bg-success">Selesai</span>';
        case 'dibatalkan': return '<span class="badge bg-danger">Dibatalkan</span>';
        default: return '<span class="badge bg-secondary">'.$status.'</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan - ReWorth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom active"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Manajemen Pesanan</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar-wrap">
            <div class="action-bar">
                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" placeholder="Cari pesanan..." id="searchInput" value="<?= htmlspecialchars($search) ?>">
                </div>
                <select id="statusFilter" class="form-select w-auto" style="width: auto;">
                    <option value="semua" <?= $filterStatus == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="menunggu_pembayaran" <?= $filterStatus == 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                    <option value="diproses" <?= $filterStatus == 'diproses' ? 'selected' : '' ?>>Diproses</option>
                    <option value="dikirim" <?= $filterStatus == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                    <option value="selesai" <?= $filterStatus == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= $filterStatus == 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
                <a href="pesanan_tambah.php" class="btn-tambah">
                    <i class="bi bi-plus-lg"></i> Tambah Pesanan
                </a>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table">
                        <thead>
                            <tr><th>No</th><th>ID Pesanan</th><th>Produk</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($current_data)): ?>
                                <tr><td colspan="7" class="text-center py-4">Belum ada pesanan</td></tr>
                            <?php else: ?>
                                <?php $no = $showing_from; foreach ($current_data as $p): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><small><?= substr($p['id_pesanan'], 0, 8) ?>...</small></td>
                                    <td><?= htmlspecialchars($p['produk']['nama_produk'] ?? '-') ?></td>
                                    <td>Rp <?= number_format($p['total_bayar'], 0, ',', '.') ?></td>
                                    <td><?= getStatusBadge($p['status']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="pesanan_detail.php?id=<?= $p['id_pesanan'] ?>" class="btn-aksi btn-lihat"><i class="bi bi-eye"></i> Detail</a>
                                            
                                            <?php if ($p['status'] == 'menunggu_pembayaran'): ?>
                                                <button class="btn-aksi btn-edit" onclick="updateStatus('<?= $p['id_pesanan'] ?>', 'diproses')"><i class="bi bi-check-lg"></i> Proses</button>
                                                <button class="btn-aksi btn-hapus" onclick="updateStatus('<?= $p['id_pesanan'] ?>', 'dibatalkan')"><i class="bi bi-x-lg"></i> Batal</button>
                                            
                                            <?php elseif ($p['status'] == 'diproses'): ?>
                                                <button class="btn-aksi btn-edit" onclick="openResiModal('<?= $p['id_pesanan'] ?>')"><i class="bi bi-truck"></i> Kirim</button>
                                            
                                            <?php elseif ($p['status'] == 'dikirim'): ?>
                                                <button class="btn-aksi btn-success" onclick="updateStatus('<?= $p['id_pesanan'] ?>', 'selesai')"><i class="bi bi-check-circle"></i> Selesai</button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-aksi btn-hapus" onclick="hapusPesanan('<?= $p['id_pesanan'] ?>')"><i class="bi bi-trash"></i> Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <div class="showing-text">Showing <b><?= $showing_from ?></b> to <b><?= $showing_to ?></b> of <b><?= $total_data ?></b> entries</div>
                    <div class="pagination-custom">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page-1 ?>&status=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" class="page-btn page-btn-text">Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page+1 ?>&status=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" class="page-btn page-btn-text">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input Resi -->
    <div class="modal-overlay" id="modalResi">
        <div class="modal-box" style="max-width: 450px;">
            <div class="modal-title">Input Nomor Resi<button class="modal-close" onclick="closeModal('modalResi')">&times;</button></div>
            <div class="form-group">
                <label class="form-label">Nomor Resi Pengiriman</label>
                <input type="text" id="resiNumber" class="form-control-custom" placeholder="Masukkan nomor resi">
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('modalResi')">Batal</button>
                <button class="btn-save" onclick="submitResi()">Kirim</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPesananId = null;

        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        document.getElementById('statusFilter').addEventListener('change', function() {
            window.location.href = '?status=' + this.value + '&search=' + encodeURIComponent(document.getElementById('searchInput').value);
        });

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                window.location.href = '?status=<?= $filterStatus ?>&search=' + encodeURIComponent(this.value);
            }
        });

        function updateStatus(id, status) {
            if (!confirm('Ubah status pesanan ini?')) return;
            fetch('pesanan_update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_pesanan=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status)
            }).then(res => res.json()).then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
        }

        function openResiModal(id) {
            currentPesananId = id;
            openModal('modalResi');
        }

        function submitResi() {
            let resi = document.getElementById('resiNumber').value;
            if (!resi) { showToast('Masukkan nomor resi', 'error'); return; }
            fetch('pesanan_update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_pesanan=' + encodeURIComponent(currentPesananId) + '&status=dikirim&nomor_resi=' + encodeURIComponent(resi)
            }).then(res => res.json()).then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                closeModal('modalResi');
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
        }

        function hapusPesanan(id) {
            if (!confirm('Yakin hapus pesanan ini?')) return;
            fetch('pesanan_hapus.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            }).then(res => res.json()).then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
        }

        function showToast(msg, type) {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
    </script>
</body>
</html>