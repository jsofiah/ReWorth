<?php
    session_start();

    if (!isset($_SESSION['id_penjual'])) {
        header("Location: login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
    
    require_once 'subscription_check.php';

    $subscription = getSubscriptionStatus($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);
    $isPremium = $subscription['is_premium'];
    $remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

    if ($isPremium && $remainingDays <= 7) {
        $warning = "Langganan akan berakhir dalam $remainingDays hari";
    }

    $userName  = $_SESSION['nama_penjual'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';
    $userId    = $_SESSION['id_penjual'] ?? '';

    function getSupabaseImageUrl($path) {

        if (empty($path)) {
            return null;
        }

        $bucketUrl =
            "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";

        return $bucketUrl . ltrim($path, '/');
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penjual – Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Penjual Premium">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom active">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="produk.php" class="nav-link-custom">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>Manajemen Produk</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pesanan.php" class="nav-link-custom">
                    <i class="bi bi-bag-check-fill"></i>
                    <span>Manajemen Pesanan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="langganan.php" class="nav-link-custom">
                    <i class="bi bi-stars"></i>
                    <span>Langganan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pembayaran_komisi.php" class="nav-link-custom">
                    <i class="bi bi-cash-coin"></i>
                    <span>Pembayaran Komisi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_toko.php" class="nav-link-custom">
                    <i class="bi bi-shop-window"></i>
                    <span>Pengaturan Toko</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_premium.php" class="nav-link-custom">
                    <i class="bi bi-gem"></i>
                    <span>Pengaturan Premium</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-logout">
            <a class="logout-btn" href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Dashboard - Penjual</h1>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>