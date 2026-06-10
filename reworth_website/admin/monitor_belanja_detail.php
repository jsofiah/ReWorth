s<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userRole = $_SESSION['role'] ?? '';
    $userFoto = $_SESSION['foto_profil'] ?? '';
    $userId = $_SESSION['id_admin'] ?? '';

    $idPesanan = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idPesanan)) {
        echo '<div class="alert alert-danger m-3">ID Pesanan tidak ditemukan.</div>';
        exit;
    }

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

    function formatRupiah($angka) {
        if (empty($angka)) return 'Rp 0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'completed') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'dikirim' || $status == 'shipped') {
            return '<span class="status-badge status-berlangsung">Dikirim</span>';
        } elseif ($status == 'diproses' || $status == 'processing') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'ditolak' || $status == 'rejected') {
            return '<span class="status-badge status-akan_datang">Ditolak</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Menunggu Verifikasi</span>';
        }
    }

    $url = $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$idPesanan&select=*,pengguna(*),produk(*)";

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

    $pesanan = null;

    if ($response && $httpCode == 200) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $pesanan = $data[0];
        }
    }

    if (!$pesanan) {
        echo '<div class="alert alert-danger m-3">Data pesanan tidak ditemukan. ID: ' . htmlspecialchars($idPesanan) . '</div>';
        exit;
    }

    $pengguna = $pesanan['pengguna'] ?? [];
    $namaPengguna = $pengguna['nama_lengkap'] ?? '-';
    $emailPengguna = $pengguna['email'] ?? '-';
    $noTelepon = $pengguna['no_telepon'] ?? '-';
    $alamatPengguna = $pengguna['alamat'] ?? $pesanan['alamat_pengiriman'] ?? '-';

    $produk = $pesanan['produk'] ?? [];
    $namaProduk = $produk['nama_produk'] ?? '-';
    $hargaProduk = $produk['harga'] ?? 0;
    $deskripsiProduk = $produk['deskripsi_produk'] ?? '';

    $tanggalPesanan = $pesanan['created_at'] ?? null;
    $totalBayar = $pesanan['total_harga'] ?? $hargaProduk;
    $status = $pesanan['status'] ?? 'pending';
    $buktiPembayaran = $pesanan['bukti_pembayaran'] ?? null;
    $jumlah = $pesanan['jumlah'] ?? 1;
    $catatan = $pesanan['catatan'] ?? '';

    $tanggalFormatted = formatTanggalWaktuIndonesia($tanggalPesanan);
    $fotoBuktiUrl = getSupabaseImageUrl($buktiPembayaran);
    $fotoProdukUrl = getSupabaseImageUrl($produk['foto_produk'] ?? null);

    $penjual = $produk['penjual'] ?? [];
    $namaPenjual = $penjual['nama_penjual'] ?? '-';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Belanja - Monitor Transaksi</title>
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
                            <h3>Detail Belanja</h3>
                            <h1 class="event-title"><?= htmlspecialchars($namaPengguna) ?></h1>
                        </div>
                        <div class="detail-info-left">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Nama Produk</div>
                                        <div class="info-value"><?= htmlspecialchars($namaProduk) ?></div>
                                        <?php if (!empty($namaPenjual) && $namaPenjual != '-'): ?>
                                            <div class="info-value" style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                                Penjual: <?= htmlspecialchars($namaPenjual) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Total Bayar</div>
                                        <div class="info-value large"><?= formatRupiah($totalBayar) ?></div>
                                        <?php if ($jumlah > 1): ?>
                                            <div class="info-value" style="font-size: 12px; color: #6b7280;">
                                                <?= $jumlah ?> x <?= formatRupiah($hargaProduk) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Tanggal Belanja</div>
                                        <div class="info-value"><?= htmlspecialchars($tanggalFormatted) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-geo-alt"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Alamat Pengiriman</div>
                                        <div class="info-value"><?= nl2br(htmlspecialchars($alamatPengguna)) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-info-circle"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Status Pesanan</div>
                                        <div class="info-value"><?= getStatusBadge($status) ?></div>
                                    </div>
                                </div>

                                <?php if (!empty($catatan)): ?>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-chat"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Catatan</div>
                                        <div class="info-value"><?= nl2br(htmlspecialchars($catatan)) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <hr>
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
                            </div>
                        </div>

                        <div class="detail-photo-right">
                            
                            <div class="photo-container">
                                <?php if ($fotoBuktiUrl): ?>
                                    <img src="<?= htmlspecialchars($fotoBuktiUrl) ?>" 
                                        alt="Bukti Pembayaran"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="photo-placeholder" style="display: none;">
                                        <i class="bi bi-receipt"></i>
                                        <p>Bukti pembayaran tidak tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i class="bi bi-receipt"></i>
                                        <p>Belum ada bukti pembayaran</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; margin-top: 8px; font-size: 11px; color: #9ca3af;">
                                Bukti Pembayaran
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</body>
</html>