<?php
    require_once 'role_check.php';

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


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $nama_reward = $_POST['nama_reward'] ?? '';
        $poin_dibutuhkan = $_POST['poin_dibutuhkan'] ?? 0;
        $stok = $_POST['stok'] ?? 0;
        $kode_voucher = $_POST['kode_voucher'] ?? '';
        
        if (empty($nama_reward)) {
            echo json_encode(['success' => false, 'message' => 'Nama reward wajib diisi']);
            exit;
        }
        
        if (empty($poin_dibutuhkan) || (int)$poin_dibutuhkan <= 0) {
            echo json_encode(['success' => false, 'message' => 'Poin yang dibutuhkan harus lebih dari 0']);
            exit;
        }
        
        if (empty($stok) || (int)$stok <= 0) {
            echo json_encode(['success' => false, 'message' => 'Stok harus lebih dari 0']);
            exit;
        }
        

        $foto_path = '';
        if (isset($_FILES['foto_reward']) && $_FILES['foto_reward']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_reward'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'reward/' . time() . '_' . uniqid() . '.' . $extension;

            $storageUrl = $supabaseUrl . "/storage/v1/object/media/" . $filename;
            
            $fileData = file_get_contents($file['tmp_name']);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $storageUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: " . $file['type'],
                "x-upsert: true"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 || $httpCode == 201) {
                $foto_path = $filename;
            } else {

                error_log("Upload failed. HTTP Code: $httpCode, Response: $response");
            }
        }
        
        $data = [
            'nama_reward' => $nama_reward,
            'poin_dibutuhkan' => (int)$poin_dibutuhkan,
            'stok' => (int)$stok,
            'kode_voucher' => $kode_voucher,
            'foto_reward' => $foto_path,
        ];
        
        $ch = curl_init($supabaseUrl . "/rest/v1/reward");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
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
        
        if ($httpCode === 201 || $httpCode === 200 || $httpCode === 204) {

            $logData = [
                'id_admin' => $_SESSION['id_admin'] ?? '',
                'aktivitas' => 'Menambahkan data reward: ' . $nama_reward,
                'tabel_terkait' => 'reward',
                'id_data' => '',
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
            
            echo json_encode(['success' => true, 'message' => 'Reward berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan data reward']);
        }
        exit;
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Reward - Kelola Data Master</title>
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
                        <h2>Tambah Data Reward</h2>
                    </div>
                    <form id="rewardForm" enctype="multipart/form-data">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Nama Reward <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="nama_reward" placeholder="Masukkan nama reward" required>
                                <small class="text-muted" style="font-size: 11px;">Contoh: Voucher Pulsa 10k, Listrik 20k</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kode Voucher</label>
                                <input type="text" class="form-control-custom" id="kode_voucher" placeholder="Masukkan kode voucher (opsional)">
                                <small class="text-muted" style="font-size: 11px;">Kode voucher untuk reward digital</small>
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Poin yang Dibutuhkan <span class="text-danger">*</span></label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #6b7280; font-weight: 500;">
                                        <i class="bi bi-coin"></i>
                                    </span>
                                    <input type="number" class="form-control-custom" id="poin_dibutuhkan" placeholder="0" value="0" style="padding-left: 48px;" required>
                                </div>
                                <small class="text-muted" style="font-size: 11px;">Jumlah poin yang diperlukan untuk menukar reward ini</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stok Reward <span class="text-danger">*</span></label>
                                <input type="number" class="form-control-custom" id="stok" placeholder="0" value="0" required>
                                <small class="text-muted" style="font-size: 11px;">Jumlah stok reward yang tersedia</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Foto Reward</label>
                            <div class="file-upload-wrapper">
                                <label class="file-upload-label">
                                    <i class="bi bi-cloud-upload"></i>
                                    <span id="fileName">Pilih file foto reward</span>
                                    <input type="file" class="file-upload-input" id="foto_reward" accept="image/*" onchange="previewImage(this)">
                                </label>
                            </div>
                            <div class="image-preview" id="imagePreview"></div>
                            <small class="text-muted" style="font-size: 11px;">Format: JPG, PNG, JPEG (Max 2MB)</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='kelola_data_master.php?tab=reward'">Batal</button>
                            <button type="submit" class="btn-submit">Simpan Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        function previewImage(input) {
            const fileName = input.files[0]?.name || 'Pilih file foto reward';
            document.getElementById('fileName').textContent = fileName;
            
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                if (input.files[0].size > 2 * 1024 * 1024) {
                    showToast('Ukuran file terlalu besar! Maksimal 2MB', 'error');
                    input.value = '';
                    document.getElementById('fileName').textContent = 'Pilih file foto reward';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage(this)"><i class="bi bi-x"></i></button>
                    `;
                    preview.appendChild(div);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removeImage(btn) {
            const previewItem = btn.closest('.preview-item');
            previewItem.remove();
            document.getElementById('foto_reward').value = '';
            document.getElementById('fileName').textContent = 'Pilih file foto reward';
        }
        
        document.getElementById('rewardForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const namaReward = document.getElementById('nama_reward').value.trim();
            const poinDibutuhkan = document.getElementById('poin_dibutuhkan').value;
            const stok = document.getElementById('stok').value;
            
            if (!namaReward) {
                showToast('Nama reward wajib diisi!', 'error');
                return;
            }
            
            if (parseInt(poinDibutuhkan) <= 0) {
                showToast('Poin yang dibutuhkan harus lebih dari 0!', 'error');
                return;
            }
            
            if (parseInt(stok) <= 0) {
                showToast('Stok harus lebih dari 0!', 'error');
                return;
            }
            
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Menyimpan...';
            
            const formData = new FormData();
            formData.append('nama_reward', namaReward);
            formData.append('poin_dibutuhkan', poinDibutuhkan);
            formData.append('stok', stok);
            formData.append('kode_voucher', document.getElementById('kode_voucher').value);
            
            const fotoFile = document.getElementById('foto_reward').files[0];
            if (fotoFile) {
                formData.append('foto_reward', fotoFile);
            }
            
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => {
                        window.location.href = 'kelola_data_master.php?tab=reward';
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