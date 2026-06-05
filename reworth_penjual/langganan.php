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

function formatTanggal($date) {
    if (empty($date)) {
        return '-';
    }
    return date('d F Y', strtotime($date));
}

// ========== FUNGSI CEK LANGGANAN ==========
function cekStatusLangganan($supabaseUrl, $supabaseKey, $userId) {
    $getLangganan = curlRequest(
        $supabaseUrl . "/rest/v1/langganan?id_penjual=eq.$userId&order=created_at.desc",
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );
    
    $allData = json_decode($getLangganan['response'], true);
    $langgananList = is_array($allData) ? $allData : [];
    
    $result = [
        'status' => 'tidak_ada',
        'warning_message' => '',
        'warning_type' => '',
        'tanggal_mulai' => null,
        'tanggal_selesai' => null,
        'hari_tersisa' => 0
    ];
    
    $today = date('Y-m-d');
    
    foreach ($langgananList as $l) {
        if ($l['status'] === 'aktif') {
            $tanggalSelesai = $l['tanggal_selesai'];
            
            if ($tanggalSelesai >= $today) {
                $sekarang = new DateTime();
                $tSelesai = new DateTime($tanggalSelesai);
                $hariTersisa = $sekarang->diff($tSelesai)->days;
                
                $result['status'] = 'aktif';
                $result['tanggal_mulai'] = $l['tanggal_mulai'];
                $result['tanggal_selesai'] = $tanggalSelesai;
                $result['hari_tersisa'] = $hariTersisa;
                
                if ($hariTersisa <= 7 && $hariTersisa > 3) {
                    $result['warning_message'] = "⏰ Peringatan! Langganan Anda akan berakhir dalam <strong>$hariTersisa hari</strong> (tanggal " . date('d F Y', strtotime($tanggalSelesai)) . "). Segera perpanjang!";
                    $result['warning_type'] = "warning";
                } elseif ($hariTersisa <= 3 && $hariTersisa > 1) {
                    $result['warning_message'] = "⚠️ Peringatan Keras! Langganan akan berakhir dalam <strong>$hariTersisa hari</strong>! Segera perpanjang sebelum layanan terputus.";
                    $result['warning_type'] = "warning-orange";
                } elseif ($hariTersisa == 1) {
                    $result['warning_message'] = "🔥 PERINGATAN! Langganan Anda akan berakhir <strong>BESOK</strong>! Perpanjang sekarang juga!";
                    $result['warning_type'] = "danger";
                }
                break;
            } else {
                $result['status'] = 'expired';
                $result['warning_message'] = "❌ Langganan Anda telah berakhir! Segera lakukan perpanjangan agar layanan Anda tidak terputus.";
                $result['warning_type'] = "danger";
                break;
            }
        }
    }
    
    if (empty($langgananList)) {
        $result['status'] = 'tidak_ada';
        $result['warning_message'] = "💡 Anda belum memiliki langganan. Segera berlangganan untuk menikmati fitur premium!";
        $result['warning_type'] = "info";
    }
    
    return $result;
}

// ========== CEK STATUS LANGGANAN ==========
$langgananStatus = cekStatusLangganan($supabaseUrl, $supabaseKey, $userId);
$warningMessage = $langgananStatus['warning_message'];
$warningType = $langgananStatus['warning_type'];
$isExpired = ($langgananStatus['status'] == 'expired');
$tanggalMulai = $langgananStatus['tanggal_mulai'];
$tanggalSelesai = $langgananStatus['tanggal_selesai'];
$hariTersisa = $langgananStatus['hari_tersisa'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langganan - ReWorth</title>
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
            <!-- <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div> -->
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Langganan</h1>
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

        <div class="content-area">
            <?php if ($warningMessage): ?>
                <br>
            <div class="alert alert-<?= $warningType ?> mb-4">
                <div class="d-flex gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span><?= $warningMessage ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Langganan dengan layout baru -->
            <div class="card-custom card-with-accent langganan-card">
                <div class="card-accent"></div>
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Status Langganan</h5>
                    
                    <div class="d-flex justify-content-between">
                        <!-- Bagian Kiri: Tanggal dan Status (atas ke bawah) -->
                        <div class="flex-grow-1">
<div class="mb-4">
    <div class="text-muted small text-uppercase">TANGGAL MULAI</div>
    <div class="fw-bold fs-5"><?= !$isExpired ? formatTanggal($tanggalMulai) : '-' ?></div>
</div>
<div class="mb-4">
    <div class="text-muted small text-uppercase">TANGGAL SELESAI</div>
    <div class="fw-bold fs-5"><?= !$isExpired ? formatTanggal($tanggalSelesai) : '-' ?></div>
</div>
                            <div>
                                <div class="text-muted small text-uppercase">STATUS</div>
                                <div>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-danger">Kadaluarsa</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Bagian Kanan: Tombol (atas ke bawah) -->
                        <div class="d-flex flex-column gap-3" style="min-width: 200px;">
                            <a href="langganan_riwayat.php" class="btn btn-outline-secondary w-100">
                                LIHAT RIWAYAT PEMBAYARAN
                            </a>
                            <a href="langganan_perpanjang.php" class="btn btn-success w-100">
                                PERPANJANG LANGGANAN
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Paket -->
            <div class="card-custom mt-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Paket Langganan</h5>
                    <div class="alert alert-info mb-0">
                        <strong>Paket Premium:</strong> 3 bulan <br>
                        <strong>Harga:</strong> Rp 70.000 / 3 bulan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>