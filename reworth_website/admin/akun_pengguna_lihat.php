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
        if (empty($path)) {
            return null;
        }
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    $idPengguna = $_GET['id'] ?? '';
    if (empty($idPengguna)) {
        header("Location: kelola_akun.php?tab=pengguna");
        exit;
    }

    function getPenggunaById($supabaseUrl, $supabaseKey, $id) {
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

    $pengguna = getPenggunaById($supabaseUrl, $supabaseKey, $idPengguna);
    if (!$pengguna) {
        header("Location: kelola_akun.php?tab=pengguna&error=notfound");
        exit;
    }

    $wilayah = getWilayahById($supabaseUrl, $supabaseKey, $pengguna['id_wilayah'] ?? '');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Kelola Akun</title>
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
                <a href="kelola_akun.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Kelola Akun</h1>
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
                    <div class="foto-section">
                        <div class="foto-wrapper">
                            <?php if (!empty($pengguna['foto_profil'])): ?>
                                <img src="<?= htmlspecialchars(getSupabaseImageUrl($pengguna['foto_profil'])) ?>"
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
                            <input type="text" class="field-input" value="<?= htmlspecialchars($pengguna['nama_lengkap'] ?? '-') ?>" readonly disabled>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Email</label>
                            <input type="email" class="field-input" value="<?= htmlspecialchars($pengguna['email'] ?? '-') ?>" readonly disabled>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Total Poin</label>
                            <input type="text" class="field-input" value="<?= number_format($pengguna['poin'] ?? 0, 0, ',', '.') ?>" readonly disabled>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Saldo Tabungan</label>
                            <input type="text" class="field-input" value="Rp <?= number_format($pengguna['saldo_tabungan'] ?? 0, 0, ',', '.') ?>" readonly disabled>
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
                            <input type="text" class="field-input" value="<?= htmlspecialchars($pengguna['nama_lengkap'] ?? '-') ?>" readonly disabled>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">Email</label>
                            <input type="email" class="field-input" value="<?= htmlspecialchars($pengguna['email'] ?? '-') ?>" readonly disabled>
                        </div>
                    </div>

                    <div class="two-columns">
                        <div class="field-group">
                            <label class="field-label">No Telepon</label>
                            <input type="text" class="field-input" value="<?= htmlspecialchars($pengguna['no_telepon'] ?? '-') ?>" readonly disabled>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">Alamat</label>
                            <textarea class="field-input" rows="3" readonly disabled><?= htmlspecialchars($pengguna['alamat_detail'] ?? '-') ?></textarea>
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

                    <a href="kelola_akun.php?tab=pengguna" class="btn-submit" style="text-align: center; text-decoration: none; display: inline-block; margin-top: 20px;">
                        <span class="btn-text">Kembali</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>