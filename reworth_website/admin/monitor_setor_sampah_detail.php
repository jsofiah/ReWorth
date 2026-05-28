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

    $idSetor = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idSetor)) {
        echo '<div class="alert alert-danger m-3">ID Setor Sampah tidak ditemukan.</div>';
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

    function formatTanggalIndonesia($date) {
        if (empty($date)) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun";
    }

    function formatRupiah($angka) {
        if (empty($angka)) return 'Rp 0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
    
    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'completed') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'diproses' || $status == 'processing') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'dibatalkan' || $status == 'cancelled') {
            return '<span class="status-badge status-ditolak">Dibatalkan</span>';
        } elseif ($status == 'diverifikasi' || $status == 'verified') {
            return '<span class="status-badge status-verified">Diverifikasi</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Menunggu Verifikasi</span>';
        }
    }

    $url = $supabaseUrl . "/rest/v1/setor_sampah?id_setor=eq.$idSetor";
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

    $setor = null;
    if ($response && $httpCode == 200) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data[0])) {
            $setor = $data[0];
        }
    }

    if (!$setor) {
        echo '<div class="alert alert-danger m-3">Data setor sampah tidak ditemukan. ID: ' . htmlspecialchars($idSetor) . '</div>';
        exit;
    }

    $pengguna = [];
    if (!empty($setor['id_pengguna'])) {
        $urlPengguna = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $setor['id_pengguna'];
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

    $jadwal = [];
    if (!empty($setor['id_jadwal'])) {
        $urlJadwal = $supabaseUrl . "/rest/v1/jadwal_ambil?id_jadwal=eq." . $setor['id_jadwal'];
        $chJadwal = curl_init();
        curl_setopt_array($chJadwal, [
            CURLOPT_URL => $urlJadwal,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        $responseJadwal = curl_exec($chJadwal);
        curl_close($chJadwal);
        
        if ($responseJadwal) {
            $dataJadwal = json_decode($responseJadwal, true);
            if (is_array($dataJadwal) && isset($dataJadwal[0])) {
                $jadwal = $dataJadwal[0];
            }
        }
    }

    $detailSetor = [];
    if (!empty($setor['id_setor'])) {
        $urlDetail = $supabaseUrl . "/rest/v1/detail_setor?id_setor=eq." . $setor['id_setor'];
        $chDetail = curl_init();
        curl_setopt_array($chDetail, [
            CURLOPT_URL => $urlDetail,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        $responseDetail = curl_exec($chDetail);
        curl_close($chDetail);
        
        if ($responseDetail) {
            $detailSetor = json_decode($responseDetail, true);
            if (!is_array($detailSetor)) {
                $detailSetor = [];
            }
        }
        
        foreach ($detailSetor as $key => $detail) {
            if (!empty($detail['id_jenis'])) {
                $urlJenis = $supabaseUrl . "/rest/v1/jenis_sampah?id_jenis=eq." . $detail['id_jenis'];
                $chJenis = curl_init();
                curl_setopt_array($chJenis, [
                    CURLOPT_URL => $urlJenis,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "apikey: $supabaseKey",
                        "Authorization: Bearer $supabaseKey"
                    ]
                ]);
                $responseJenis = curl_exec($chJenis);
                curl_close($chJenis);
                
                if ($responseJenis) {
                    $dataJenis = json_decode($responseJenis, true);
                    if (is_array($dataJenis) && isset($dataJenis[0])) {
                        $detailSetor[$key]['jenis_sampah'] = $dataJenis[0];
                    }
                }
            }
        }
    }

    $namaPengguna = $pengguna['nama_lengkap'] ?? $setor['nama_pengguna'] ?? '-';
    $emailPengguna = $pengguna['email'] ?? '-';
    $noTelepon = $pengguna['no_telepon'] ?? '-';
    $alamat = $setor['alamat'] ?? '-';
    $totalUang = $setor['total_uang'] ?? 0;
    $status = $setor['status'] ?? 'pending';
    $createdAt = $setor['created_at'] ?? null;

    $tanggalJadwal = $jadwal['tanggal'] ?? null;
    $waktuMulai = $jadwal['waktu_mulai'] ?? null;
    $waktuSelesai = $jadwal['waktu_selesai'] ?? null;
    $kuota = $jadwal['kuota'] ?? 0;

    $tanggalFormatted = formatTanggalWaktuIndonesia($createdAt);
    $tanggalJadwalFormatted = $tanggalJadwal ? formatTanggalIndonesia($tanggalJadwal) : '-';
    $waktuMulaiFormatted = $waktuMulai ? date('H:i', strtotime($waktuMulai)) : '-';
    $waktuSelesaiFormatted = $waktuSelesai ? date('H:i', strtotime($waktuSelesai)) : '-';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Setor Sampah - Monitor Transaksi</title>
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
                <div class="card-body-inner2">
                    <div class="detail-header">
                        <h3>Detail Setor Sampah</h3>
                        <h1 class="event-title"><?= htmlspecialchars($namaPengguna) ?></h1>
                    </div>

                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Total Uang</div>
                            <div class="info-value large"><?= formatRupiah($totalUang) ?></div>
                        </div>
                    </div>

                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Alamat</div>
                            <div class="info-value"><?= nl2br(htmlspecialchars($alamat)) ?></div>
                        </div>
                    </div>

                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Tanggal Setor</div>
                            <div class="info-value"><?= htmlspecialchars($tanggalFormatted) ?></div>
                        </div>
                    </div>

                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Status</div>
                            <div class="info-value"><?= getStatusBadge($status) ?></div>
                        </div>
                    </div>

                    <?php if (!empty($setor['id_jadwal']) && !empty($jadwal)): ?>
                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Jadwal Setor</div>
                            <div class="info-value">
                                <strong><?= htmlspecialchars($tanggalJadwalFormatted) ?></strong><br>
                                <?php if ($waktuMulaiFormatted != '-' && $waktuSelesaiFormatted != '-'): ?>
                                    Jam: <?= $waktuMulaiFormatted ?> - <?= $waktuSelesaiFormatted ?> WIB<br>
                                <?php endif; ?>
                                <?php if ($kuota > 0): ?>
                                    Kuota: <?= $kuota ?> peserta
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Email Pengguna</div>
                            <div class="info-value"><?= htmlspecialchars($emailPengguna) ?></div>
                        </div>
                    </div>

                    <div class="info-item2">
                        <div class="info-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">No. Telepon</div>
                            <div class="info-value"><?= htmlspecialchars($noTelepon) ?></div>
                        </div>
                    </div>

                    <?php if (!empty($detailSetor)): ?>
                    <div class="info-label" style="margin-bottom: 12px;">
                        <i class="bi bi-receipt"></i> Detail Sampah yang Disetor
                    </div>
                    
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Jenis Sampah</th>
                                <th>Berat (kg)</th>
                                <th>Harga/kg</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $totalBerat = 0;
                            $totalSubtotal = 0;
                            foreach ($detailSetor as $detail): 
                                $jenisSampah = $detail['jenis_sampah']['nama_sampah'] ?? '-';
                                $berat = floatval($detail['berat'] ?? 0);
                                $hargaPerKg = floatval($detail['harga_per_kg'] ?? 0);
                                $subtotal = floatval($detail['subtotal'] ?? ($berat * $hargaPerKg));
                                
                                $totalBerat += $berat;
                                $totalSubtotal += $subtotal;
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($jenisSampah) ?></td>
                                <td><?= number_format($berat, 2, ',', '.') ?> kg</td>
                                <td><?= formatRupiah($hargaPerKg) ?> / kg</td>
                                <td><?= formatRupiah($subtotal) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2"><strong>Total</strong></td>
                                <td><strong><?= number_format($totalBerat, 2, ',', '.') ?> kg</strong></td>
                                <td></td>
                                <td><strong><?= formatRupiah($totalSubtotal) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <hr>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Tidak ada detail sampah yang disetor.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>