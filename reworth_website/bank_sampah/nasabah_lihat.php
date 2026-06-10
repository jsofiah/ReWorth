<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

function getSupabaseImageUrl($path) {
    if (empty($path)) return null;
    return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
}

function getNasabahById($supabaseUrl, $supabaseKey, $id) {
    $url = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $id . "&select=*";
    $headers = [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response !== false) {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
    return null;
}

function getWilayahById($supabaseUrl, $supabaseKey, $idWilayah) {
    if (empty($idWilayah)) return null;
    
    $url = $supabaseUrl . "/rest/v1/wilayah?id_wilayah=eq." . $idWilayah . "&select=*";
    $headers = [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response !== false) {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
    return null;
}

$idNasabah = $_GET['id'] ?? '';
if (empty($idNasabah)) {
    header("Location: data_nasabah.php");
    exit;
}

$nasabah = getNasabahById($supabaseUrl, $supabaseKey, $idNasabah);
if (!$nasabah) {
    header("Location: data_nasabah.php?error=notfound");
    exit;
}

$wilayah = getWilayahById($supabaseUrl, $supabaseKey, $nasabah['id_wilayah'] ?? '');

$userName = $_SESSION['nama_admin'] ?? 'User';
$userEmail = $_SESSION['email'] ?? 'user@example.com';
$userFoto = $_SESSION['foto_profil'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Nasabah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="transaksi_setor_sampah.php" class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
            <div class="nav-item"><a href="penarikan_saldo.php" class="nav-link-custom"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
            <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
            <div class="nav-item"><a href="jadwal_ambil_sampah.php" class="nav-link-custom"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="data_nasabah.php" class="nav-link-custom active"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
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
                <h1 class="topbar-title">Detail Nasabah</h1>
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

        <div class="setting-bar-wrap">
            <div class="settings-card">
                <div class="card-accent"></div>
                <div class="card-body-inner">
                    <div class="foto-section">
                        <div class="foto-wrapper">
                            <?php if (!empty($nasabah['foto_profil'])): ?>
                                <img src="<?= htmlspecialchars(getSupabaseImageUrl($nasabah['foto_profil'])) ?>"
                                    id="previewFoto"
                                    alt="Foto Profil"
                                    onerror="this.style.display='none';document.getElementById('fallbackIcon').style.display='flex';">
                                <i class="bi bi-person-fill fallback-icon"
                                id="fallbackIcon"
                                style="display:none;"></i>
                            <?php else: ?>
                                <img src=""
                                    id="previewFoto"
                                    style="display:none;">
                                <i class="bi bi-person-fill fallback-icon"
                                id="fallbackIcon"></i>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-section-settings">
                        <div class="field-group">
                            <label class="field-label">Nama Lengkap</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($nasabah['nama_lengkap'] ?? '-') ?>" readonly disabled>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Email</label>
                            <input type="email" class="field-input" value="<?= htmlspecialchars($nasabah['email'] ?? '-') ?>" readonly disabled>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Total Poin</label>
                            <input type="text" class="field-input" value="<?= number_format($nasabah['poin'] ?? 0, 0, ',', '.') ?>" readonly disabled>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Saldo Tabungan</label>
                            <input type="text" class="field-input" value="Rp <?= number_format($nasabah['saldo_tabungan'] ?? 0, 0, ',', '.') ?>" readonly disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="setting-content-area">
            <div class="settings-card password-card">
                <div class="card-accent"></div>
                <div class="card-body-inner password-section">
                    <h2 class="section-title">Profil Detail</h2>
                    <div class="two-columns">
                        <div class="field-group">
                            <label class="field-label">Nama Lengkap</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($nasabah['nama_lengkap'] ?? '-') ?>" readonly disabled>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">Email</label>
                            <input type="email" class="field-input" value="<?= htmlspecialchars($nasabah['email'] ?? '-') ?>" readonly disabled>
                        </div>
                    </div>

                    <div class="two-columns">
                        <div class="field-group">
                            <label class="field-label">No Telepon</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($nasabah['no_telepon'] ?? '-') ?>" readonly disabled>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">Alamat</label>
                            <textarea class="field-input" rows="3" readonly disabled><?= htmlspecialchars($nasabah['alamat_detail'] ?? '-') ?></textarea>
                        </div>
                    </div>

                    <div class="three-columns">
                        <div class="field-group">
                            <label class="field-label">Kecamatan</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($wilayah['kecamatan'] ?? '-') ?>" readonly disabled>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">Kelurahan</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($wilayah['kelurahan'] ?? '-') ?>" readonly disabled>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">RW</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($wilayah['rw'] ?? '-') ?>" readonly disabled>
                        </div>
                    </div>

                    <a href="data_nasabah.php" class="btn-submit" style="text-align: center; text-decoration: none; display: inline-block; margin-top: 20px;">
                        <span class="btn-text">Kembali</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>