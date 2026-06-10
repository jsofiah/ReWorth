<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName = $_SESSION['nama_admin'] ?? 'User';
$userEmail = $_SESSION['email'] ?? 'user@example.com';
$userFoto = $_SESSION['foto_profil'] ?? '';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$fromTs = $dateFrom . 'T00:00:00';
$toTs = $dateTo . 'T23:59:59';

function sbGet($url, $key, $ep)
{
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json"
        ]
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}

function getSupabaseImageUrl($p)
{
    return empty($p) ? null : "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($p, '/');
}

$allLaporan = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan");
$totalLaporan = count($allLaporan);

$newLaporan = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.menunggu");
$totalBaru = count($newLaporan);

$selesaiList = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.selesai&created_at=gte." . urlencode($fromTs) . "&created_at=lte." . urlencode($toTs));
$totalSelesai = count($selesaiList);
$percentSelesai = $totalLaporan > 0 ? round(($totalSelesai / $totalLaporan) * 100, 1) : 0;

$ditolakList = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.ditolak");
$totalDitolak = count($ditolakList);
$percentDitolak = $totalLaporan > 0 ? round(($totalDitolak / $totalLaporan) * 100, 1) : 0;

$statusMenunggu = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.menunggu");
$statusDiproses = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.diproses");
$statusSelesaiCount = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.selesai");
$statusDitolakCount = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.ditolak");

$totalSemua = count($statusMenunggu) + count($statusDiproses) + count($statusSelesaiCount) + count($statusDitolakCount);
$persenSelesai = $totalSemua > 0 ? round((count($statusSelesaiCount) / $totalSemua) * 100) : 0;
$persenDiproses = $totalSemua > 0 ? round((count($statusDiproses) / $totalSemua) * 100) : 0;
$persenMenunggu = $totalSemua > 0 ? round((count($statusMenunggu) / $totalSemua) * 100) : 0;
$persenDitolak = $totalSemua > 0 ? round((count($statusDitolakCount) / $totalSemua) * 100) : 0;

$trendMonths = [];
for ($i = 2; $i >= 0; $i--) {
    $bulanTs = mktime(0, 0, 0, date('n') - $i, 1);
    $trendMonths[] = [
        'label'  => date('M Y', $bulanTs),
        'from'   => date('Y-m-01', $bulanTs) . 'T00:00:00',
        'to'     => date('Y-m-t', $bulanTs) . 'T23:59:59',
    ];
}

$trendData = [];
foreach ($trendMonths as $bln) {
    $selesai  = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.selesai&created_at=gte." . urlencode($bln['from']) . "&created_at=lte." . urlencode($bln['to'])));
    $diproses = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.diproses&created_at=gte." . urlencode($bln['from']) . "&created_at=lte." . urlencode($bln['to'])));
    $menunggu = count(sbGet($supabaseUrl, $supabaseKey, "/rest/v1/lapor_sampah?select=id_laporan&status=eq.menunggu&created_at=gte." . urlencode($bln['from']) . "&created_at=lte." . urlencode($bln['to'])));
    $trendData[] = [
        'label'    => $bln['label'],
        'selesai'  => $selesai,
        'diproses' => $diproses,
        'menunggu' => $menunggu,
    ];
}

$maxVal = 1;
foreach ($trendData as $d) {
    $maxVal = max($maxVal, $d['selesai'], $d['diproses'], $d['menunggu']);
}

function formatNumber($n)
{
    return number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan dan Analitik - DLH ReWorth</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><img src="img/logo.png" alt="Logo"></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="laporan_sampah.php" class="nav-link-custom"><i class="bi bi-file-text-fill"></i><span>Laporan Sampah</span></a></div>
        <div class="nav-item"><a href="apresiasi_rw.php" class="nav-link-custom"><i class="bi bi-trophy-fill"></i><span>Apresiasi RW</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="laporan_analitik.php" class="nav-link-custom active"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a></div>
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
            <h1 class="topbar-title">Laporan dan Analitik</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): ?>
                        <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="action-bar-wrap">
        <form method="GET" action="" class="filter-bar">
            <div class="date-range-wrap">
                <i class="bi bi-calendar3"></i>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                <span>—</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <button type="submit" class="btn-generate">Generate</button>
            <button type="button" class="btn-export" onclick="window.open('laporan_pdf.php?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>', '_blank')">
                <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
            </button>
        </form>
    </div>

    <div class="content-area">
     
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">TOTAL LAPORAN</div>
                <div class="stat-value"><?= formatNumber($totalLaporan) ?></div>
                <div class="stat-trend trend-up"><i class="bi bi-arrow-up-short"></i> 18% dari bulan lalu</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">LAPORAN BARU</div>
                <div class="stat-value"><?= formatNumber($totalBaru) ?></div>
                <div class="stat-trend">Menunggu konfirmasi</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">LAPORAN SELESAI</div>
                <div class="stat-value"><?= formatNumber($totalSelesai) ?></div>
                <div class="stat-trend"><?= $percentSelesai ?>% selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">LAPORAN DITOLAK</div>
                <div class="stat-value"><?= formatNumber($totalDitolak) ?></div>
                <div class="stat-trend"><?= $percentDitolak ?>% dari total</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Tren Laporan Bulanan</div>
                <div class="bar-chart">
                    <?php foreach ($trendData as $bln): ?>
                    <div class="bar-group">
                        <div class="bar-group-bars">
                            <div class="bar-col">
                                <div class="bar-value"><?= $bln['selesai'] > 0 ? $bln['selesai'] : '' ?></div>
                                <div class="bar bar-selesai" style="height:<?= max(($bln['selesai']/$maxVal)*100, $bln['selesai']>0?8:3) ?>px;"></div>
                            </div>
                            <div class="bar-col">
                                <div class="bar-value"><?= $bln['diproses'] > 0 ? $bln['diproses'] : '' ?></div>
                                <div class="bar bar-diproses" style="height:<?= max(($bln['diproses']/$maxVal)*100, $bln['diproses']>0?8:3) ?>px;"></div>
                            </div>
                            <div class="bar-col">
                                <div class="bar-value"><?= $bln['menunggu'] > 0 ? $bln['menunggu'] : '' ?></div>
                                <div class="bar bar-menunggu" style="height:<?= max(($bln['menunggu']/$maxVal)*100, $bln['menunggu']>0?8:3) ?>px;"></div>
                            </div>
                        </div>
                        <div class="bar-label"><?= $bln['label'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="bar-legend">
                    <span><span class="legend-dot selesai"></span> Selesai</span>
                    <span><span class="legend-dot diproses"></span> Diproses</span>
                    <span><span class="legend-dot menunggu"></span> Menunggu</span>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-title">Status Laporan</div>
                <div class="status-list">
                    <div class="status-item">
                        <div class="status-header">
                            <span class="status-name">Selesai</span>
                            <span class="status-percent selesai"><?= $persenSelesai ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill selesai" style="width: <?= $persenSelesai ?>%;"></div>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-header">
                            <span class="status-name">Diproses</span>
                            <span class="status-percent diproses"><?= $persenDiproses ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill diproses" style="width: <?= $persenDiproses ?>%;"></div>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-header">
                            <span class="status-name">Menunggu</span>
                            <span class="status-percent menunggu"><?= $persenMenunggu ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill menunggu" style="width: <?= $persenMenunggu ?>%;"></div>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-header">
                            <span class="status-name">Ditolak</span>
                            <span class="status-percent ditolak"><?= $persenDitolak ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill ditolak" style="width: <?= $persenDitolak ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>