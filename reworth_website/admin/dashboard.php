<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userRole = $_SESSION['role'] ?? '';
    $userFoto = $_SESSION['foto_profil'] ?? '';
    $userId = $_SESSION['id_admin'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function sbGet($url, $key, $endpoint) {
        $ch = curl_init($url . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $key",
                "Authorization: Bearer $key",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200 ? (json_decode($response, true) ?: []) : [];
    }

    $totalPengguna = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pengguna?select=id_pengguna"));
    $totalAdmin    = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/admin?select=id_admin"));
    $totalPenjual  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/penjual?select=id_penjual"));
    $totalSponsor  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/sponsor?select=id_sponsor"));
    $totalWilayah  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/wilayah?select=id_wilayah"));
    $totalReward   = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/reward?select=id_reward"));

    $langgananMenunggu = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/langganan?select=id_langganan,penjual(nama_penjual),jumlah_bayar&status=eq.menunggu");
    $komisiMenunggu = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/komisi?select=id_komisi,penjual(nama_penjual),total_komisi&status_pembayaran=eq.pending");
    
    $totalMenungguLangganan = count($langgananMenunggu);
    $totalMenungguKomisi = count($komisiMenunggu);

    $bulanIni = date('Y-m');
    $startBulan = $bulanIni . '-01T00:00:00';
    $endBulan = $bulanIni . '-31T23:59:59';
    
    $langgananBulanIni = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/langganan?select=jumlah_bayar&created_at=gte." . urlencode($startBulan) . "&created_at=lte." . urlencode($endBulan));
    $totalLanggananBulanIni = array_sum(array_column($langgananBulanIni, 'jumlah_bayar'));
    
    $langgananTotal = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/langganan?select=jumlah_bayar");
    $totalLanggananKeseluruhan = array_sum(array_column($langgananTotal, 'jumlah_bayar'));
    
    $komisiBulanIni = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/komisi?select=total_komisi&created_at=gte." . urlencode($startBulan) . "&created_at=lte." . urlencode($endBulan));
    $totalKomisiBulanIni = array_sum(array_column($komisiBulanIni, 'total_komisi'));
    
    $komisiTotal = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/komisi?select=total_komisi");
    $totalKomisiKeseluruhan = array_sum(array_column($komisiTotal, 'total_komisi'));
    
    $sponsorList = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/kontribusi_sponsor?select=nominal_uang");
    $totalKontribusiSponsor = array_sum(array_column($sponsorList, 'nominal_uang'));
    
    $pengeluaranList = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pengeluaran?select=jumlah");
    $totalPengeluaran = array_sum(array_column($pengeluaranList, 'jumlah'));
    
    $totalPemasukan = $totalLanggananKeseluruhan + $totalKomisiKeseluruhan + $totalKontribusiSponsor;
    $saldoAkhir = $totalPemasukan - $totalPengeluaran;

    $logAktivitas = sbGet($supabaseUrl, $supabaseKey, 
        "/rest/v1/log_admin?select=*,admin(nama_admin)&order=created_at.desc&limit=5");

    function fmtRp($n) { 
        return 'Rp ' . number_format((float)$n, 0, ',', '.'); 
    }
    function fmtNum($n) { 
        return number_format((int)$n, 0, ',', '.'); 
    }
    function formatTanggalIndonesia($date) {
        if (empty($date)) return '-';
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $timestamp = strtotime($date);
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);
        return "$tanggal " . $bulan[$bulanNum] . " $tahun, $jam";
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/dashboard.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Admin Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom active">
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
                <a href="monitor_transaksi.php" class="nav-link-custom">
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
                <h1 class="topbar-title">Dashboard</h1>
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

        <div class="action-bar-wrap">
            <div class="action-bar">
                <div class="info-cards">
                    <div class="info-item">
                        <div class="info-icon blue"><i class="bi bi-people-fill"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtNum($totalPengguna) ?></div>
                            <div class="info-label">Pengguna</div>
                        </div>
                    </div>
                    <div class="separator"></div>
                    <div class="info-item">
                        <div class="info-icon green"><i class="bi bi-person-badge-fill"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtNum($totalAdmin) ?></div>
                            <div class="info-label">Admin</div>
                        </div>
                    </div>
                    <div class="separator"></div>
                    <div class="info-item">
                        <div class="info-icon orange"><i class="bi bi-shop"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtNum($totalPenjual) ?></div>
                            <div class="info-label">Penjual</div>
                        </div>
                    </div>
                    <div class="separator"></div>
                    <div class="info-item">
                        <div class="info-icon red"><i class="bi bi-cash-stack"></i></div>
                        <div class="info-content">
                            <div class="info-value"><?= fmtRp($totalLanggananBulanIni + $totalKomisiBulanIni) ?></div>
                            <div class="info-label">Pendapatan Bulan Ini</div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <?php if ($totalMenungguLangganan > 0): ?>
                        <button class="btn-notif" onclick="window.location.href='kelola_data_master.php?tab=langganan'" style="background:#FFF3E0; color:#E65100;">
                            <i class="bi bi-journal-bookmark-fill"></i>
                            <?= $totalMenungguLangganan ?> Langganan
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($totalMenungguKomisi > 0): ?>
                        <button class="btn-notif" onclick="window.location.href='pembayaran_komisi.php'" style="background:#FFEBEE; color:#C62828;">
                            <i class="bi bi-cash-coin"></i>
                            <?= $totalMenungguKomisi ?> Komisi
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($totalMenungguLangganan == 0 && $totalMenungguKomisi == 0): ?>
                        <button class="btn-notif" style="background:#E8F5E9; color:#2E7D32;" disabled>
                            <i class="bi bi-check-circle-fill"></i>
                            Semua Terverifikasi
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="three-cards">
                <div class="stat-card-dashboard">
                    <div class="stat-icon-lg teal"><i class="bi bi-map"></i></div>
                    <div class="stat-value-lg"><?= fmtNum($totalWilayah) ?></div>
                    <div class="stat-label-lg">Total Wilayah</div>
                </div>
                <div class="stat-card-dashboard">
                    <div class="stat-icon-lg purple"><i class="bi bi-gift-fill"></i></div>
                    <div class="stat-value-lg"><?= fmtNum($totalReward) ?></div>
                    <div class="stat-label-lg">Total Reward</div>
                </div>
                <div class="stat-card-dashboard">
                    <div class="stat-icon-lg orange"><i class="bi bi-megaphone-fill"></i></div>
                    <div class="stat-value-lg"><?= fmtNum($totalSponsor) ?></div>
                    <div class="stat-label-lg">Total Sponsor</div>
                </div>
            </div>

            <div class="verif-quick-grid">
                <div class="card-dashboard">
                    <div class="card-title">
                        <i class="bi bi-journal-bookmark-fill"></i>
                        Verifikasi Langganan
                        <?php if ($totalMenungguLangganan > 0): ?>
                            <span class="pending-badge"><?= $totalMenungguLangganan ?> perlu ditindak</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($langgananMenunggu)): ?>
                        <?php 
                        $tampilLangganan = array_slice($langgananMenunggu, 0, 4);
                        foreach ($tampilLangganan as $l): 
                            $namaPenjual = $l['penjual']['nama_penjual'] ?? '-';
                        ?>
                        <div class="verif-item">
                            <div>
                                <strong><?= htmlspecialchars($namaPenjual) ?></strong>
                                <div style="font-size: 12px; color: #6B8A7E;"><?= fmtRp($l['jumlah_bayar'] ?? 0) ?></div>
                            </div>
                            <a href="kelola_data_master.php?tab=langganan" class="btn-verif-small">
                                <i class="bi bi-check2-circle"></i> Verifikasi
                            </a>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($langgananMenunggu) > 4): ?>
                            <div style="text-align: center; margin-top: 8px;">
                                <a href="kelola_data_master.php?tab=langganan" style="color: var(--green); font-size: 12px;">+ <?= count($langgananMenunggu) - 4 ?> lainnya</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px 0; color: #6B8A7E;">
                            <i class="bi bi-check-circle-fill" style="font-size: 32px; color: var(--green);"></i>
                            <p style="margin-top: 8px;">Tidak ada langganan yang perlu diverifikasi</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-dashboard">
                    <div class="card-title">
                        <i class="bi bi-cash-coin"></i>
                        Konfirmasi Komisi
                        <?php if ($totalMenungguKomisi > 0): ?>
                            <span class="pending-badge"><?= $totalMenungguKomisi ?> perlu ditindak</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($komisiMenunggu)): ?>
                        <?php 
                        $tampilKomisi = array_slice($komisiMenunggu, 0, 4);
                        foreach ($tampilKomisi as $k): 
                            $namaPenjual = $k['penjual']['nama_penjual'] ?? '-';
                        ?>
                        <div class="verif-item">
                            <div>
                                <strong><?= htmlspecialchars($namaPenjual) ?></strong>
                                <div style="font-size: 12px; color: #6B8A7E;"><?= fmtRp($k['total_komisi'] ?? 0) ?></div>
                            </div>
                            <a href="pembayaran_komisi.php" class="btn-verif-small">
                                <i class="bi bi-check2-circle"></i> Konfirmasi
                            </a>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($komisiMenunggu) > 4): ?>
                            <div style="text-align: center; margin-top: 8px;">
                                <a href="pembayaran_komisi.php" style="color: var(--green); font-size: 12px;">+ <?= count($komisiMenunggu) - 4 ?> lainnya</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px 0; color: #6B8A7E;">
                            <i class="bi bi-check-circle-fill" style="font-size: 32px; color: var(--green);"></i>
                            <p style="margin-top: 8px;">Tidak ada komisi yang perlu dikonfirmasi</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-dashboard">
                    <div class="card-title">
                        <i class="bi bi-compass"></i>
                        Quick Access
                    </div>
                    <div class="quick-access">
                        <a href="kelola_akun.php" class="quick-btn">
                            <i class="bi bi-people-fill"></i>
                            <span>Kelola Akun</span>
                        </a>
                        <a href="kelola_data_master.php" class="quick-btn">
                            <i class="bi bi-database-fill-gear"></i>
                            <span>Data Master</span>
                        </a>
                        <a href="monitor_transaksi.php" class="quick-btn">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Monitor Transaksi</span>
                        </a>
                        <a href="pembayaran_komisi.php" class="quick-btn">
                            <i class="bi bi-cash-coin"></i>
                            <span>Pembayaran Komisi</span>
                        </a>
                        <a href="laporan_keuangan.php" class="quick-btn">
                            <i class="bi bi-bar-chart-line-fill"></i>
                            <span>Laporan Keuangan</span>
                        </a>
                        <a href="pengaturan_akun.php" class="quick-btn">
                            <i class="bi bi-gear-fill"></i>
                            <span>Pengaturan Akun</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="finance-activity-grid">
                <div class="finance-card">
                    <div class="finance-item">
                        <div class="finance-label">Pemasukan Langganan</div>
                        <div class="finance-value income"><?= fmtRp($totalLanggananKeseluruhan) ?></div>
                    </div>
                    <div class="finance-item">
                        <div class="finance-label">Pemasukan Komisi</div>
                        <div class="finance-value income"><?= fmtRp($totalKomisiKeseluruhan) ?></div>
                    </div>
                    <div class="finance-item">
                        <div class="finance-label">Kontribusi Sponsor</div>
                        <div class="finance-value income"><?= fmtRp($totalKontribusiSponsor) ?></div>
                    </div>
                    <div class="separator-line"></div>
                    <div class="finance-item">
                        <div class="finance-label">Total Pemasukan</div>
                        <div class="finance-value income" style="font-size: 26px;"><?= fmtRp($totalPemasukan) ?></div>
                    </div>
                    <div class="separator-line"></div>
                    <div class="finance-item">
                        <div class="finance-label">Total Pengeluaran</div>
                        <div class="finance-value expense"><?= fmtRp($totalPengeluaran) ?></div>
                    </div>
                    <div class="separator-line"></div>
                    <div class="finance-item">
                        <div class="finance-label">Saldo Akhir</div>
                        <div class="finance-value balance" style="font-size: 26px;"><?= fmtRp($saldoAkhir) ?></div>
                    </div>
                </div>

                <div class="card-dashboard">
                    <div class="card-title">
                        <i class="bi bi-activity"></i>
                        Aktivitas Terbaru
                    </div>
                    
                    <?php if (!empty($logAktivitas)): ?>
                        <?php foreach ($logAktivitas as $log): 
                            $namaAdmin = $log['admin']['nama_admin'] ?? 'Admin';
                        ?>
                        <div class="log-item">
                            <div class="log-icon">
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
                            <div class="log-content">
                                <div class="log-text"><?= htmlspecialchars($log['aktivitas'] ?? '-') ?></div>
                                <div class="log-time">
                                    <i class="bi bi-clock"></i> <?= formatTanggalIndonesia($log['created_at'] ?? '') ?>
                                    <span style="margin-left: 8px;">by <?= htmlspecialchars($namaAdmin) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 12px; text-align: center;">
                            <a href="aktivitas.php" style="color: var(--green); text-decoration: none; font-size: 13px; font-weight: 500;">
                                Lihat semua aktivitas <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px 0; color: #6B8A7E;">
                            <i class="bi bi-inbox" style="font-size: 40px;"></i>
                            <p style="margin-top: 12px;">Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>