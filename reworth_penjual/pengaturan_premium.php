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

// Ambil data langganan aktif
$getLangganan = curlRequest(
    $supabaseUrl . "/rest/v1/langganan?id_penjual=eq.$userId&order=created_at.desc",
    'GET',
    null,
    [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]
);

$langgananList = json_decode($getLangganan['response'], true) ?? [];

// Cek langganan aktif
$langgananAktif = null;
$isPremium = false;
$tanggalMulai = '';
$tanggalSelesai = '';
$hariTersisa = 0;

foreach ($langgananList as $l) {
    if ($l['status'] === 'aktif') {
        $langgananAktif = $l;
        $isPremium = true;
        $tanggalMulai = $l['tanggal_mulai'];
        $tanggalSelesai = $l['tanggal_selesai'];
        $sekarang = new DateTime();
        $tSelesai = new DateTime($tanggalSelesai);
        $hariTersisa = $sekarang->diff($tSelesai)->days;
        break;
    }
}

// Load pengaturan dari session atau default
$autoRenewal = $_SESSION['auto_renewal'] ?? 'off';
$notifikasiHari = $_SESSION['notifikasi_hari'] ?? 7;
$metodeBayar = $_SESSION['metode_bayar'] ?? 'transfer';

// Proses simpan pengaturan
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan_pengaturan'])) {
        $autoRenewal = $_POST['auto_renewal'] ?? 'off';
        $notifikasiHari = (int)$_POST['notifikasi_hari'];
        $metodeBayar = $_POST['metode_bayar'] ?? 'transfer';
        
        $_SESSION['auto_renewal'] = $autoRenewal;
        $_SESSION['notifikasi_hari'] = $notifikasiHari;
        $_SESSION['metode_bayar'] = $metodeBayar;
        
        $message = "Pengaturan berhasil disimpan!";
        $messageType = "success";
    }
    
    if (isset($_POST['batalkan_langganan'])) {
        // Hanya jika ada langganan aktif
        if ($langgananAktif) {
            // Update status jadi tidak akan diperpanjang
            // Bisa juga update status jadi 'kadaluarsa' atau tambah field 'auto_renewal'
            $message = "Langganan akan dihentikan setelah masa aktif berakhir.";
            $messageType = "warning";
            $_SESSION['auto_renewal'] = 'off';
            $autoRenewal = 'off';
        } else {
            $message = "Tidak ada langganan aktif untuk dibatalkan.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Premium - ReWorth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom active"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout">
            <a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Pengaturan Premium</h1>
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
                <div class="flex-grow-1"></div>
                <a href="langganan.php" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-arrow-left"></i> Kembali ke Langganan
                </a>
            </div>
        </div>

        <div class="content-area">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType == 'success' ? 'success' : ($messageType == 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show mb-4">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- INFORMASI LANGGANAN SAAT INI -->
            <div class="card-custom mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-info-circle"></i> Informasi Langganan Saat Ini</h5>
                    
                    <?php if ($isPremium && $langgananAktif && $tanggalSelesai >= date('Y-m-d')): ?>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small text-uppercase">Status</div>
                                <div><span class="badge bg-success">Aktif</span></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small text-uppercase">Masa Aktif</div>
                                <div><?= date('d F Y', strtotime($tanggalMulai)) ?> - <?= date('d F Y', strtotime($tanggalSelesai)) ?></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small text-uppercase">Sisa Waktu</div>
                                <div><?= $hariTersisa ?> hari lagi</div>
                            </div>
                        </div>
                    <?php elseif ($isPremium && $langgananAktif): ?>
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-exclamation-triangle"></i> Langganan Anda telah kadaluarsa. Segera perpanjang!
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-0">
                            <i class="bi bi-info-circle"></i> Anda belum memiliki langganan premium aktif.
                            <a href="langganan.php" class="alert-link">Klik di sini</a> untuk berlangganan.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PENGATURAN PREMIUM -->
            <div class="card-custom mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-gear"></i> Pengaturan Akun Premium</h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="fw-bold mb-2">Auto-Renewal (Perpanjang Otomatis)</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="auto_renewal" value="on" id="autoRenewalOn" <?= $autoRenewal == 'on' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoRenewalOn">
                                    <i class="bi bi-check-circle-fill text-success"></i> Aktif - Perpanjang otomatis sebelum masa aktif habis
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="auto_renewal" value="off" id="autoRenewalOff" <?= $autoRenewal == 'off' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoRenewalOff">
                                    <i class="bi bi-x-circle-fill text-danger"></i> Nonaktif - Perpanjang manual setiap periode
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold mb-2">Notifikasi Pengingat</label>
                            <select name="notifikasi_hari" class="form-select w-50">
                                <option value="3" <?= $notifikasiHari == 3 ? 'selected' : '' ?>>3 hari sebelum kadaluarsa</option>
                                <option value="7" <?= $notifikasiHari == 7 ? 'selected' : '' ?>>7 hari sebelum kadaluarsa</option>
                                <option value="14" <?= $notifikasiHari == 14 ? 'selected' : '' ?>>14 hari sebelum kadaluarsa</option>
                                <option value="30" <?= $notifikasiHari == 30 ? 'selected' : '' ?>>30 hari sebelum kadaluarsa</option>
                            </select>
                            <small class="text-muted d-block">Kami akan mengirimkan pengingat via email</small>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold mb-2">Metode Pembayaran Default</label>
                            <select name="metode_bayar" class="form-select w-50">
                                <option value="transfer" <?= $metodeBayar == 'transfer' ? 'selected' : '' ?>>Transfer Bank (Manual)</option>
                                <option value="virtual_account" <?= $metodeBayar == 'virtual_account' ? 'selected' : '' ?>>Virtual Account</option>
                                <option value="ewallet" <?= $metodeBayar == 'ewallet' ? 'selected' : '' ?>>E-Wallet (Qris, OVO, Dana)</option>
                            </select>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" name="simpan_pengaturan" class="btn btn-success">
                                <i class="bi bi-save"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- BATALKAN LANGGANAN -->
            <?php if ($isPremium && $langgananAktif && $tanggalSelesai >= date('Y-m-d')): ?>
            <div class="card-custom">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 text-danger"><i class="bi bi-exclamation-triangle"></i> Batalkan Langganan</h5>
                    <p class="mb-3">Jika Anda membatalkan langganan, akun premium Anda akan tetap aktif hingga masa langganan berakhir. Setelah itu, akun Anda akan kembali ke akun biasa.</p>
                    <form method="POST" onsubmit="return confirm('Yakin ingin membatalkan langganan premium? Anda tetap bisa menggunakan fitur premium hingga masa aktif berakhir.')">
                        <button type="submit" name="batalkan_langganan" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Batalkan Langganan
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>