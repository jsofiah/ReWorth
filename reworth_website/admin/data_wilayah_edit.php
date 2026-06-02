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

    $idWilayah = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idWilayah)) {
        echo '<div class="alert alert-danger m-3">ID Wilayah tidak ditemukan.</div>';
        exit;
    }

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }


    function getKetuaRWList($supabaseUrl, $supabaseKey) {
        $ch = curl_init($supabaseUrl . "/rest/v1/pengguna?select=id_pengguna,nama_lengkap,email,no_telepon&order=nama_lengkap.asc");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey", 
            "Authorization: Bearer $supabaseKey"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [];
        }
        
        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }


    function getWilayahById($supabaseUrl, $supabaseKey, $id) {
        $ch = curl_init($supabaseUrl . "/rest/v1/wilayah?id_wilayah=eq.$id&select=*");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey", 
            "Authorization: Bearer $supabaseKey"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (is_array($data) && isset($data[0])) {
            return $data[0];
        }
        return null;
    }

    $ketuaRWList = getKetuaRWList($supabaseUrl, $supabaseKey);
    $wilayah = getWilayahById($supabaseUrl, $supabaseKey, $idWilayah);

    if (!$wilayah) {
        echo '<div class="alert alert-danger m-3">Data wilayah tidak ditemukan.</div>';
        exit;
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $rw = $_POST['rw'] ?? '';
        $kelurahan = $_POST['kelurahan'] ?? '';
        $kecamatan = $_POST['kecamatan'] ?? '';
        $kota = $_POST['kota'] ?? '';
        $id_ketua_rw = $_POST['id_ketua_rw'] ?? null;
        
        if (empty($rw) || empty($kelurahan) || empty($kecamatan) || empty($kota)) {
            echo json_encode(['success' => false, 'message' => 'Data wajib tidak lengkap']);
            exit;
        }
        
        $data = [
            'rw' => $rw,
            'kelurahan' => $kelurahan,
            'kecamatan' => $kecamatan,
            'kota' => $kota,
        ];
        
        if (!empty($id_ketua_rw)) {
            $data['id_ketua_rw'] = $id_ketua_rw;
        } else {
            $data['id_ketua_rw'] = null;
        }
        
        $url = $supabaseUrl . "/rest/v1/wilayah?id_wilayah=eq." . $idWilayah;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 204) {

            $logData = [
                'id_admin' => $_SESSION['id_admin'] ?? '',
                'aktivitas' => 'Mengedit data wilayah: RW ' . $rw . ' - ' . $kelurahan,
                'tabel_terkait' => 'wilayah',
                'id_data' => $idWilayah,
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
            
            echo json_encode(['success' => true, 'message' => 'Wilayah berhasil diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data wilayah']);
        }
        exit;
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Wilayah - Kelola Data Master</title>
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
                <a href="kelola_data_master.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Kelola Data Master</h1>
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
        
        <div class="content-area">
            <div class="form-container">
                <div class="form-section">
                    <div class="inside-header">
                        <h2>Edit Data Wilayah</h2>
                    </div>
                    <form id="wilayahForm">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="kecamatan" value="<?= htmlspecialchars($wilayah['kecamatan'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kelurahan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="kelurahan" value="<?= htmlspecialchars($wilayah['kelurahan'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">RW <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="rw" value="<?= htmlspecialchars($wilayah['rw'] ?? '') ?>" required>
                                <small class="text-muted" style="font-size: 11px;">Masukkan nomor RW (tanpa tulisan RW)</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kota <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="kota" value="<?= htmlspecialchars($wilayah['kota'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Ketua RW</label>
                            <select class="form-control-custom" id="id_ketua_rw">
                                <option value="">-- Pilih Ketua RW --</option>
                                <?php if (!empty($ketuaRWList)): ?>
                                    <?php foreach ($ketuaRWList as $ketua): ?>
                                        <option value="<?= htmlspecialchars($ketua['id_pengguna']) ?>" 
                                            <?= ($ketua['id_pengguna'] == ($wilayah['id_ketua_rw'] ?? '')) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ketua['nama_lengkap']) ?> (<?= htmlspecialchars($ketua['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>Belum ada data pengguna</option>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted" style="font-size: 11px;">Pilih ketua RW jika sudah terdaftar sebagai pengguna</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='kelola_data_master.php?tab=wilayah'">Batal</button>
                            <button type="submit" class="btn-submit">Simpan Perubahan Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        document.getElementById('wilayahForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const kecamatan = document.getElementById('kecamatan').value.trim();
            const kelurahan = document.getElementById('kelurahan').value.trim();
            const rw = document.getElementById('rw').value.trim();
            const kota = document.getElementById('kota').value.trim();
            
            if (!kecamatan) {
                showToast('Kecamatan wajib diisi!', 'error');
                return;
            }
            
            if (!kelurahan) {
                showToast('Kelurahan wajib diisi!', 'error');
                return;
            }
            
            if (!rw) {
                showToast('RW wajib diisi!', 'error');
                return;
            }
            
            if (!kota) {
                showToast('Kota wajib diisi!', 'error');
                return;
            }
            
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Menyimpan...';
            
            const formData = new URLSearchParams();
            formData.append('kecamatan', kecamatan);
            formData.append('kelurahan', kelurahan);
            formData.append('rw', rw);
            formData.append('kota', kota);
            formData.append('id_ketua_rw', document.getElementById('id_ketua_rw').value);
            
            try {
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
                        window.location.href = 'kelola_data_master.php?tab=wilayah';
                    }, 2000);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                showToast('Terjadi kesalahan pada server', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        };
        
        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${escapeHtml(msg)}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
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
        
        const style = document.createElement('style');
        style.textContent = `.spin { animation: spin 1s linear infinite; } @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    </script>
</body>
</html>