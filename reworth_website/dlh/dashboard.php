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

$totalEvent    = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/event?select=id_event"));
$totalPetugas  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/petugas_lapangan?select=id_petugas&status_aktif=eq.true"));
$totalApresiasi = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/apresiasi?select=id_apresiasi"));

$laporMenunggu = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/lapor_sampah?select=id_laporan,lokasi,jenis_sampah,created_at&status=eq.menunggu&order=created_at.desc&limit=5");
$totalMenunggu = count(sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/lapor_sampah?select=id_laporan&status=eq.menunggu"));

$logAktivitas = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/log_admin?select=*,admin(nama_admin)&id_admin=eq." . urlencode($userId) . "&order=created_at.desc&limit=5");

function formatTanggal($date) {
    if (empty($date)) return '-';
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $bulan[(int)date('m', $ts)] . ' ' . date('Y, H:i', $ts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DLH ReWorth</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><img src="img/logo.png" alt="Logo"></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="dashboard.php" class="nav-link-custom active"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="laporan_sampah.php" class="nav-link-custom"><i class="bi bi-file-text-fill"></i><span>Laporan Sampah</span></a></div>
        <div class="nav-item"><a href="apresiasi_rw.php" class="nav-link-custom"><i class="bi bi-trophy-fill"></i><span>Apresiasi RW</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="laporan_analitik.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a></div>
        <div class="nav-item"><a href="data_petugas.php" class="nav-link-custom"><i class="bi bi-person-badge-fill"></i><span>Data Petugas</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
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
                    <?php if (!empty($userFoto)): ?>
                        <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                             style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="action-bar-wrap">
        <div class="db-stats-row">
            <div class="db-stat-card">
                <div class="db-stat-icon" style="background:rgba(142,166,4,0.12);color:var(--status-selesai);">
                    <i class="bi bi-calendar-event-fill"></i>
                </div>
                <div class="db-stat-info">
                    <div class="db-stat-value"><?= $totalEvent ?></div>
                    <div class="db-stat-label">Total Event</div>
                </div>
            </div>
            <div class="db-stat-card">
                <div class="db-stat-icon" style="background:rgba(78,172,145,0.12);color:var(--btn-lihat);">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div class="db-stat-info">
                    <div class="db-stat-value"><?= $totalPetugas ?></div>
                    <div class="db-stat-label">Petugas Aktif</div>
                </div>
            </div>
            <div class="db-stat-card">
                <div class="db-stat-icon" style="background:rgba(245,187,0,0.12);color:var(--orange);">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <div class="db-stat-info">
                    <div class="db-stat-value"><?= $totalApresiasi ?></div>
                    <div class="db-stat-label">Total Apresiasi</div>
                </div>
            </div>
            <div class="db-stat-card db-stat-alert <?= $totalMenunggu > 0 ? 'has-alert' : '' ?>"
                 onclick="<?= $totalMenunggu > 0 ? "window.location.href='laporan_sampah.php'" : '' ?>"
                 style="<?= $totalMenunggu > 0 ? 'cursor:pointer;' : '' ?>">
                <div class="db-stat-icon" style="background:rgba(217,93,57,0.12);color:var(--btn-hapus);">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="db-stat-info">
                    <div class="db-stat-value" style="color:var(--btn-hapus);"><?= $totalMenunggu ?></div>
                    <div class="db-stat-label">Laporan Menunggu</div>
                </div>
                <?php if ($totalMenunggu > 0): ?>
                    <div class="db-alert-badge"><i class="bi bi-arrow-right-short"></i></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-area">
        <div class="db-main-grid">

            <!-- Laporan butuh konfirmasi -->
            <div class="card-custom db-laporan-card">
                <div class="db-card-header">
                    <div class="db-card-title">
                        <i class="bi bi-exclamation-diamond-fill" style="color:var(--btn-hapus);"></i>
                        Laporan Perlu Konfirmasi
                        <?php if ($totalMenunggu > 0): ?>
                            <span class="pending-badge"><?= $totalMenunggu ?> menunggu</span>
                        <?php endif; ?>
                    </div>
                    <a href="laporan_sampah.php" class="db-lihat-semua">Lihat semua <i class="bi bi-arrow-right"></i></a>
                </div>

                <?php if (!empty($laporMenunggu)): ?>
                    <?php foreach ($laporMenunggu as $l): ?>
                    <div class="db-laporan-item" onclick="window.location.href='laporan_sampah.php'" style="cursor:pointer;">
                        <div class="db-laporan-dot"></div>
                        <div class="db-laporan-info">
                            <div class="db-laporan-jenis"><?= htmlspecialchars($l['jenis_sampah'] ?? '-') ?></div>
                            <div class="db-laporan-lokasi"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($l['lokasi'] ?? '-') ?></div>
                        </div>
                        <div class="db-laporan-time"><?= formatTanggal($l['created_at']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="db-empty">
                        <i class="bi bi-check-circle-fill" style="color:var(--status-selesai);font-size:32px;"></i>
                        <p>Tidak ada laporan yang perlu dikonfirmasi</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Access -->
            <div class="card-custom db-quick-card">
                <div class="db-card-header">
                    <div class="db-card-title">
                        <i class="bi bi-compass-fill" style="color:var(--status-selesai);"></i>
                        Quick Access
                    </div>
                </div>
                <div class="db-quick-grid">
                    <a href="laporan_sampah.php" class="db-quick-btn">
                        <i class="bi bi-file-text-fill"></i>
                        <span>Laporan Sampah</span>
                    </a>
                    <a href="apresiasi_rw.php" class="db-quick-btn">
                        <i class="bi bi-trophy-fill"></i>
                        <span>Apresiasi RW</span>
                    </a>
                    <a href="event_lingkungan.php" class="db-quick-btn">
                        <i class="bi bi-calendar-event-fill"></i>
                        <span>Event Lingkungan</span>
                    </a>
                    <a href="laporan_analitik.php" class="db-quick-btn">
                        <i class="bi bi-bar-chart-line-fill"></i>
                        <span>Laporan & Analitik</span>
                    </a>
                    <a href="data_petugas.php" class="db-quick-btn">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Data Petugas</span>
                    </a>
                    <a href="pengaturan_akun.php" class="db-quick-btn">
                        <i class="bi bi-gear-fill"></i>
                        <span>Pengaturan Akun</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Aktivitas Terbaru -->
        <div class="card-custom db-aktivitas-card">
            <div class="db-card-header">
                <div class="db-card-title">
                    <i class="bi bi-activity" style="color:var(--status-selesai);"></i>
                    Aktivitas Terbaru
                </div>
            </div>

            <?php if (!empty($logAktivitas)): ?>
                <?php foreach ($logAktivitas as $log):
                    $namaAdmin = $log['admin']['nama_admin'] ?? 'Admin';
                ?>
                <div class="db-log-item">
                    <div class="db-log-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                                 style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <i class="bi bi-person-fill" style="display:none;"></i>
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div class="db-log-content">
                        <div class="db-log-text"><?= htmlspecialchars($log['aktivitas'] ?? '-') ?></div>
                        <div class="db-log-meta">
                            <i class="bi bi-clock"></i> <?= formatTanggal($log['created_at'] ?? '') ?>
                            <span class="db-log-by">oleh <?= htmlspecialchars($namaAdmin) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="db-empty">
                    <i class="bi bi-inbox" style="font-size:36px;color:#8AA29E;"></i>
                    <p>Belum ada aktivitas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>