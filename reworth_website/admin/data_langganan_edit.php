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

    $idLangganan = isset($_GET['id']) ? $_GET['id'] : '';

    if (empty($idLangganan)) {
        echo '<div class="alert alert-danger m-3">ID Langganan tidak ditemukan.</div>';
        exit;
    }

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    function getPenjualList($supabaseUrl, $supabaseKey) {
        $ch = curl_init($supabaseUrl . "/rest/v1/penjual?select=id_penjual,nama_penjual,email&status=eq.verified&order=nama_penjual.asc");
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

    function getLanggananById($supabaseUrl, $supabaseKey, $id) {
        $ch = curl_init($supabaseUrl . "/rest/v1/langganan?id_langganan=eq.$id&select=*");
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

    $penjualList = getPenjualList($supabaseUrl, $supabaseKey);
    $langganan = getLanggananById($supabaseUrl, $supabaseKey, $idLangganan);

    if (!$langganan) {
        echo '<div class="alert alert-danger m-3">Data langganan tidak ditemukan.</div>';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $id_penjual = $_POST['id_penjual'] ?? '';
        $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
        $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
        $jumlah_bayar = $_POST['jumlah_bayar'] ?? 0;
        $status = $_POST['status'] ?? 'menunggu';
        $hapus_bukti = isset($_POST['hapus_bukti']) ? true : false;
        
        if (empty($id_penjual) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
            echo json_encode(['success' => false, 'message' => 'Data wajib tidak lengkap']);
            exit;
        }
        
        $data = [
            'id_penjual' => $id_penjual,
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_selesai' => $tanggal_selesai,
            'jumlah_bayar' => (float)$jumlah_bayar,
            'status' => $status,
        ];
        
        if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['bukti_pembayaran'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'langganan/' . time() . '_' . uniqid() . '.' . $extension;
            $storageUrl = $supabaseUrl . "/storage/v1/object/public/media/" . $filename;
            
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
                $data['bukti_pembayaran'] = $filename;
            }
        }
        
        if ($hapus_bukti) {
            $data['bukti_pembayaran'] = '';
        }
        
        $url = $supabaseUrl . "/rest/v1/langganan?id_langganan=eq." . $idLangganan;
        
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
                'aktivitas' => 'Mengedit data langganan: '  . ($penjualList['nama_penjual'] ?? '-'),
                'tabel_terkait' => 'langganan',
                'id_data' => $idLangganan,
            ];
            
            $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
            curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($logCh, CURLOPT_POSTFIELDS, json_encode($logData));
            curl_setopt($logCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: json"
            ]);
            curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($logCh);
            curl_close($logCh);
            
            echo json_encode(['success' => true, 'message' => 'Langganan berhasil diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data langganan']);
        }
        exit;
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Langganan - Kelola Data Master</title>
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
                        <h2>Edit Data Langganan</h2>
                    </div>
                    <form id="langgananForm" enctype="multipart/form-data">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Pilih Penjual <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="id_penjual" required>
                                    <option value="">Pilih Penjual</option>
                                    <?php if (!empty($penjualList)): ?>
                                        <?php foreach ($penjualList as $penjual): ?>
                                            <option value="<?= htmlspecialchars($penjual['id_penjual']) ?>" 
                                                <?= ($penjual['id_penjual'] == $langganan['id_penjual']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($penjual['nama_penjual']) ?> (<?= htmlspecialchars($penjual['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Belum ada penjual terverifikasi</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="status" required>
                                    <option value="menunggu" <?= ($langganan['status'] == 'menunggu') ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                                    <option value="aktif" <?= ($langganan['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                    <option value="expired" <?= ($langganan['status'] == 'expired') ? 'selected' : '' ?>>Expired</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                                <input type="date" class="form-control-custom" id="tanggal_mulai" value="<?= htmlspecialchars($langganan['tanggal_mulai']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                                <input type="date" class="form-control-custom" id="tanggal_selesai" value="<?= htmlspecialchars($langganan['tanggal_selesai']) ?>" required>
                                <small class="text-muted" style="font-size: 11px;">Otomatis update jika tanggal mulai diubah</small>
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Jumlah Bayar <span class="text-danger">*</span></label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #6b7280; font-weight: 500;">Rp</span>
                                    <input type="number" class="form-control-custom" id="jumlah_bayar" placeholder="0" value="<?= $langganan['jumlah_bayar'] ?? 0 ?>" style="padding-left: 48px;" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bukti Pembayaran</label>
                                <?php if (!empty($langganan['bukti_pembayaran'])): ?>
                                    <div class="current-image" style="margin-bottom: 10px;">
                                        <label class="form-label" style="font-size: 12px;">Bukti saat ini:</label>
                                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                                            <img src="<?= getSupabaseImageUrl($langganan['bukti_pembayaran']) ?>" 
                                                style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb;"
                                                alt="Current Bukti">
                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                <input type="checkbox" id="hapus_bukti" value="1">
                                                <span style="font-size: 12px; color: #dc3545;">Hapus bukti</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="file-upload-wrapper">
                                    <label class="file-upload-label">
                                        <i class="bi bi-cloud-upload"></i>
                                        <span id="fileName">Ganti bukti pembayaran (opsional)</span>
                                        <input type="file" class="file-upload-input" id="bukti_pembayaran" accept="image/*" onchange="previewImage(this)">
                                    </label>
                                </div>
                                <div class="image-preview" id="imagePreview"></div>
                                <small class="text-muted" style="font-size: 11px;">Format: JPG, PNG, JPEG (Max 2MB)</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='kelola_data_master.php?tab=langganan'">Batal</button>
                            <button type="submit" class="btn-submit">Update Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        function addMonths(date, months) {
            const newDate = new Date(date);
            newDate.setMonth(newDate.getMonth() + months);
            return newDate;
        }
        
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        function updateTanggalSelesai() {
            const tglMulai = document.getElementById('tanggal_mulai').value;
            const tglSelesaiInput = document.getElementById('tanggal_selesai');
            
            if (tglMulai) {
                const startDate = new Date(tglMulai);
                const endDate = addMonths(startDate, 3);
                tglSelesaiInput.value = formatDate(endDate);
            }
        }
        
        document.getElementById('tanggal_mulai').addEventListener('change', updateTanggalSelesai);
        
        function previewImage(input) {
            const fileName = input.files[0]?.name || 'Ganti bukti pembayaran (opsional)';
            document.getElementById('fileName').textContent = fileName;
            
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                if (input.files[0].size > 2 * 1024 * 1024) {
                    showToast('Ukuran file terlalu besar! Maksimal 2MB', 'error');
                    input.value = '';
                    document.getElementById('fileName').textContent = 'Ganti bukti pembayaran (opsional)';
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
            document.getElementById('bukti_pembayaran').value = '';
            document.getElementById('fileName').textContent = 'Ganti bukti pembayaran (opsional)';
        }
        
        document.getElementById('langgananForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const idPenjual = document.getElementById('id_penjual').value;
            const tglMulai = document.getElementById('tanggal_mulai').value;
            const tglSelesai = document.getElementById('tanggal_selesai').value;
            const jumlahBayar = document.getElementById('jumlah_bayar').value;
            
            if (!idPenjual || !tglMulai || !tglSelesai) {
                showToast('Data wajib tidak lengkap!', 'error');
                return;
            }
            
            if (parseFloat(jumlahBayar) <= 0) {
                showToast('Jumlah bayar harus lebih dari 0!', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('id_penjual', idPenjual);
            formData.append('tanggal_mulai', tglMulai);
            formData.append('tanggal_selesai', tglSelesai);
            formData.append('jumlah_bayar', jumlahBayar);
            formData.append('status', document.getElementById('status').value);
            
            const hapusBukti = document.getElementById('hapus_bukti');
            if (hapusBukti && hapusBukti.checked) {
                formData.append('hapus_bukti', '1');
            }
            
            const buktiFile = document.getElementById('bukti_pembayaran').files[0];
            if (buktiFile) {
                formData.append('bukti_pembayaran', buktiFile);
            }
            
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Menyimpan...';
            
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => {
                        window.location.href = 'kelola_data_master.php?tab=langganan';
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