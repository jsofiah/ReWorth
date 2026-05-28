<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $idTukar = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idTukar)) {
        echo '<div class="alert alert-danger m-3">ID Tukar Reward tidak ditemukan.</div>';
        exit;
    }

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userRole = $_SESSION['role'] ?? '';
    $userFoto = $_SESSION['foto_profil'] ?? '';
    $userId = $_SESSION['id_admin'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) {
            return null;
        }
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    function formatTanggalWaktuIndonesia($date) {
        if (empty($date)) return '-';

        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $timestamp = strtotime($date);
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);

        return "$tanggal " . $bulan[$bulanNum] . " $tahun, $jam WIB";
    }

    $url = $supabaseUrl . "/rest/v1/tukar_poin?id_tukar=eq.$idTukar&select=*,reward(*),pengguna(*)";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tukar = null;

    if ($response && $httpCode == 200) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $tukar = $data[0];
        }
    }

    if (!$tukar) {
        echo '<div class="alert alert-danger m-3">Data tukar reward tidak ditemukan. ID: ' . htmlspecialchars($idTukar) . '</div>';
        exit;
    }

    $pengguna = $tukar['pengguna'] ?? [];
    $reward = $tukar['reward'] ?? [];

    $namaPengguna = $pengguna['nama_lengkap'] ?? $pengguna['nama_pengguna'] ?? '-';
    $emailPengguna = $pengguna['email'] ?? '-';
    $noTelepon = $pengguna['no_telepon'] ?? '-';

    $namaReward = $reward['nama_reward'] ?? '-';
    $fotoReward = $reward['foto_reward'] ?? null;
    $poinDibutuhkan = $reward['poin_dibutuhkan'] ?? 0;
    $kodeVoucher = $reward['kode_voucher'] ?? '-';
    $stokReward = $reward['stok'] ?? 0;

    $tanggalTukar = $tukar['created_at'] ?? null;

    $fotoUrlReward = getSupabaseImageUrl($fotoReward);
    $tanggalFormatted = formatTanggalWaktuIndonesia($tanggalTukar);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tukar Reward - Monitor Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Admin Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="kelola_akun.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i>
                    <span>Kelola Akun</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="kelola_data_master.php" class="nav-link-custom">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>Kelola Data Master</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="monitor_transaksi.php" class="nav-link-custom active">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Monitor Transaksi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pembayaran_komisi.php" class="nav-link-custom">
                    <i class="bi bi-cash-coin"></i>
                    <span>Pembayaran Komisi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="aktivitas.php" class="nav-link-custom">
                    <i class="bi bi-activity"></i>
                    <span>Aktivitas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="sponsor.php" class="nav-link-custom">
                    <i class="bi bi-megaphone-fill"></i>
                    <span>Sponsor</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom">
                    <i class="bi bi-gear-fill"></i>
                    <span>Pengaturan Akun</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Monitor Transaksi</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)):
                            $fotoUrl = getSupabaseImageUrl($userFoto);
                        ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>"
                                style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <i class="bi bi-person-fill" style="display: none;"></i>
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="setting-bar-wrap">
            <div class="settings-card">
                <div class="card-accent"></div>
                <div class="card-body-inner">
                    <div class="detail-two-columns">
                        <div class="detail-header">
                            <h3>Detail Tukar Reward</h3>
                            <h1 class="event-title"><?= htmlspecialchars($namaPengguna) ?></h1>
                        </div>
                        <div class="detail-info-left">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-gift"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Nama Reward</div>
                                        <div class="info-value"><?= htmlspecialchars($namaReward) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-star"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Jumlah Poin</div>
                                        <div class="info-value">
                                            <?= number_format($poinDibutuhkan, 0, ',', '.') ?> Poin
                                        </div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Tanggal Penukaran</div>
                                        <div class="info-value"><?= htmlspecialchars($tanggalFormatted) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-envelope"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Email Pengguna</div>
                                        <div class="info-value"><?= htmlspecialchars($emailPengguna) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">No. Telepon</div>
                                        <div class="info-value"><?= htmlspecialchars($noTelepon) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-ticket-perforated"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Kode Voucher</div>
                                        <div class="info-value">
                                            <code><?= htmlspecialchars($kodeVoucher) ?></code>
                                        </div>
                                    </div>
                                </div>

                                <div class="deskripsi-section">
                                    <div class="deskripsi-label">
                                        <i class="bi bi-file-text"></i> Ringkasan
                                    </div>
                                    <div class="deskripsi-text">
                                        Pengguna <strong><?= htmlspecialchars($namaPengguna) ?></strong> 
                                        telah melakukan penukaran reward 
                                        <strong><?= htmlspecialchars($namaReward) ?></strong> 
                                        dengan total <strong><?= number_format($poinDibutuhkan, 0, ',', '.') ?> poin</strong> 
                                        pada tanggal <strong><?= htmlspecialchars($tanggalFormatted) ?></strong>.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-photo-right">
                            <div class="photo-container">
                                <?php if ($fotoUrlReward): ?>
                                    <img src="<?= htmlspecialchars($fotoUrlReward) ?>" 
                                        alt="<?= htmlspecialchars($namaReward) ?>"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="photo-placeholder" style="display: none;">
                                        <i class="bi bi-image"></i>
                                        <p>Foto tidak tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i class="bi bi-image"></i>
                                        <p>Tidak ada foto reward</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>