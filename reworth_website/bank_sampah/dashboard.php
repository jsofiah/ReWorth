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
    $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
    return $bucketUrl . ltrim($path, '/');
}

function sbGet($url, $key, $ep) {
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}

// 1. Fetch jumlah event
$events = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/event?select=id_event");
$totalEvent = count($events);

// 2. Fetch jumlah transaksi setor
$setor = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/setor_sampah?select=id_setor");
$totalSetor = count($setor);

// 3. Fetch jumlah data nasabah
$nasabah = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/pengguna?select=id_pengguna");
$totalNasabah = count($nasabah);

// 4. Fetch jumlah data setor menunggu konfirmasi
$menunggu = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/setor_sampah?select=id_setor&status=in.(menunggu,diproses)");
$totalMenunggu = count($menunggu);

// 5. Fetch 5 aktivitas terbaru (Transaksi Setor Sampah)
$aktivitas = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/setor_sampah?select=id_setor,created_at,status,pengguna(nama_lengkap)&order=created_at.desc&limit=5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah – Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    </head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Bank Sampah Kota Malang">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom active"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="transaksi_setor_sampah.php" class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
            <div class="nav-item"><a href="penarikan_saldo.php" class="nav-link-custom"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
            <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
            <div class="nav-item"><a href="jadwal_ambil_sampah.php" class="nav-link-custom"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
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
                <h1 class="topbar-title">Dashboard</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <i class="bi bi-person-fill" style="display: none;"></i>
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area" style="padding: 24px;">
            <!-- Top Stats -->
            <div class="stats-grid dashboard-stats">
                <div class="stat-card">
                    <div class="stat-label">Jumlah Nasabah</div>
                    <div class="stat-value"><i class="bi bi-people-fill me-2" style="font-size: 24px; color: #6B8A7E;"></i><?= $totalNasabah ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Transaksi Setor</div>
                    <div class="stat-value"><i class="bi bi-recycle me-2" style="font-size: 24px; color: #6B8A7E;"></i><?= $totalSetor ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Jumlah Event</div>
                    <div class="stat-value"><i class="bi bi-calendar-event-fill me-2" style="font-size: 24px; color: #6B8A7E;"></i><?= $totalEvent ?></div>
                </div>
                <div class="stat-card alert-card" onclick="window.location.href='transaksi_setor_sampah.php'">
                    <div class="stat-label" style="color:#D97706;">Butuh Konfirmasi</div>
                    <div class="stat-value" style="color:#D97706;"><i class="bi bi-exclamation-circle-fill me-2" style="font-size: 24px;"></i><?= $totalMenunggu ?></div>
                    <div style="font-size:10px; color:#D97706; margin-top:8px; font-weight:600;">KLIK UNTUK MELIHAT &rarr;</div>
                </div>
            </div>

            <!-- Bottom Sections -->
            <div class="charts-grid">
                <!-- Quick Access -->
                <div class="chart-card">
                    <div class="chart-title"><i class="bi bi-lightning-charge-fill me-2" style="color:#FFB347;"></i>Quick Access</div>
                    <div class="qa-grid">
                        <a href="transaksi_setor_sampah.php" class="qa-btn">
                            <i class="bi bi-recycle"></i> Transaksi Setor
                        </a>
                        <a href="event_lingkungan.php" class="qa-btn">
                            <i class="bi bi-calendar-event-fill"></i> Event Lingkungan
                        </a>
                        <a href="data_nasabah.php" class="qa-btn">
                            <i class="bi bi-people-fill"></i> Data Nasabah
                        </a>
                        <a href="data_sampah.php" class="qa-btn">
                            <i class="bi bi-trash-fill"></i> Data Sampah
                        </a>
                    </div>
                </div>

                <!-- Aktivitas Terbaru -->
                <div class="chart-card">
                    <div class="chart-title"><i class="bi bi-activity me-2" style="color:var(--green);"></i>Aktivitas Setor Terbaru</div>
                    <div class="aktivitas-list">
                            <?php if (empty($aktivitas)): ?>
                                <div style="text-align:center; color:#9AA7A2; font-size:13px; padding:20px;">Belum ada aktivitas.</div>
                            <?php else: ?>
                                <?php foreach ($aktivitas as $akt): 
                                    $status = strtolower($akt['status'] ?? '');
                                    $nama = htmlspecialchars($akt['pengguna']['nama_lengkap'] ?? 'Tanpa Nama');
                                    $waktu = !empty($akt['created_at']) ? date('d M Y, H:i', strtotime($akt['created_at'])) : '-';
                                    
                                    $iconClass = 'akt-icon';
                                    $iconBi = 'bi-check2-circle';
                                    $pesan = "Transaksi setor selesai oleh $nama";
                                    
                                    if ($status === 'menunggu' || $status === 'diproses') {
                                        $iconClass .= ' warn';
                                        $iconBi = 'bi-clock-history';
                                        $pesan = "Menunggu konfirmasi setor dari $nama";
                                    } elseif ($status === 'ditolak') {
                                        $iconClass .= ' danger';
                                        $iconBi = 'bi-x-circle';
                                        $pesan = "Transaksi setor oleh $nama ditolak";
                                    }
                                ?>
                                <div class="aktivitas-item">
                                    <div class="<?= $iconClass ?>">
                                        <i class="bi <?= $iconBi ?>"></i>
                                    </div>
                                    <div class="akt-content">
                                        <div class="akt-title"><?= $pesan ?></div>
                                        <div class="akt-time"><?= $waktu ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                    </div>
                    <?php if (!empty($aktivitas)): ?>
                    <div style="text-align:center; margin-top:16px;">
                        <a href="transaksi_setor_sampah.php" style="font-size:12px; color:var(--green); font-weight:600; text-decoration:none;">Lihat semua aktivitas &rarr;</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>