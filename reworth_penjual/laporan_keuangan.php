<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['id_penjual'])) {
    header("Location: login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

require_once 'subscription_check.php';

$subscription = requirePremium($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

$isPremium = $subscription['is_premium'];
$remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

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

// Ambil parameter filter
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$labelFrom = date('d M Y', strtotime($dateFrom));
$labelTo = date('d M Y', strtotime($dateTo));

// Ambil semua produk penjual
$getProduk = curlRequest(
    $supabaseUrl . "/rest/v1/produk?id_penjual=eq.$userId",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$produkList = json_decode($getProduk['response'], true) ?? [];
$totalProduk = count($produkList);

// Ambil SEMUA pesanan (termasuk yang belum selesai) untuk filter
$getPesanan = curlRequest(
    $supabaseUrl . "/rest/v1/pesanan?select=*,produk(*)&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$semuaPesanan = json_decode($getPesanan['response'], true) ?? [];

// Filter pesanan berdasarkan produk penjual dan tanggal
$pesananList = [];
foreach ($semuaPesanan as $p) {
    // Cek apakah produk milik penjual ini
    if ($p['produk'] && $p['produk']['id_penjual'] == $userId) {
        // Filter berdasarkan tanggal (gunakan created_at atau tanggal_selesai)
        $tglPesanan = substr($p['created_at'], 0, 10);
        if ($tglPesanan >= $dateFrom && $tglPesanan <= $dateTo) {
            $pesananList[] = $p;
        }
    }
}

// Hitung total pendapatan dari SEMUA pesanan (termasuk yang belum selesai)
$totalPendapatan = 0;
foreach ($pesananList as $p) {
    $totalPendapatan += $p['total_bayar'];
}
$totalTransaksi = count($pesananList);

// Ambil komisi penjual
$getKomisi = curlRequest(
    $supabaseUrl . "/rest/v1/komisi?id_penjual=eq.$userId&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$komisiList = json_decode($getKomisi['response'], true) ?? [];

// Filter komisi berdasarkan tanggal
$komisiPeriode = [];
$totalKomisi = 0;
foreach ($komisiList as $k) {
    $tglKomisi = substr($k['created_at'], 0, 10);
    if ($tglKomisi >= $dateFrom && $tglKomisi <= $dateTo) {
        $komisiPeriode[] = $k;
        $totalKomisi += $k['total_komisi'];
    }
}

$totalBersih = $totalPendapatan - $totalKomisi;

// Hitung pendapatan per bulan (untuk grafik)
$monthlyIncome = array_fill(1, 12, 0);
foreach ($pesananList as $p) {
    $m = (int)date('n', strtotime($p['created_at']));
    $monthlyIncome[$m] += $p['total_bayar'];
}

$curMonth = (int)date('n');
$monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
$trendLabels = [];
$trendValues = [];
for ($m = 1; $m <= $curMonth; $m++) {
    $trendLabels[] = $monthNames[$m-1];
    $trendValues[] = $monthlyIncome[$m];
}

function fmtRp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function fmtNum($n) { return number_format((int)$n, 0, ',', '.'); }

// Debug: log jumlah data
error_log("Total Pesanan: " . count($pesananList));
error_log("Total Pendapatan: " . $totalPendapatan);
error_log("Date Range: $dateFrom - $dateTo");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - ReWorth</title>
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
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="pembayaran_komisi.php" class="nav-link-custom"><i class="bi bi-cash-coin"></i><span>Pembayaran Komisi</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom active"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Laporan Keuangan</h1>
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
            <form method="GET" action="" class="laporan-bar" id="filterForm" style="background:#fff; border-radius:20px; padding:16px 24px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="bi bi-calendar3" style="font-size:22px; color:var(--green);"></i>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="padding:8px 12px; border:1px solid #E5E7EB; border-radius:8px; font-family:'Poppins',sans-serif;">
                    <span>–</span>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="padding:8px 12px; border:1px solid #E5E7EB; border-radius:8px; font-family:'Poppins',sans-serif;">
                </div>
                <button type="submit" class="btn-generate" style="padding:10px 28px; border:2px solid var(--green); border-radius:12px; background:transparent; color:var(--green); font-weight:700; cursor:pointer; transition:all 0.3s;">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <button type="button" class="btn-export" onclick="window.open('laporan_keuangan_export.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>', '_blank')" style="display:flex; align-items:center; gap:8px; padding:10px 22px; border:none; border-radius:12px; background:#FFCF00; color:#1A2E24; font-weight:700; cursor:pointer; transition:all 0.3s;">
                    <i class="bi bi-file-earmark-pdf" style="font-size:18px;"></i> Export PDF
                </button>
            </form>
        </div>

        <div class="content-area">
            <!-- Info periode -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                Menampilkan data periode <strong><?= $labelFrom ?> – <?= $labelTo ?></strong>
            </div>

            <!-- Statistik -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card-custom p-3">
                        <div class="text-muted small text-uppercase">Total Produk</div>
                        <div class="fs-3 fw-bold"><?= fmtNum($totalProduk) ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card-custom p-3">
                        <div class="text-muted small text-uppercase">Total Transaksi</div>
                        <div class="fs-3 fw-bold"><?= fmtNum($totalTransaksi) ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card-custom p-3">
                        <div class="text-muted small text-uppercase">Total Pendapatan</div>
                        <div class="fs-3 fw-bold text-success"><?= fmtRp($totalPendapatan) ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card-custom p-3">
                        <div class="text-muted small text-uppercase">Total Komisi (5%)</div>
                        <div class="fs-3 fw-bold text-warning"><?= fmtRp($totalKomisi) ?></div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card-custom p-3">
                        <div class="text-muted small text-uppercase">Total Bersih</div>
                        <div class="fs-2 fw-bold text-primary"><?= fmtRp($totalBersih) ?></div>
                        <small class="text-muted">Pendapatan - Komisi</small>
                    </div>
                </div>
            </div>

            <!-- Grafik Pendapatan -->
            <div class="card-custom mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-graph-up"></i> Grafik Pendapatan per Bulan</h5>
                    <canvas id="incomeChart" height="100"></canvas>
                </div>
            </div>

            <!-- Tabel Riwayat Transaksi -->
            <div class="card-custom">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Riwayat Transaksi</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Produk</th>
                                    <th>Total Bayar</th>
                                    <th>Komisi (5%)</th>
                                    <th>Tanggal Transaksi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pesananList)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-inbox fs-1 text-muted"></i>
                                            <p class="mt-2 mb-0">Belum ada transaksi pada periode ini</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($pesananList as $p): 
                                        $komisiItem = $p['total_bayar'] * 0.05;
                                        $statusBadge = '';
                                        switch($p['status']) {
                                            case 'selesai': 
                                                $statusBadge = '<span class="status-badge status-selesai">Selesai</span>'; 
                                                break;
                                            case 'dikirim': 
                                                $statusBadge = '<span class="status-badge status-dikirim">Dikirim</span>'; 
                                                break;
                                            case 'diproses': 
                                                $statusBadge = '<span class="status-badge status-berlangsung">Diproses</span>'; 
                                                break;
                                            case 'menunggu': 
                                                $statusBadge = '<span class="status-badge status-menunggu">Menunggu Konfirmasi</span>'; 
                                                break;
                                            case 'ditolak': 
                                                $statusBadge = '<span class="status-badge status-ditolak">Ditolak</span>'; 
                                                break;
                                            default: 
                                                $statusBadge = '<span class="status-badge status-akan_datang">' . ucfirst($p['status']) . '</span>';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($p['produk']['nama_produk'] ?? '-') ?></td>
                                        <td>Rp <?= number_format($p['total_bayar'], 0, ',', '.') ?></td>
                                        <td>Rp <?= number_format($komisiItem, 0, ',', '.') ?></td>
                                        <td><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                                        <td><?= $statusBadge ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('incomeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= json_encode($trendValues) ?>,
                    backgroundColor: '#00916E',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: (c) => 'Rp ' + c.parsed.y.toLocaleString('id-ID') } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (v) => 'Rp ' + v.toLocaleString('id-ID') } }
                }
            }
        });
    </script>
</body>
</html>