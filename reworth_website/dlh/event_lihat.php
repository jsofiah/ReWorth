<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $eventId = $_GET['id'] ?? '';
    if (empty($eventId)) {
        header("Location: event_lingkungan.php");
        exit;
    }

    function getEventDetail($supabaseUrl, $supabaseKey, $eventId) {
        $url = $supabaseUrl . "/rest/v1/event?id_event=eq." . $eventId . "&select=*";
        $headers = ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }

    $event = getEventDetail($supabaseUrl, $supabaseKey, $eventId);
    if (!$event) {
        header("Location: event_lingkungan.php");
        exit;
    }

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    $statusMap = ['akan_datang' => 'Akan Datang', 'berlangsung' => 'Berlangsung', 'selesai' => 'Selesai'];
    $statusText = $statusMap[$event['status'] ?? 'akan_datang'] ?? 'Akan Datang';
    $statusClass = $event['status'] ?? 'akan_datang';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Event - DLH</title>
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
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_sampah.php" class="nav-link-custom">
                    <i class="bi bi-exclamation-diamond-fill"></i>
                    <span>Laporan Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="apresiasi_rw.php" class="nav-link-custom">
                    <i class="bi bi-award-fill"></i>
                    <span>Apresiasi RW</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom active">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>Event Lingkungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_analitik.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Analitik</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_petugas.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Petugas</span>
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
                <h1 class="topbar-title">Event Lingkungan</h1>
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

        <div class="event-banner-wrap">
            <?php if (!empty($event['foto_event'])): ?>
                <img 
                    src="<?= getSupabaseImageUrl($event['foto_event']) ?>" 
                    class="event-banner-image"
                    alt="Foto Event"
                >
            <?php else: ?>
                <div class="event-banner-placeholder">
                    <i class="bi bi-image"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="content-area">
            <div class="form-container">
                <div class="form-section-lihat">
                    <div class="row-2cols">
                        <div class="form-group">
                            <label class="form-label">
                                Nama Event
                            </label>
                            <div class="form-control-custom">
                                <?= htmlspecialchars($event['nama_event'] ?? '-') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Narasumber
                            </label>

                            <div class="form-control-custom">
                                <?= htmlspecialchars($event['narasumber'] ?? '-') ?>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Deskripsi
                        </label>

                        <div class="form-control-custom" style="min-height:120px;">
                            <?= nl2br(htmlspecialchars($event['deskripsi'] ?? '-')) ?>
                        </div>
                    </div>

                    <div class="row-2cols">

                        <div class="form-group">
                            <label class="form-label">
                                Maksimal Peserta
                            </label>

                            <div class="form-control-custom">
                                <?= ($event['max_partisipan'] ?? 0) == 0 
                                    ? 'Tidak terbatas' 
                                    : $event['max_partisipan'] . ' orang' ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Status Event
                            </label>

                            <div class="form-control-custom">
                                <?= $statusText ?>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Lokasi / Alamat
                        </label>

                        <div class="form-control-custom">
                            <?= htmlspecialchars($event['lokasi'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="row-2cols">

                        <div class="form-group">
                            <label class="form-label">
                                Tanggal
                            </label>

                            <div class="form-control-custom">
                                <?= !empty($event['tanggal']) 
                                    ? date('d F Y', strtotime($event['tanggal'])) 
                                    : '-' ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Waktu Mulai
                            </label>

                            <div class="form-control-custom">
                                <?= !empty($event['waktu']) 
                                    ? substr($event['waktu'], 0, 5) . ' WIB'
                                    : '-' ?>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Persyaratan Peserta
                        </label>

                        <div class="form-control-custom" style="min-height:100px;">
                            <?= nl2br(htmlspecialchars($event['persyaratan'] ?? '-')) ?>
                        </div>
                    </div>

                    <?php if (!empty($event['latitude']) || !empty($event['longitude'])): ?>
                        <div class="form-group">
                            <label class="form-label">
                                Lokasi Koordinat
                            </label>

                            <div class="row-2cols">
                                <div class="form-control-custom">
                                    <?= htmlspecialchars($event['latitude'] ?? '-') ?>
                                </div>

                                <div class="form-control-custom">
                                    <?= htmlspecialchars($event['longitude'] ?? '-') ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">

                        <button 
                            type="button"
                            class="btn-cancel"
                            onclick="window.location.href='event_lingkungan.php'"
                        >
                            Kembali
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </div>
</body>
</html>