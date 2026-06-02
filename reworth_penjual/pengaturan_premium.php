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

// Ambil toast dari session
$toastMessage = $_SESSION['toast_message'] ?? '';
$toastType = $_SESSION['toast_type'] ?? '';
unset($_SESSION['toast_message']);
unset($_SESSION['toast_type']);

// Ambil nilai notifikasi dari session
$notifikasiHari = $_SESSION['notifikasi_hari'] ?? '';

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
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$langgananList = json_decode($getLangganan['response'], true) ?? [];

// Cek langganan aktif
$langgananAktif = null;
$isPremium = false;
$tanggalMulai = '';
$tanggalSelesai = '';
$hariTersisa = 0;

$today = date('Y-m-d');
foreach ($langgananList as $l) {
    if ($l['status'] === 'aktif' && $l['tanggal_selesai'] >= $today) {
        $isPremium = true;
        $langgananAktif = $l;
        $tanggalMulai = $l['tanggal_mulai'];
        $tanggalSelesai = $l['tanggal_selesai'];
        $sekarang = new DateTime();
        $tSelesai = new DateTime($tanggalSelesai);
        $hariTersisa = $sekarang->diff($tSelesai)->days;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan_pengaturan'])) {
        $notifikasiHari = $_POST['notifikasi_hari'] ?? '';
        
        if (empty($notifikasiHari)) {
            $_SESSION['toast_message'] = "Silakan pilih notifikasi pengingat terlebih dahulu!";
            $_SESSION['toast_type'] = "error";
        } else {
            $notifikasiHari = (int)$notifikasiHari;
            $_SESSION['notifikasi_hari'] = $notifikasiHari;
            
            $_SESSION['toast_message'] = "Pengaturan berhasil disimpan!";
            $_SESSION['toast_type'] = "success";
        }
        
        header("Location: pengaturan_premium.php");
        exit;
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
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom active"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
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

        <div class="content-area">
            <br>

            <!-- INFORMASI LANGGANAN SAAT INI -->
            <div class="card-custom mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Informasi Langganan Saat Ini</h5>
                    
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
                    <h5 class="fw-bold mb-4">Pengaturan Akun Premium</h5>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Notifikasi Pengingat</label>
                            <select name="notifikasi_hari" class="form-control-custom w-50" required>
                                <option value="" disabled>-- Pilih pengingat --</option>
                                <option value="1" <?= $notifikasiHari == 1 ? 'selected' : '' ?>>1 hari sebelum kadaluarsa</option>
                                <option value="3" <?= $notifikasiHari == 3 ? 'selected' : '' ?>>3 hari sebelum kadaluarsa</option>
                                <option value="7" <?= $notifikasiHari == 7 ? 'selected' : '' ?>>7 hari sebelum kadaluarsa</option>
                                <option value="14" <?= $notifikasiHari == 14 ? 'selected' : '' ?>>14 hari sebelum kadaluarsa</option>
                                <option value="30" <?= $notifikasiHari == 30 ? 'selected' : '' ?>>30 hari sebelum kadaluarsa</option>
                            </select>
                            <div class="text-muted small mt-1">Notifikasi peringatan akan muncul di halaman Langganan</div>
                        </div>

                        <!-- METODE PEMBAYARAN (QRIS) -->
                        <div class="form-group">
                            <label class="form-label">Metode Pembayaran</label>
                            <div class="alert alert-info">
                                <i class="bi bi-upc-scan"></i> <strong>QRIS</strong><br>
                                Lakukan pembayaran di menu <strong>Langganan → Perpanjang Langganan</strong>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="simpan_pengaturan" class="btn-submit">
                                <i class="bi bi-save"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(msg, type) {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
    </script>
    <?php if ($toastMessage): ?>
    <script>
        showToast('<?= htmlspecialchars($toastMessage) ?>', '<?= $toastType ?>');
    </script>
    <?php endif; ?>
</body>
</html>