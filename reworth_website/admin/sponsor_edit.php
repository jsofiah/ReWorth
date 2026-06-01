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
    $userFoto = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) {
        header("Location: sponsor.php");
        exit;
    }

    function getSponsorById($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/sponsor?id_sponsor=eq." . $id . "&select=*";
        
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
            if (!empty($data) && isset($data[0])) {
                return $data[0];
            }
        }
        return null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $nama_sponsor = $_POST['nama_sponsor'] ?? '';
        $kontak = $_POST['kontak'] ?? '';
        $jenis_sponsor = $_POST['jenis_sponsor'] ?? '';
        
        if (empty($nama_sponsor) || empty($kontak) || empty($jenis_sponsor)) {
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
            exit;
        }
        
        $url = $supabaseUrl . "/rest/v1/sponsor?id_sponsor=eq." . $id;
        
        $data = [
            'nama_sponsor' => $nama_sponsor,
            'kontak' => $kontak,
            'jenis_sponsor' => $jenis_sponsor
        ];
        
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 204) {
            $logData = [
                'id_admin' => $_SESSION['id_admin'] ?? '',
                'aktivitas' => 'Memperbarui sponsor: ' . $nama_sponsor,
                'tabel_terkait' => 'sponsor',
                'id_data' => $id,
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
            
            echo json_encode(['success' => true, 'message' => 'Sponsor berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui sponsor. Silakan coba lagi.']);
        }
        exit;
    }

    $sponsor = getSponsorById($supabaseUrl, $supabaseKey, $id);
    
    if (!$sponsor) {
        header("Location: sponsor.php");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Edit Sponsor</title>
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
                <a href="sponsor.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Kelola Sponsor</h1>
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
        
        <div class="content-area">
            <div class="form-container">
                <div class="form-section">
                    <div class="inside-header">
                        <h2>Edit Sponsor</h2>
                    </div>
                    
                    <form id="sponsorForm">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Nama Sponsor <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="nama_sponsor" placeholder="Masukkan nama sponsor" value="<?= htmlspecialchars($sponsor['nama_sponsor'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jenis Sponsor <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="jenis_sponsor" required>
                                    <option value="">Pilih Jenis Sponsor</option>
                                    <option value="Perusahaan" <?= ($sponsor['jenis_sponsor'] ?? '') == 'Perusahaan' ? 'selected' : '' ?>>Perusahaan</option>
                                    <option value="Komunitas" <?= ($sponsor['jenis_sponsor'] ?? '') == 'Komunitas' ? 'selected' : '' ?>>Komunitas</option>
                                    <option value="Individu" <?= ($sponsor['jenis_sponsor'] ?? '') == 'Individu' ? 'selected' : '' ?>>Individu</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kontak <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-custom" id="kontak" placeholder="Masukkan nomor telepon atau email kontak" value="<?= htmlspecialchars($sponsor['kontak'] ?? '') ?>" required>
                            <small class="text-muted" style="font-size: 11px;">Contoh: 628123456789 atau email@example.com</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='sponsor.php'">Batal</button>
                            <button type="submit" class="btn-submit">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        document.getElementById('sponsorForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const nama_sponsor = document.getElementById('nama_sponsor').value.trim();
            const kontak = document.getElementById('kontak').value.trim();
            const jenis_sponsor = document.getElementById('jenis_sponsor').value;
            
            if (!nama_sponsor) {
                showToast('Nama sponsor wajib diisi!', 'error');
                return;
            }
            
            if (!kontak) {
                showToast('Kontak wajib diisi!', 'error');
                return;
            }
            
            if (!jenis_sponsor) {
                showToast('Jenis sponsor wajib dipilih!', 'error');
                return;
            }
            
            const submitBtn = document.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-spinner"><i class="bi bi-arrow-repeat spin"></i></span> Menyimpan...';
            
            try {
                const formData = new URLSearchParams();
                formData.append('nama_sponsor', nama_sponsor);
                formData.append('kontak', kontak);
                formData.append('jenis_sponsor', jenis_sponsor);
                
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
                        window.location.href = 'sponsor.php';
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
        };
        
        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
    </script>
</body>
</html>