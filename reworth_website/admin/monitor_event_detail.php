<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $idEvent = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idEvent)) {
        echo '<div class="alert alert-danger m-3">ID Event tidak ditemukan.</div>';
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
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun, $jam";
    }

    function getStatusBadge($status) {
        $status = strtolower($status);
        $statusMap = [
            'selesai' => ['class' => 'status-selesai', 'text' => 'Selesai'],
            'berlangsung' => ['class' => 'status-berlangsung', 'text' => 'Berlangsung'],
            'dibatalkan' => ['class' => 'status-dibatalkan', 'text' => 'Dibatalkan'],
            'akan_datang' => ['class' => 'status-akan_datang', 'text' => 'Akan Datang']
        ];
        
        $statusKey = $status;
        if (!isset($statusMap[$statusKey])) {
            $statusKey = 'akan_datang';
        }
        
        return '<span class="status-badge ' . $statusMap[$statusKey]['class'] . '">' . $statusMap[$statusKey]['text'] . '</span>';
    }

    $url = $supabaseUrl . "/rest/v1/event?id_event=eq.$idEvent&select=*,admin!id_pembuat(id_admin,nama_admin,email,id_role,role!id_role(nama_role))";

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
    curl_close($ch);

    $event = null;
    if ($response) {
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data)) {
            $event = $data[0];
        }
    }

    if (!$event) {
        echo '<div class="alert alert-danger m-3">Data event tidak ditemukan.</div>';
        exit;
    }

    $countUrl = $supabaseUrl . "/rest/v1/pendaftar_event?select=id_pendaftar_event&id_event=eq.$idEvent";
    $chCount = curl_init();
    curl_setopt_array($chCount, [
        CURLOPT_URL => $countUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]
    ]);

    $countResponse = curl_exec($chCount);
    curl_close($chCount);

    $jumlahPeserta = 0;
    if ($countResponse) {
        $pesertaData = json_decode($countResponse, true);
        $jumlahPeserta = is_array($pesertaData) ? count($pesertaData) : 0;
    }

    $namaEvent = $event['nama_event'] ?? '-';
    $tanggal = $event['tanggal'] ?? null;
    $waktuMulai = $event['waktu_mulai'] ?? null;
    $waktuSelesai = $event['waktu_selesai'] ?? null;
    $lokasi = $event['lokasi'] ?? '-';
    $deskripsi = $event['deskripsi'] ?? '';
    $status = $event['status'] ?? 'akan_datang';
    $fotoEvent = $event['foto_event'] ?? null;
    $maxPartisipan = $event['max_partisipan'] ?? 0;
    $narasumber = $event['narasumber'] ?? '-';
    $penyelenggara = '-';

    if (isset($event['admin']) && is_array($event['admin'])) {
        $admin = $event['admin'];
        $namaAdmin = $admin['nama_admin'] ?? '-';
        $role = isset($admin['role']) && is_array($admin['role']) ? $admin['role']['nama_role'] ?? '' : '';
        
        if ($role == 'dlh') {
            $penyelenggara = "DLH - " . $namaAdmin;
        } elseif ($role == 'bank sampah') {
            $penyelenggara = "Bank Sampah - " . $namaAdmin;
        } elseif ($role == 'admin') {
            $penyelenggara = "Admin - " . $namaAdmin;
        } else {
            $penyelenggara = $namaAdmin;
        }
    }

    $tanggalFormatted = formatTanggalIndonesia($tanggal);
    $waktuString = '';
    if ($waktuMulai && $waktuSelesai) {
        $waktuMulaiFormatted = date('H:i', strtotime($waktuMulai));
        $waktuSelesaiFormatted = date('H:i', strtotime($waktuSelesai));
        $waktuString = $waktuMulaiFormatted . ' - ' . $waktuSelesaiFormatted . ' WIB';
    } elseif ($waktuMulai) {
        $waktuString = date('H:i', strtotime($waktuMulai)) . ' WIB';
    }

    $fotoUrlEvent = getSupabaseImageUrl($fotoEvent);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Event - Monitor Transaksi</title>
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
                            <h3>Detail Event</h3>
                            <h1 class="event-title"><?= htmlspecialchars($namaEvent) ?></h1>
                        </div>
                        <div class="detail-info-left">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-mic"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Narasumber</div>
                                        <div class="info-value"><?= htmlspecialchars($narasumber) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-building"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Diselenggarakan Oleh</div>
                                        <div class="info-value"><?= htmlspecialchars($penyelenggara) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Jumlah Peserta</div>
                                        <div class="info-value">
                                            <div class="peserta-detail">
                                                <span class="peserta-angka"><?= number_format($jumlahPeserta) ?></span>
                                                <span class="peserta-maks">/ <?= number_format($maxPartisipan) ?> peserta</span>
                                                <?php if ($maxPartisipan > 0 && $jumlahPeserta >= $maxPartisipan): ?>
                                                    <span class="peserta-full">(Penuh)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Tanggal & Waktu</div>
                                        <div class="info-value">
                                            <?= htmlspecialchars($tanggalFormatted) ?>
                                            <?php if ($waktuString): ?>
                                                <br><span style="font-size: 13px; color: #6b7280;"><?= htmlspecialchars($waktuString) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-info-circle"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Status</div>
                                        <div class="info-value">
                                            <?= getStatusBadge($status) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($deskripsi)): ?>
                            <div class="deskripsi-section">
                                <div class="deskripsi-label">
                                    <i class="bi bi-file-text"></i> Deskripsi Event
                                </div>
                                <div class="deskripsi-text">
                                    <?= nl2br(htmlspecialchars($deskripsi)) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="detail-photo-right">
                            <div class="photo-container">
                                <?php if ($fotoUrlEvent): ?>
                                    <img src="<?= htmlspecialchars($fotoUrlEvent) ?>" 
                                        alt="<?= htmlspecialchars($namaEvent) ?>"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="photo-placeholder" style="display: none;">
                                        <i class="bi bi-image"></i>
                                        <p>Foto tidak tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i class="bi bi-image"></i>
                                        <p>Tidak ada foto event</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function goBack() {
            if (window.parent && window.parent.closeModal) {
                window.parent.closeModal('modalDetail');
            } else {
                window.location.href = 'monitor_transaksi.php?tab=event';
            }
        }

        function showToast(msg, type = 'success') {
            const icons = { 
                success: 'bi-check-circle-fill', 
                error: 'bi-x-circle-fill', 
                info: 'bi-info-circle-fill' 
            };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            const container = document.getElementById('toastContainer');
            if (container) {
                container.appendChild(div);
                setTimeout(() => div.remove(), 3500);
            }
        }
    </script>
</body>
</html>