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

// Hapus data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
    header('Content-Type: application/json');
    $hapusId = $_POST['hapus_id'];
    
    $getFoto = curlRequest(
        $supabaseUrl . "/rest/v1/langganan?id_langganan=eq.$hapusId&select=bukti_pembayaran",
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );
    $fotoData = json_decode($getFoto['response'], true);
    
    if (!empty($fotoData) && !empty($fotoData[0]['bukti_pembayaran'])) {
        $pathFoto = $fotoData[0]['bukti_pembayaran'];
        curlRequest(
            $supabaseUrl . "/storage/v1/object/media/" . $pathFoto,
            'DELETE',
            null,
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
        );
    }
    
    $delete = curlRequest(
        $supabaseUrl . "/rest/v1/langganan?id_langganan=eq.$hapusId",
        'DELETE',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Prefer: return=minimal"]
    );
    
    if ($delete['httpCode'] == 200 || $delete['httpCode'] == 204) {
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
    exit;
}

// Ambil semua riwayat langganan
$getRiwayat = curlRequest(
    $supabaseUrl . "/rest/v1/langganan?id_penjual=eq.$userId&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$riwayatList = json_decode($getRiwayat['response'], true) ?? [];

// Search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $riwayatList = array_filter($riwayatList, function($r) use ($search) {
        return stripos(date('d M Y', strtotime($r['tanggal_mulai'])), $search) !== false ||
               stripos(date('d M Y', strtotime($r['tanggal_selesai'])), $search) !== false ||
               stripos(number_format($r['jumlah_bayar'], 0, ',', '.'), $search) !== false ||
               stripos($r['status'], $search) !== false;
    });
}

// Filter status
$filterStatus = $_GET['filter'] ?? 'semua';
if ($filterStatus !== 'semua') {
    $riwayatList = array_filter($riwayatList, function($r) use ($filterStatus) {
        if ($filterStatus == 'aktif') {
            // Aktif dan belum kadaluarsa
            return $r['status'] == 'aktif' && $r['tanggal_selesai'] >= date('Y-m-d');
        } elseif ($filterStatus == 'kadaluarsa') {
            // Status aktif tapi sudah lewat tanggal
            return $r['status'] == 'aktif' && $r['tanggal_selesai'] < date('Y-m-d');
        } else {
            return $r['status'] === $filterStatus;
        }
    });
}

// Pagination
$per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_data = count($riwayatList);
$total_pages = ceil($total_data / $per_page);
$start = ($current_page - 1) * $per_page;
$current_data = array_slice($riwayatList, $start, $per_page);
$showing_from = $total_data > 0 ? $start + 1 : 0;
$showing_to = min($start + $per_page, $total_data);

function getStatusInfo($r) {
    if ($r['status'] == 'aktif') {
        if ($r['tanggal_selesai'] < date('Y-m-d')) {
            return ['class' => 'bg-secondary', 'text' => 'Kadaluarsa'];
        } else {
            return ['class' => 'bg-success', 'text' => 'Aktif'];
        }
    } elseif ($r['status'] == 'menunggu_verifikasi') {
        return ['class' => 'bg-warning text-dark', 'text' => 'Menunggu Verifikasi'];
    } else {
        return ['class' => 'bg-secondary', 'text' => ucfirst($r['status'])];
    }
}

// Mapping status untuk tampilan filter
$statusLabel = [
    'semua' => 'Semua Status',
    'aktif' => 'Aktif',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'kadaluarsa' => 'Kadaluarsa'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Langganan - ReWorth</title>
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
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom active"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Riwayat Langganan</h1>
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
                    <input type="text" class="search-input" placeholder="Cari data..." id="searchInput" value="<?= htmlspecialchars($search) ?>">
                </div>
                <select id="filterStatus" class="form-select w-auto">
                    <option value="semua" <?= $filterStatus == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="aktif" <?= $filterStatus == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="menunggu_verifikasi" <?= $filterStatus == 'menunggu_verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                    <option value="kadaluarsa" <?= $filterStatus == 'kadaluarsa' ? 'selected' : '' ?>>Kadaluarsa</option>
                </select>
            </div>
        </div>

        <div class="content-area">
            <!-- Informasi filter dan search -->
            <?php if ($filterStatus != 'semua' || !empty($search)): ?>
            <div class="alert alert-info mb-3">
                <i class="bi bi-funnel"></i> 
                Menampilkan: 
                <strong><?= $statusLabel[$filterStatus] ?? $filterStatus ?></strong>
                <?php if (!empty($search)): ?>
                    dengan pencarian "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
                (ditemukan <strong><?= $total_data ?></strong> data)
            </div>
            <?php endif; ?>

            <div class="card-custom">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table">
                        <thead>
                            <tr><th class="text-center">No</th><th>Periode</th><th class="text-center">Jumlah Bayar</th><th class="text-center">Status</th><th class="text-center">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($current_data)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 text-muted"></i>
                                        <p class="mt-2 mb-0">
                                            <?php if (!empty($search)): ?>
                                                Tidak ada data untuk pencarian "<strong><?= htmlspecialchars($search) ?></strong>"
                                            <?php elseif ($filterStatus != 'semua'): ?>
                                                Tidak ada data dengan status "<strong><?= $statusLabel[$filterStatus] ?? $filterStatus ?></strong>"
                                            <?php else: ?>
                                                Belum ada riwayat langganan
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = $showing_from; foreach ($current_data as $r): 
                                    $status = getStatusInfo($r);
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= date('d M Y', strtotime($r['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($r['tanggal_selesai'])) ?> (3 bulan)</td>
                                    <td class="text-center">Rp <?= number_format($r['jumlah_bayar'], 0, ',', '.') ?></td>
                                    <td class="text-center"><span class="badge <?= $status['class'] ?>"><?= $status['text'] ?></span></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $r['id_langganan'] ?>')">Lihat</button>
                                            <button class="btn-aksi btn-hapus" onclick="hapusData('<?= $r['id_langganan'] ?>')">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($current_data) && $total_pages > 1): ?>
                <div class="table-footer">
                    <div class="showing-text">Showing <b><?= $showing_from ?></b> to <b><?= $showing_to ?></b> of <b><?= $total_data ?></b> entries</div>
                    <div class="pagination-custom">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page-1 ?>&filter=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" class="page-btn page-btn-text">Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&filter=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page+1 ?>&filter=<?= $filterStatus ?>&search=<?= urlencode($search) ?>" class="page-btn page-btn-text">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-title">Detail Langganan<button class="modal-close" onclick="closeModal('modalDetail')">&times;</button></div>
            <div id="detailContent"></div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal-overlay" id="modalHapus">
        <div class="modal-box" style="max-width: 400px;">
            <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
            <div class="confirm-text"><h3>Hapus Data?</h3><p>Tindakan ini tidak dapat dibatalkan.</p></div>
            <div class="modal-actions" style="justify-content:center;">
                <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
                <button class="btn-aksi btn-hapus" id="confirmHapusBtn">Ya, Hapus</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let hapusId = null;

        // Search + Filter
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                window.location.href = '?search=' + encodeURIComponent(this.value) + '&filter=<?= $filterStatus ?>';
            }
        });
        
        document.getElementById('filterStatus').addEventListener('change', function() {
            window.location.href = '?filter=' + this.value + '&search=' + encodeURIComponent(document.getElementById('searchInput').value);
        });

        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function lihatDetail(id) {
    fetch('langganan_detail.php?id=' + id)
        .then(res => res.text())
        .then(html => {
            document.getElementById('detailContent').innerHTML = html;
            openModal('modalDetail');
        });
}

        function hapusData(id) {
            hapusId = id;
            openModal('modalHapus');
        }

        document.getElementById('confirmHapusBtn').onclick = function() {
            if (!hapusId) return;
            fetch('langganan_riwayat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'hapus_id=' + encodeURIComponent(hapusId)
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
                closeModal('modalHapus');
            });
        };

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