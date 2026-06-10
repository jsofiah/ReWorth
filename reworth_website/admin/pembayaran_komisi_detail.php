<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userRole = $_SESSION['role'] ?? '';
    $userFoto = $_SESSION['foto_profil'] ?? '';
    $userId = $_SESSION['id_admin'] ?? '';

    $idKomisi = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idKomisi)) {
        echo '<div class="alert alert-danger m-3">ID Komisi tidak ditemukan.</div>';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'konfirmasi') {
        header('Content-Type: application/json');
        
        $updateData = [
            'status_pembayaran' => 'selesai',
            'tanggal_pembayaran' => date('Y-m-d')
        ];
        
        $url = $supabaseUrl . "/rest/v1/komisi?id_komisi=eq." . $idKomisi;
        
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 204) {
            $logData = [
                'id_admin' => $_SESSION['id_admin'] ?? '',
                'aktivitas' => 'Mengkonfirmasi pembayaran komisi',
                'tabel_terkait' => 'komisi',
                'id_data' => $idKomisi,
            ];
            
            $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
            curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($logCh, CURLOPT_POSTFIELDS, json_encode($logData));
            curl_setopt($logCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json"
            ]);
            curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($logCh);
            curl_close($logCh);
            
            echo json_encode(['success' => true, 'message' => 'Komisi berhasil dikonfirmasi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengkonfirmasi komisi']);
        }
        exit;
    }

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

    function formatRupiah($angka) {
        if (empty($angka)) return 'Rp 0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    function getStatusBadge($status) {
        $status = strtolower(trim($status));
        if ($status == 'selesai') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'dibayar') {
            return '<span class="status-badge status-berlangsung">Menunggu Konfirmasi</span>';
        } elseif ($status == 'pending') {
            return '<span class="status-badge status-akan_datang">Belum Membayar</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Menunggu Verifikasi</span>';
        }
    }

    $url = $supabaseUrl . "/rest/v1/komisi?id_komisi=eq.$idKomisi&select=*,penjual(nama_penjual)";

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $komisi = null;

    if ($response && $httpCode == 200) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $komisi = $data[0];
        }
    }

    if (!$komisi) {
        echo '<div class="alert alert-danger m-3">Data komisi tidak ditemukan. ID: ' . htmlspecialchars($idKomisi) . '</div>';
        exit;
    }

    $penjual = $komisi['penjual'] ?? [];
    $namaPenjual = $penjual['nama_penjual'] ?? '-';
    
    $totalKomisi = $komisi['total_komisi'] ?? 0;
    $periodeBulan = $komisi['periode_bulan'] ?? '-';
    $status = $komisi['status_pembayaran'] ?? 'pending';
    $tanggalPembayaran = $komisi['tanggal_pembayaran'] ?? null;
    $buktiPembayaran = $komisi['bukti_pembayaran'] ?? null;

    $tanggalBayarFormatted = $tanggalPembayaran ? formatTanggalIndonesia($tanggalPembayaran) : null;
    $fotoBuktiUrl = getSupabaseImageUrl($buktiPembayaran);
    
    $isStatusDibayar = (strtolower(trim($status)) == 'dibayar');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Komisi - Pembayaran Komisi</title>
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
                <a href="monitor_transaksi.php" class="nav-link-custom">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Monitor Transaksi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pembayaran_komisi.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Detail Komisi</h1>
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
                            <h3>Detail Komisi</h3>
                            <h1 class="event-title"><?= htmlspecialchars($namaPenjual) ?></h1>
                        </div>
                        <div class="detail-info-left">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Nama Penjual/Toko</div>
                                        <div class="info-value"><?= htmlspecialchars($namaPenjual) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Total Komisi</div>
                                        <div class="info-value large"><?= formatRupiah($totalKomisi) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-calendar-month"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Periode Bulan</div>
                                        <div class="info-value"><?= htmlspecialchars($periodeBulan) ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-info-circle"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Status Pembayaran</div>
                                        <div class="info-value"><?= getStatusBadge($status) ?></div>
                                    </div>
                                </div>

                                <?php if ($isStatusDibayar && $tanggalBayarFormatted): ?>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Tanggal Pembayaran</div>
                                        <div class="info-value"><?= htmlspecialchars($tanggalBayarFormatted) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="detail-photo-right">
                            <div class="photo-container">
                                <?php if ($fotoBuktiUrl): ?>
                                    <img src="<?= htmlspecialchars($fotoBuktiUrl) ?>" 
                                        alt="Bukti Pembayaran"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="photo-placeholder" style="display: none;">
                                        <i class="bi bi-receipt"></i>
                                        <p>Bukti pembayaran tidak tersedia</p>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <i class="bi bi-receipt"></i>
                                        <p>Belum ada bukti pembayaran</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; margin-top: 8px; font-size: 11px; color: #9ca3af;">
                                Bukti Pembayaran
                            </div>
                        </div>
                        <?php if ($status == 'dibayar'): ?>
                        <div class="form-actions" style="margin-top: 30px; justify-content: center;">
                            <button type="button" class="btn-cancel" onclick="window.location.href='pembayaran_komisi.php'">Kembali</button>
                            <button type="button" class="btn-submit" id="btnKonfirmasi">
                                <i class="bi bi-check-circle"></i> Konfirmasi Pembayaran
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="form-actions" style="margin-top: 30px; justify-content: center;">
                            <button type="button" class="btn-cancel" onclick="window.location.href='pembayaran_komisi.php'">Kembali</button>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const btnKonfirmasi = document.getElementById('btnKonfirmasi');
        
        if (btnKonfirmasi) {
            btnKonfirmasi.addEventListener('click', async function() {
                const submitBtn = btnKonfirmasi;
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="btn-spinner"><i class="bi bi-arrow-repeat spin"></i></span> Memproses...';
                
                try {
                    const formData = new URLSearchParams();
                    formData.append('action', 'konfirmasi');
                    
                    const res = await fetch(window.location.href, { 
                        method: 'POST', 
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData.toString()
                    });
                    
                    const data = await res.json();
                    showToast(data.message, data.success ? 'success' : 'error');
                    
                    if (data.success) {
                        setTimeout(() => {
                            window.location.href = 'pembayaran_komisi.php';
                        }, 2000);
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan pada server', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }
        
        function showToast(msg, type = 'success') {
            const icons = { 
                success: 'bi-check-circle-fill', 
                error: 'bi-x-circle-fill', 
                info: 'bi-info-circle-fill' 
            };
            
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${escapeHtml(msg)}</span>`;
            
            toastContainer.appendChild(div);
            
            setTimeout(() => {
                div.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => div.remove(), 300);
            }, 3500);
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>
</body>
</html>