<?php
    require_once 'role_check.php';

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
        header("Location: sponsor.php?tab=kontribusi");
        exit;
    }

    function getAllSponsor($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/sponsor?select=id_sponsor,nama_sponsor&order=nama_sponsor.asc";
        
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
            return json_decode($response, true) ?: [];
        }
        return [];
    }

    function getKontribusiById($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/kontribusi_sponsor?id_kontribusi=eq." . $id . "&select=*";
        
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
        
        $id_kontribusi = $_POST['id_kontribusi'] ?? '';
        $id_sponsor = $_POST['id_sponsor'] ?? '';
        $jenis_kontribusi = $_POST['jenis_kontribusi'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        
        if (empty($id_kontribusi) || empty($id_sponsor) || empty($jenis_kontribusi) || empty($tanggal)) {
            echo json_encode(['success' => false, 'message' => 'Field wajib tidak lengkap!']);
            exit;
        }
        
        $data = [
            'id_sponsor' => $id_sponsor,
            'jenis_kontribusi' => $jenis_kontribusi,
            'keterangan' => $keterangan,
            'tanggal' => $tanggal
        ];
        
        if ($jenis_kontribusi === 'Barang') {
            $nama_barang = $_POST['nama_barang'] ?? '';
            $jumlah_barang = $_POST['jumlah_barang'] ?? 0;
            
            if (empty($nama_barang)) {
                echo json_encode(['success' => false, 'message' => 'Nama barang wajib diisi!']);
                exit;
            }
            
            $data['nama_barang'] = $nama_barang;
            $data['jumlah_barang'] = $jumlah_barang;
            $data['nominal_uang'] = null;
        } else if ($jenis_kontribusi === 'Uang') {
            $nominal_uang = $_POST['nominal_uang'] ?? 0;
            
            if (empty($nominal_uang) || $nominal_uang <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nominal uang wajib diisi!']);
                exit;
            }
            
            $data['nominal_uang'] = (int) $nominal_uang;
            $data['nama_barang'] = null;
            $data['jumlah_barang'] = null;
        }
        
        $url = $supabaseUrl . "/rest/v1/kontribusi_sponsor?id_kontribusi=eq." . $id_kontribusi;
        
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
                'aktivitas' => 'Memperbarui kontribusi ' . $jenis_kontribusi . ' untuk sponsor',
                'tabel_terkait' => 'kontribusi_sponsor',
                'id_data' => $id_kontribusi,
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
            
            echo json_encode(['success' => true, 'message' => 'Kontribusi sponsor berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui kontribusi. Silakan coba lagi.']);
        }
        exit;
    }

    $sponsorList = getAllSponsor($supabaseUrl, $supabaseKey);
    $kontribusi = getKontribusiById($supabaseUrl, $supabaseKey, $id);
    
    if (!$kontribusi) {
        header("Location: sponsor.php?tab=kontribusi");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Edit Kontribusi Sponsor</title>
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
                        <h2>Edit Kontribusi Sponsor</h2>
                    </div>
                    
                    <form id="kontribusiForm">
                        <input type="hidden" id="id_kontribusi" value="<?= htmlspecialchars($kontribusi['id_kontribusi']) ?>">
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Pilih Sponsor <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="id_sponsor" required>
                                    <option value="">Pilih Sponsor</option>
                                    <?php foreach ($sponsorList as $sponsor): ?>
                                        <option value="<?= htmlspecialchars($sponsor['id_sponsor']) ?>" 
                                            <?= ($kontribusi['id_sponsor'] == $sponsor['id_sponsor']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sponsor['nama_sponsor']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jenis Kontribusi <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="jenis_kontribusi" required>
                                    <option value="">Pilih Jenis Kontribusi</option>
                                    <option value="Barang" <?= ($kontribusi['jenis_kontribusi'] == 'Barang') ? 'selected' : '' ?>>Barang</option>
                                    <option value="Uang" <?= ($kontribusi['jenis_kontribusi'] == 'Uang') ? 'selected' : '' ?>>Uang</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Dynamic Field for Barang -->
                        <div id="fieldBarang" class="dynamic-field <?= ($kontribusi['jenis_kontribusi'] == 'Barang') ? 'show' : '' ?>">
                            <div class="row-2cols">
                                <div class="form-group">
                                    <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control-custom" id="nama_barang" placeholder="Masukkan nama barang" value="<?= htmlspecialchars($kontribusi['nama_barang'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Jumlah Barang</label>
                                    <input type="number" class="form-control-custom" id="jumlah_barang" placeholder="Jumlah barang" min="1" value="<?= htmlspecialchars($kontribusi['jumlah_barang'] ?? 1) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dynamic Field for Uang -->
                        <div id="fieldUang" class="dynamic-field <?= ($kontribusi['jenis_kontribusi'] == 'Uang') ? 'show' : '' ?>">
                            <div class="form-group">
                                <label class="form-label">Nominal Uang <span class="text-danger">*</span></label>
                                <input type="integer" class="form-control-custom" id="nominal_uang" placeholder="Masukkan nominal uang" min="1" value="<?= htmlspecialchars($kontribusi['nominal_uang'] ?? '') ?>">
                                <small class="text-muted" style="font-size: 11px;">Contoh: 5000000 (Rp 5.000.000)</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tanggal Kontribusi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control-custom" id="tanggal" required value="<?= htmlspecialchars($kontribusi['tanggal'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control-custom" id="keterangan" rows="3" placeholder="Masukkan keterangan tambahan (opsional)"><?= htmlspecialchars($kontribusi['keterangan'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='sponsor.php?tab=kontribusi'">Batal</button>
                            <button type="submit" class="btn-submit">Update Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        const jenisKontribusi = document.getElementById('jenis_kontribusi');
        const fieldBarang = document.getElementById('fieldBarang');
        const fieldUang = document.getElementById('fieldUang');
        
        function toggleDynamicFields() {
            fieldBarang.classList.remove('show');
            fieldUang.classList.remove('show');
            
            if (jenisKontribusi.value === 'Barang') {
                fieldBarang.classList.add('show');
                document.getElementById('nama_barang').required = true;
                document.getElementById('nominal_uang').required = false;
            } else if (jenisKontribusi.value === 'Uang') {
                fieldUang.classList.add('show');
                document.getElementById('nama_barang').required = false;
                document.getElementById('nominal_uang').required = true;
            } else {
                document.getElementById('nama_barang').required = false;
                document.getElementById('nominal_uang').required = false;
            }
        }
        
        jenisKontribusi.addEventListener('change', toggleDynamicFields);
        
        document.getElementById('kontribusiForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const id_kontribusi = document.getElementById('id_kontribusi').value;
            const id_sponsor = document.getElementById('id_sponsor').value;
            const jenis_kontribusi = document.getElementById('jenis_kontribusi').value;
            const tanggal = document.getElementById('tanggal').value;
            const keterangan = document.getElementById('keterangan').value;
            
            if (!id_sponsor) {
                showToast('Pilih sponsor terlebih dahulu!', 'error');
                return;
            }
            
            if (!jenis_kontribusi) {
                showToast('Pilih jenis kontribusi!', 'error');
                return;
            }
            
            if (!tanggal) {
                showToast('Pilih tanggal kontribusi!', 'error');
                return;
            }
            
            const formData = new URLSearchParams();
            formData.append('id_kontribusi', id_kontribusi);
            formData.append('id_sponsor', id_sponsor);
            formData.append('jenis_kontribusi', jenis_kontribusi);
            formData.append('tanggal', tanggal);
            formData.append('keterangan', keterangan);
            
            if (jenis_kontribusi === 'Barang') {
                const nama_barang = document.getElementById('nama_barang').value.trim();
                const jumlah_barang = document.getElementById('jumlah_barang').value;
                
                if (!nama_barang) {
                    showToast('Nama barang wajib diisi!', 'error');
                    return;
                }
                
                formData.append('nama_barang', nama_barang);
                formData.append('jumlah_barang', jumlah_barang);
                formData.append('nominal_uang', 0);
            } else if (jenis_kontribusi === 'Uang') {
                const nominal_uang = document.getElementById('nominal_uang').value;
                
                if (!nominal_uang || nominal_uang <= 0) {
                    showToast('Nominal uang wajib diisi!', 'error');
                    return;
                }
                
                formData.append('nominal_uang', nominal_uang);
                formData.append('nama_barang', '');
                formData.append('jumlah_barang', 0);
            }
            
            const submitBtn = document.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-spinner"><i class="bi bi-arrow-repeat spin"></i></span> Menyimpan...';
            
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
                        window.location.href = 'sponsor.php?tab=kontribusi';
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