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

require_once 'subscription_check.php';

$notifikasiHari = $_SESSION['notifikasi_hari'] ?? 7;

$subscription = getSubscriptionStatus($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);
$isPremium = $subscription['is_premium'];
$remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);
$tanggalSelesai = $subscription['current_subscription']['tanggal_selesai'] ?? '';

$warning = '';
$warningType = '';

$warning = '';
$warningType = '';

if ($isPremium && $remainingDays > 0) {
    if ($remainingDays <= $notifikasiHari && $remainingDays > 3) {
        $warning = "Peringatan! Langganan Anda akan berakhir dalam <strong>$remainingDays hari</strong> (tanggal " . date('d F Y', strtotime($tanggalSelesai)) . "). Segera perpanjang!";
        $warningType = "warning";
    } elseif ($remainingDays <= 3 && $remainingDays > 1) {
        $warning = "Peringatan Keras! Langganan akan berakhir dalam <strong>$remainingDays hari</strong>! Segera perpanjang sebelum layanan terputus.";
        $warningType = "warning-orange";
    } elseif ($remainingDays == 1) {
        $warning = "PERINGATAN! Langganan Anda akan berakhir <strong>BESOK</strong>! Perpanjang sekarang juga!";
        $warningType = "danger";
    }
} elseif ($isPremium && $remainingDays == 0) {
    $warning = "Langganan Anda telah berakhir pada " . date('d F Y', strtotime($tanggalSelesai)) . "! Segera lakukan perpanjangan agar layanan Anda tidak terputus.";
    $warningType = "danger";
} elseif (!$subscription['has_any_subscription']) {
    $warning = "Anda belum memiliki langganan. Segera berlangganan untuk menikmati fitur premium!";
    $warningType = "info";
}

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

function fmtRp($n) { 
    return 'Rp ' . number_format((float)$n, 0, ',', '.'); 
}

function fmtNum($n) { 
    return number_format((int)$n, 0, ',', '.'); 
}

$getProduk = curlRequest(
    $supabaseUrl . "/rest/v1/produk?id_penjual=eq.$userId",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$produkList = json_decode($getProduk['response'], true) ?? [];
$totalProduk = count($produkList);

$getPesanan = curlRequest(
    $supabaseUrl . "/rest/v1/pesanan?select=*,produk(*)&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$semuaPesanan = json_decode($getPesanan['response'], true) ?? [];

$pesananList = [];
$pesananMenunggu = 0;
$totalPendapatan = 0;

foreach ($semuaPesanan as $p) {
    if ($p['produk'] && $p['produk']['id_penjual'] == $userId) {
        $pesananList[] = $p;
        if ($p['status'] == 'menunggu_konfirmasi' || $p['status'] == 'menunggu') {
            $pesananMenunggu++;
        }
        if ($p['status'] == 'dikirim' || $p['status'] == 'selesai') {
            $totalPendapatan += $p['total_bayar'];
        }
    }
}
$totalPesanan = count($pesananList);

$produkTerjual = [];
foreach ($pesananList as $p) {
    if ($p['status'] == 'dikirim' || $p['status'] == 'selesai') {
        $namaProduk = $p['produk']['nama_produk'];
        if (!isset($produkTerjual[$namaProduk])) {
            $produkTerjual[$namaProduk] = 0;
        }
        $produkTerjual[$namaProduk] += $p['total_bayar'];
    }
}
arsort($produkTerjual);

$semuaNamaProduk = array_column($produkList, 'nama_produk');
$dataPenjualan = [];
foreach ($semuaNamaProduk as $nama) {
    $dataPenjualan[$nama] = $produkTerjual[$nama] ?? 0;
}

$labels = array_keys($dataPenjualan);
$data = array_values($dataPenjualan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ReWorth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom active"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="pembayaran_komisi.php" class="nav-link-custom"><i class="bi bi-cash-coin"></i><span>Pembayaran Komisi</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Dashboard</h1>
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
                <div class="info-cards">
                    <div class="info-item">
                        <div class="info-icon blue"><i class="bi bi-box-seam-fill"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtNum($totalProduk) ?></div>
                            <div class="info-label">Total Produk</div>
                        </div>
                    </div>
                    <div class="separator"></div>
                    <div class="info-item">
                        <div class="info-icon green"><i class="bi bi-bag-check-fill"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtNum($totalPesanan) ?></div>
                            <div class="info-label">Total Pesanan</div>
                        </div>
                    </div>
                    <div class="separator"></div>
                    <div class="info-item">
                        <div class="info-icon orange"><i class="bi bi-cash-stack"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtRp($totalPendapatan) ?></div>
                            <div class="info-label">Total Pendapatan</div>
                        </div>
                    </div>
                    <div class="separator"></div>
                    <div class="info-item">
                        <div class="info-icon red"><i class="bi bi-clock-history"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtNum($pesananMenunggu) ?></div>
                            <div class="info-label">Menunggu Konfirmasi</div>
                        </div>
                    </div>
                </div>
                
                <?php if ($pesananMenunggu > 0): ?>
                    <button class="btn-notif" onclick="window.location.href='pesanan.php?status=menunggu'">
                        <i class="bi bi-check2-circle"></i> Verifikasi Pesanan
                    </button>
                    <?php else: ?>
                        <button class="btn-notif" style="background:#E8F5E9; color:#2E7D32;" disabled>
                            <i class="bi bi-check-circle-fill"></i> Semua Terverifikasi
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                
                <div class="content-area">
                    <?php if ($warning): ?>
                    <div class="alert alert-<?= $warningType == 'warning-orange' ? 'warning' : $warningType ?> alert-dismissible fade show m-3" role="alert" style="<?= $warningType == 'warning-orange' ? 'background:#FFF3E0; border-color:#FFB74D; color:#E65100;' : '' ?>">
                        <div class="d-flex gap-2 align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <span><?= $warning ?></span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                    <div class="card-dashboard">
                        <div class="card-title">
                            <i class="bi bi-compass"></i> Quick Access
                        </div>
                        <div class="quick-access">
                            <a href="produk.php" class="quick-btn">
                                <i class="bi bi-box-seam-fill"></i>
                                <span>Produk</span>
                            </a>
                            <a href="pesanan.php" class="quick-btn">
                                <i class="bi bi-bag-check-fill"></i>
                                <span>Pesanan</span>
                            </a>
                            <a href="langganan.php" class="quick-btn">
                                <i class="bi bi-stars"></i>
                                <span>Langganan</span>
                            </a>
                            <a href="laporan_keuangan.php" class="quick-btn">
                                <i class="bi bi-bar-chart-line-fill"></i>
                                <span>Laporan</span>
                            </a>
                            <a href="pengaturan_toko.php" class="quick-btn">
                                <i class="bi bi-shop-window"></i>
                                <span>Pengaturan Toko</span>
                            </a>
                            <a href="pengaturan_premium.php" class="quick-btn">
                                <i class="bi bi-gem"></i>
                                <span>Pengaturan Premium</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 mb-4">
                    <div class="card-dashboard">
                        <div class="card-title">
                            <i class="bi bi-graph-up"></i> Produk Terpopuler
                        </div>
                        <?php if (!empty($produkList)): ?>
                            <canvas id="topProductsChart" height="200"></canvas>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">Belum ada produk</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($produkList)): ?>
    <script>
        new Chart(document.getElementById('topProductsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Total Pendapatan (Rp)',
                    data: <?= json_encode($data) ?>,
                    backgroundColor: '#00916E',
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { 
                        callbacks: { 
                            label: (c) => 'Rp ' + c.parsed.y.toLocaleString('id-ID') 
                        } 
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { 
                            callback: (v) => 'Rp ' + v.toLocaleString('id-ID')
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>