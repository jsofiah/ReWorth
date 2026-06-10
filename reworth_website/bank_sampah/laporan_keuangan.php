<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? 'user@example.com';
$userFoto  = $_SESSION['foto_profil'] ?? '';


$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo   = $_GET['date_to']   ?? date('Y-12-31');

$fromTs = $dateFrom . 'T00:00:00';
$toTs   = $dateTo   . 'T23:59:59';

$labelFrom = date('d M Y', strtotime($dateFrom));
$labelTo   = date('d M Y', strtotime($dateTo));

function getSupabaseImageUrl($p) {
    return empty($p) ? null : "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($p, '/');
}
function sbGet($url, $key, $ep) {
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}


$penggunaList     = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pengguna?select=id_pengguna");
$totalNasabah     = count($penggunaList);

$setorList = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?select=id_setor,total_uang,created_at,detail_setor(berat,jenis_sampah(nama_sampah))"
    . "&created_at=gte." . urlencode($fromTs)
    . "&created_at=lte." . urlencode($toTs));

$transaksiSetor  = count($setorList);
$totalNilaiSetor = array_sum(array_column($setorList, 'total_uang'));
$beratTerkumpul  = 0;
$jenisTotals     = [];

foreach ($setorList as $s) {
    foreach ($s['detail_setor'] ?? [] as $d) {
        $beratTerkumpul += (float)($d['berat'] ?? 0);
        $nama = $d['jenis_sampah']['nama_sampah'] ?? 'Lainnya';
        $jenisTotals[$nama] = ($jenisTotals[$nama] ?? 0) + (float)($d['berat'] ?? 0);
    }
}
arsort($jenisTotals);
$topJenis   = array_slice($jenisTotals, 0, 4, true);
$totalBerat = array_sum($jenisTotals) ?: 1;

$eventAktif       = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/event?select=id_event&or=(status.eq.berlangsung,status.eq.akan_datang)");
$jumlahEventAktif = count($eventAktif);

$pendaftarList   = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pendaftar_event?select=id_pendaftar_event");
$jumlahPendaftar = count($pendaftarList);

$tukarList = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/tukar_poin?select=id_tukar,reward(poin_dibutuhkan)");
$totalPoin = 0;
foreach ($tukarList as $t) { $totalPoin += (int)($t['reward']['poin_dibutuhkan'] ?? 0); }

$setorTahun   = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?select=created_at&created_at=gte." . urlencode(date('Y') . '-01-01T00:00:00'));
$monthlyCount = array_fill(1, 12, 0);
foreach ($setorTahun as $s) {
    $m = (int)date('n', strtotime($s['created_at']));
    $monthlyCount[$m]++;
}

$curMonth    = (int)date('n');
$monthNames  = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGU','SEP','OKT','NOV','DES'];
$trendLabels = $trendValues = [];
for ($m = 1; $m <= $curMonth; $m++) {
    $trendLabels[] = $monthNames[$m-1];
    $trendValues[] = $monthlyCount[$m];
}

function fmtRp($n)  { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function fmtKg($n)  { return number_format((float)$n, 0, ',', '.') . ' kg'; }
function fmtNum($n) { return number_format((int)$n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bank Sampah – Laporan &amp; Analitik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">

    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    </head>
<body>


<aside class="sidebar">
    <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="transaksi_setor_sampah.php" class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
        <div class="nav-item"><a href="penarikan_saldo.php" class="nav-link-custom"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="jadwal_ambil_sampah.php" class="nav-link-custom"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
        <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom active"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
        <div class="nav-item"><a href="data_nasabah.php" class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
        <div class="nav-item"><a href="data_sampah.php" class="nav-link-custom"><i class="bi bi-trash-fill"></i><span>Data Sampah</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
</aside>

<div class="main-wrap">

    
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Laporan &amp; Analitik</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): $fu = getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fu) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    
    <div class="action-bar-wrap">
        <form method="GET" action="" class="laporan-bar" id="filterForm">

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <i class="bi bi-calendar3" style="font-size:22px;color:var(--green);"></i>

            <input type="date"
                name="date_from"
                value="<?= htmlspecialchars($dateFrom) ?>"
                style="padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;">

            <span>–</span>

            <input type="date"
                name="date_to"
                value="<?= htmlspecialchars($dateTo) ?>"
                style="padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;">
        </div>

    <button type="submit" class="btn-generate">
        Generate
    </button>

    <button type="button" class="btn-export" onclick="exportPDF()">
        <i class="bi bi-file-earmark-pdf"></i> Export PDF
    </button>
    </div>

    
    <div class="content-area">

        
        <div class="pdf-hint" style="margin-bottom:16px;">
            <i class="bi bi-info-circle-fill" style="color:var(--green);font-size:16px;"></i>
            Menampilkan data periode
            <strong><?= $labelFrom ?> – <?= $labelTo ?></strong>.
            Klik <a href="laporan_pdf.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" target="_blank">Export PDF</a>
            untuk laporan resmi yang bisa dicetak.
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Nasabah</div>
                <div class="stat-value"><?= fmtNum($totalNasabah) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Transaksi Setor</div>
                <div class="stat-value"><?= fmtNum($transaksiSetor) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Berat Terkumpul</div>
                <div class="stat-value"><?= fmtKg($beratTerkumpul) ?></div>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Nilai Setor</div>
                <div class="stat-value green"><?= fmtRp($totalNilaiSetor) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Event Aktif</div>
                <div class="stat-value"><?= fmtNum($jumlahEventAktif) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pendaftar Event</div>
                <div class="stat-value"><?= fmtNum($jumlahPendaftar) ?></div>
            </div>
        </div>

        
        <div class="charts-grid">

            <div class="chart-card">
                <div class="chart-title">Tren Setor Sampah<br>per Bulan</div>
                <div style="position:relative;height:200px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title">Jenis Sampah Terbanyak</div>
                <?php
                $colors = ['c0','c1','c2','c3']; $ci = 0;
                foreach ($topJenis as $nama => $berat):
                    $pct = round(($berat / $totalBerat) * 100);
                ?>
                <div class="jenis-item">
                    <div class="jenis-header">
                        <span class="jenis-name"><?= htmlspecialchars($nama) ?></span>
                        <span class="jenis-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="jenis-track">
                        <div class="jenis-fill <?= $colors[$ci % 4] ?>" style="width:<?= $pct ?>%;"></div>
                    </div>
                </div>
                <?php $ci++; endforeach; ?>
                <?php if (empty($topJenis)): ?>
                <p style="color:#9AA7A2;font-size:13px;text-align:center;padding:20px 0;">
                    Belum ada data sampah pada periode ini.
                </p>
                <?php endif; ?>

                <div class="poin-section">
            <div class="poin-title">Total Nilai Setor Sampah</div>
            <div class="poin-row">
                <div class="poin-dot"></div>
                <span>Akumulasi nilai setor</span>
                <span class="poin-val"><?= fmtRp($totalNilaiSetor) ?></span>
            </div>
        </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>


new Chart(document.getElementById('trendChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels  : <?= json_encode($trendLabels) ?>,
        datasets: [{
            data           : <?= json_encode($trendValues) ?>,
            backgroundColor: '#00916E',
            borderRadius   : 6,
            borderSkipped  : false,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: c => c.parsed.y + ' transaksi' } }
        },
        scales: {
            x: {
                grid : { display: false },
                ticks: { font: { family: 'Poppins', size: 11 }, color: '#6B8A7E' }
            },
            y: {
                grid : { color: '#EEF5F1' },
                ticks: { font: { family: 'Poppins', size: 11 }, color: '#6B8A7E', precision: 0, stepSize: 1 },
                beginAtZero: true
            }
        }
    }
});


function exportPDF() {
    const from = document.querySelector('input[name="date_from"]').value;
    const to   = document.querySelector('input[name="date_to"]').value;

    window.open(
        'laporan_pdf.php?date_from=' + encodeURIComponent(from) +
        '&date_to=' + encodeURIComponent(to),
        '_blank'
    );
}
</script>
</body>
</html>