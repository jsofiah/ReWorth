<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $petugasId = $_GET['id'] ?? '';
    if (empty($petugasId)) {
        header("Location: data_petugas.php");
        exit;
    }

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function supabaseGet($supabaseUrl, $supabaseKey, $endpoint) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $supabaseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) return json_decode($response, true) ?: [];
        return [];
    }

    // Fetch petugas from petugas_lapangan
    $result  = supabaseGet($supabaseUrl, $supabaseKey,
        "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($petugasId) . "&select=*"
    );
    $petugas = $result[0] ?? null;

    if (!$petugas) {
        header("Location: data_petugas.php");
        exit;
    }

    // Riwayat tugas dari lapor_sampah — kolom: id_laporan, jenis_sampah, lokasi, created_at, status
    $riwayatTugas = supabaseGet($supabaseUrl, $supabaseKey,
        "/rest/v1/lapor_sampah?id_petugas=eq." . urlencode($petugasId) .
        "&select=id_laporan,jenis_sampah,lokasi,created_at,status&order=created_at.desc"
    );

    $isAktif        = $petugas['status_aktif'] === true || $petugas['status_aktif'] === 'true';
    $statusText     = $isAktif ? 'Aktif' : 'Nonaktif';
    $badgeClass     = $isAktif ? 'status-berlangsung' : 'status-selesai';
    $terdaftarSejak = !empty($petugas['created_at'])
        ? date('d F Y', strtotime($petugas['created_at']))
        : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Petugas - DLH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
            </div>
            <div class="nav-item">
                <a href="laporan_sampah.php" class="nav-link-custom"><i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span></a>
            </div>
            <div class="nav-item">
                <a href="apresiasi_rw.php" class="nav-link-custom"><i class="bi bi-award-fill"></i><span>Apresiasi RW</span></a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a>
            </div>
            <div class="nav-item">
                <a href="laporan_analitik.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a>
            </div>
            <div class="nav-item">
                <a href="data_petugas.php" class="nav-link-custom active"><i class="bi bi-people-fill"></i><span>Data Petugas</span></a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a>
            </div>
        </nav>
        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Data Petugas</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="petugas-lihat-wrap">

            <!-- Profil Card — overlap topbar -->
            <div class="petugas-profile-card">
                <!-- Foto berdiri sendiri di kiri card, dengan accent coklat -->
                <div class="petugas-photo-wrap">
                    <div class="petugas-photo-accent"></div>
                    <div class="petugas-photo-box">
                        <?php if (!empty($petugas['foto_profil'])): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($petugas['foto_profil'])) ?>"
                                alt="Foto Petugas"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="photo-fallback" style="display:none;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        <?php else: ?>
                            <div class="photo-fallback">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info petugas -->
                <div class="petugas-info">
                    <h2><?= htmlspecialchars($petugas['nama_petugas'] ?? '-') ?></h2>
                    <div class="sub-role">Petugas Lapangan &middot; DLH</div>
                    <div class="meta-row">
                        <span class="status-badge <?= $badgeClass ?>"><?= $statusText ?></span>
                        <span><?= htmlspecialchars($petugas['no_telepon'] ?? '-') ?></span>
                    </div>
                    <div class="meta-terdaftar">
                        Terdaftar Sejak &middot; <?= $terdaftarSejak ?>
                    </div>
                </div>
            </div>

            <!-- Riwayat Tugas -->
            <div class="riwayat-card">
                <h3>Riwayat Tugas</h3>

                <?php if (!empty($riwayatTugas)): ?>
                    <?php foreach ($riwayatTugas as $t):
                        $tgl         = !empty($t['created_at']) ? date('d M Y', strtotime($t['created_at'])) : '-';
                        $statusTugas = ucfirst($t['status'] ?? '-');
                        $badgeTugas  = strtolower($t['status'] ?? '') === 'selesai'
                            ? 'status-berlangsung'
                            : 'status-diproses';
                    ?>
                    <div class="tugas-item">
                        <div>
                            <div class="tugas-kode"><?= htmlspecialchars($t['id_laporan'] ?? '-') ?></div>
                            <div class="tugas-meta">
                                <?= htmlspecialchars(ucfirst($t['jenis_sampah'] ?? '-')) ?>
                                &middot; <?= htmlspecialchars($t['lokasi'] ?? '-') ?>
                                &middot; <?= $tgl ?>
                            </div>
                        </div>
                        <span class="status-badge <?= $badgeTugas ?>"><?= htmlspecialchars($statusTugas) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4" style="color:#6B8A7E;">
                        <i class="bi bi-clipboard-x" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                        Belum ada riwayat tugas
                    </div>
                <?php endif; ?>

                <div style="margin-top:20px;">
                    <button class="btn-cancel" onclick="window.location.href='data_petugas.php'">
                        Kembali
                    </button>
                </div>
            </div>

        </div>
    </div>
</body>
</html>