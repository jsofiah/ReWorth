<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

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
               stripos($p['alamat_pengiriman'] ?? '', $search) !== false;
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
                <select id="statusFilter" class="form-select w-auto">
                    <option value="semua" <?= $filterStatus == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="menunggu_pembayaran" <?= $filterStatus == 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                    <option value="diproses" <?= $filterStatus == 'diproses' ? 'selected' : '' ?>>Diproses</option>
                    <option value="dikirim" <?= $filterStatus == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                    <option value="selesai" <?= $filterStatus == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= $filterStatus == 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>

            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>Nama Pesanan</th>
                                <th>Alamat Pembeli</th>
                                <th class="text-center">Total Bayar</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($current_data)): ?>
                                <tr><td colspan="6" class="text-center py-4">Belum ada pesanan</td></tr>
                            <?php else: ?>
                                <?php $no = $showing_from; foreach ($current_data as $p): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($p['produk']['nama_produk'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(substr($p['alamat_pengiriman'], 0, 50)) ?>...</td>
                                    <td class="text-center">Rp <?= number_format($p['total_bayar'], 0, ',', '.') ?></td>
                                    <td class="text-center"><?= getStatusBadge($p['status']) ?></td>
                                    <td class="text-center">
                                        <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $p['id_pesanan'] ?>')">
                                            <i class="bi bi-eye"></i> Detail
                                        </button>
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

    <!-- Modal Detail Pesanan -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="max-width: 550px;">
            <div class="modal-title">Detail Pesanan <button class="modal-close" onclick="closeModal('modalDetail')">&times;</button></div>
            <div id="detailContent"></div>
            <div class="modal-actions mt-3">
                <button class="btn-cancel" onclick="closeModal('modalDetail')">Tutup</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        function lihatDetail(id) {
            fetch('pesanan_detail_modal.php?id=' + id)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('detailContent').innerHTML = html;
                    openModal('modalDetail');
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