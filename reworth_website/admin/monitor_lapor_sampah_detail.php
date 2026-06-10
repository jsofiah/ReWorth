<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $idLaporan = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idLaporan)) {
        echo '<div class="alert alert-danger m-3">ID Laporan tidak ditemukan.</div>';
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

    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'verified') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'diproses' || $status == 'processing') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'ditolak' || $status == 'rejected') {
            return '<span class="status-badge status-akan_datang">Ditolak</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Menunggu Verifikasi</span>';
        }
    }

    $url = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq.$idLaporan";
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

    $laporan = null;
    if ($response && $httpCode == 200) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data[0])) {
            $laporan = $data[0];
        }
    }

    if (!$laporan) {
        echo '<div class="alert alert-danger m-3">Data laporan tidak ditemukan. ID: ' . htmlspecialchars($idLaporan) . '</div>';
        exit;
    }

    $pengguna = [];
    if (!empty($laporan['id_pengguna'])) {
        $urlPengguna = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $laporan['id_pengguna'];
        $chPengguna = curl_init();
        curl_setopt_array($chPengguna, [
            CURLOPT_URL => $urlPengguna,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        $responsePengguna = curl_exec($chPengguna);
        curl_close($chPengguna);
        
        if ($responsePengguna) {
            $dataPengguna = json_decode($responsePengguna, true);
            if (is_array($dataPengguna) && isset($dataPengguna[0])) {
                $pengguna = $dataPengguna[0];
            }
        }
    }

    $petugas = [];
    if (!empty($laporan['id_petugas'])) {
        $urlPetugas = $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . $laporan['id_petugas'];
        $chPetugas = curl_init();
        curl_setopt_array($chPetugas, [
            CURLOPT_URL => $urlPetugas,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        $responsePetugas = curl_exec($chPetugas);
        curl_close($chPetugas);
        
        if ($responsePetugas) {
            $dataPetugas = json_decode($responsePetugas, true);
            if (is_array($dataPetugas) && isset($dataPetugas[0])) {
                $petugas = $dataPetugas[0];
            }
        }
    }

    $namaPengguna = $pengguna['nama_lengkap'] ?? $laporan['nama_pelapor'] ?? '-';
    $emailPengguna = $pengguna['email'] ?? '-';
    $noTelepon = $pengguna['no_telepon'] ?? '-';

    $fotoSampah = $laporan['foto_sampah'] ?? null;
    $lokasi = $laporan['lokasi'] ?? '-';
    $jenisSampah = $laporan['jenis_sampah'] ?? '-';
    $deskripsi = $laporan['deskripsi'] ?? '-';
    $status = $laporan['status'] ?? 'pending';
    $alasanPenolakan = $laporan['alasan_penolakan'] ?? null;
    $buktiPenanganan = $laporan['bukti_penanganan'] ?? null;
    $latitude = $laporan['latitude'] ?? null;
    $longitude = $laporan['longitude'] ?? null;
    $createdAt = $laporan['created_at'] ?? null;

    $namaPetugas = $petugas['nama_petugas'] ?? $petugas['nama_lengkap'] ?? '-';

    $tanggalFormatted = formatTanggalWaktuIndonesia($createdAt);
    $fotoSampahUrl = getSupabaseImageUrl($fotoSampah);
    $buktiPenangananUrl = getSupabaseImageUrl($buktiPenanganan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Lapor Sampah - Monitor Transaksi</title>
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
                            <h3>Detail Lapor Sampah</h3>
                            <h1 class="event-title"><?= htmlspecialchars($namaPengguna) ?></h1>
                        </div>
                        <div class="detail-info-left">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-recycle"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Jenis Sampah</div>
                                        <div class="info-value"><?= htmlspecialchars($jenisSampah) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-geo-alt"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Lokasi</div>
                                        <div class="info-value"><?= htmlspecialchars($lokasi) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-person-badge"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Nama Petugas</div>
                                        <div class="info-value"><?= htmlspecialchars($namaPetugas) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-info-circle"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Status</div>
                                        <div class="info-value"><?= getStatusBadge($status) ?></div>
                                    </div>
                                </div>

                                <?php if (!empty($deskripsi)): ?>
                                <div class="deskripsi-section">
                                    <div class="deskripsi-label">
                                        <i class="bi bi-file-text"></i> Deskripsi
                                    </div>
                                    <div class="deskripsi-text">
                                        <?= nl2br(htmlspecialchars($deskripsi)) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="detail-photo-right">
                            <div class="photo-container">
                                <?php if ($fotoSampahUrl): ?>
                                    <img src="<?= htmlspecialchars($fotoSampahUrl) ?>" 
                                        alt="Foto Sampah"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="photo-placeholder" style="display: none;">
                                        <i class="bi bi-receipt"></i>
                                        <p>Foto sampah tidak tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i class="bi bi-receipt"></i>
                                        <p>Belum ada Foto sampah</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; margin-top: 8px; font-size: 11px; color: #9ca3af;">
                                Bukti Foto Sampah
                            </div>

                            <div class="photo-container">
                                <?php if ($buktiPenangananUrl): ?>
                                    <img src="<?= htmlspecialchars($buktiPenangananUrl) ?>" 
                                        alt="Bukti Penanganan Sampah"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="photo-placeholder" style="display: none;">
                                        <i class="bi bi-receipt"></i>
                                        <p>Bukti penanganan sampah tidak tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i class="bi bi-receipt"></i>
                                        <p>Belum ada bukti penanganan sampah</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; margin-top: 8px; font-size: 11px; color: #9ca3af;">
                                Bukti Penanganan Sampah
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>