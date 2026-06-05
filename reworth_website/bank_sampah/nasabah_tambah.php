<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

function getSupabaseImageUrl($path) {
    if (empty($path)) return null;
    return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
}

function getWilayahList($supabaseUrl, $supabaseKey) {
    $ch = curl_init($supabaseUrl . "/rest/v1/wilayah?select=id_wilayah,kecamatan,kelurahan,rw&order=kecamatan.asc");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey", 
        "Authorization: Bearer $supabaseKey"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) return [];
    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

$wilayahList = getWilayahList($supabaseUrl, $supabaseKey);

$groupedWilayah = [];
if (is_array($wilayahList) && !empty($wilayahList)) {
    foreach ($wilayahList as $w) {
        if (is_array($w) && isset($w['kecamatan']) && isset($w['kelurahan'])) {
            $kecamatan = $w['kecamatan'];
            $kelurahan = $w['kelurahan'];
            
            if (!isset($groupedWilayah[$kecamatan])) {
                $groupedWilayah[$kecamatan] = [];
            }
            if (!isset($groupedWilayah[$kecamatan][$kelurahan])) {
                $groupedWilayah[$kecamatan][$kelurahan] = [];
            }
            $groupedWilayah[$kecamatan][$kelurahan][] = $w;
        }
    }
}

$userName = $_SESSION['nama_admin'] ?? 'User';
$userEmail = $_SESSION['email'] ?? 'user@example.com';
$userFoto = $_SESSION['foto_profil'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $nama = $_POST['nama_lengkap'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $no_telepon = $_POST['no_telepon'] ?? '';
    $alamat = $_POST['alamat_detail'] ?? '';
    $id_wilayah = $_POST['id_wilayah'] ?? '';
    $poin = $_POST['poin'] ?? 0;
    $saldo = $_POST['saldo_tabungan'] ?? 0;
    
    if (empty($nama) || empty($email) || empty($password) || empty($id_wilayah)) {
        echo json_encode(['success' => false, 'message' => 'Data wajib tidak lengkap']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
        exit;
    }
    
    $foto_path = '';
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_profil'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'pengguna/' . time() . '_' . uniqid() . '.' . $extension;
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
            $foto_path = $filename;
        }
    }
    
    // Buat akun auth di Supabase
    $authUrl = $supabaseUrl . "/auth/v1/signup";
    $authData = [
        'email' => $email,
        'password' => $password,
        'data' => [
            'nama_lengkap' => $nama,
            'no_telepon' => $no_telepon
        ]
    ];
    
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $authResponse = curl_exec($ch);
    $authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $authResult = json_decode($authResponse, true);
    
    if ($authHttpCode !== 200) {
        $errorMsg = 'Gagal membuat akun';
        if (isset($authResult['msg'])) {
            $errorMsg = $authResult['msg'];
        } elseif (isset($authResult['error_description'])) {
            $errorMsg = $authResult['error_description'];
        }
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    
    $userId = $authResult['user']['id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Gagal mendapatkan ID pengguna']);
        exit;
    }
    
    $data = [
        'id_pengguna' => $userId,
        'nama_lengkap' => $nama,
        'email' => $email,
        'no_telepon' => $no_telepon,
        'alamat_detail' => $alamat,
        'poin' => (int)$poin,
        'saldo_tabungan' => (float)$saldo,
        'id_wilayah' => $id_wilayah,
        'foto_profil' => $foto_path,
    ];
    
    $ch = curl_init($supabaseUrl . "/rest/v1/pengguna");
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
        require_once 'log_helper.php'; logAdminActivity($supabaseUrl, $supabaseKey, $_SESSION['id_admin'] ?? '', 'Menambahkan nasabah baru: ' . ($nama ?? ''), 'pengguna', $userId ?? '');
    echo json_encode(['success' => true, 'message' => 'Nasabah berhasil ditambahkan']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Akun Auth berhasil, tapi gagal menyimpan data tambahan'
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Nasabah</title>
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
                <h1 class="topbar-title">Tambah Nasabah</h1>
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
                        <h2>Tambah Nasabah Baru</h2>
                    </div>
                    <form id="nasabahForm" enctype="multipart/form-data">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control-custom" id="email" placeholder="contoh@email.com" required>
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">No Telepon</label>
                                <input type="tel" class="form-control-custom" id="no_telepon" placeholder="628xxxxxxxxxx">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control-custom" id="password" placeholder="Minimal 6 karakter" required minlength="6">
                                <small class="text-muted" style="font-size: 11px;">Minimal 6 karakter</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alamat Tempat Tinggal</label>
                            <textarea class="form-control-custom" id="alamat_detail" rows="2" placeholder="Masukkan alamat lengkap"></textarea>
                        </div>
                        
                        <div class="row-3cols">
                            <div class="form-group">
                                <label class="form-label">Kecamatan <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="kecamatan" required>
                                    <option value="">Pilih Kecamatan</option>
                                    <?php if (!empty($groupedWilayah)): ?>
                                        <?php foreach(array_keys($groupedWilayah) as $kec): ?>
                                            <option value="<?= htmlspecialchars($kec) ?>"><?= htmlspecialchars($kec) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Data wilayah tidak tersedia</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kelurahan <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="kelurahan" required disabled>
                                    <option value="">Pilih Kecamatan dulu</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">RW <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="rw" required disabled>
                                    <option value="">Pilih Kelurahan dulu</option>
                                </select>
                                <input type="hidden" id="id_wilayah">
                            </div>
                        </div>
                        
                        <div class="row-3cols">
                            <div class="form-group">
                                <label class="form-label">Foto Profil</label>
                                <div class="file-upload-wrapper">
                                    <label class="file-upload-label">
                                        <i class="bi bi-cloud-upload"></i>
                                        <span id="fileName">Pilih file foto</span>
                                        <input type="file" class="file-upload-input" id="foto_profil" accept="image/*" onchange="previewImage(this)">
                                    </label>
                                </div>
                                <div class="image-preview" id="imagePreview"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Saldo Tabungan</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #6b7280; font-weight: 500;">Rp</span>
                                    <input type="number" class="form-control-custom" id="saldo_tabungan" placeholder="0" value="0" style="padding-left: 48px;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Poin Awal</label>
                                <input type="number" class="form-control-custom" id="poin" placeholder="0" value="0">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='data_nasabah.php'">Batal</button>
                            <button type="submit" class="btn-submit">Simpan Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        const wilayahData = <?= json_encode($wilayahList) ?>;
        
        function previewImage(input) {
            const fileName = input.files[0]?.name || 'Pilih file foto';
            document.getElementById('fileName').textContent = fileName;
            
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
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
            document.getElementById('foto_profil').value = '';
            document.getElementById('fileName').textContent = 'Pilih file foto';
        }
        
        document.getElementById('kecamatan').onchange = function() {
            const kelurahanSelect = document.getElementById('kelurahan');
            const rwSelect = document.getElementById('rw');
            const selectedKec = this.value;
            
            if (!selectedKec) {
                kelurahanSelect.innerHTML = '<option value="">Pilih Kecamatan dulu</option>';
                kelurahanSelect.disabled = true;
                rwSelect.innerHTML = '<option value="">Pilih Kelurahan dulu</option>';
                rwSelect.disabled = true;
                return;
            }
            
            const kelurahans = [];
            if (Array.isArray(wilayahData)) {
                wilayahData.forEach(w => {
                    if (w.kecamatan === selectedKec && !kelurahans.includes(w.kelurahan)) {
                        kelurahans.push(w.kelurahan);
                    }
                });
            }
            
            kelurahanSelect.innerHTML = '<option value="">Pilih Kelurahan</option>';
            kelurahans.forEach(k => {
                kelurahanSelect.innerHTML += `<option value="${k}">${k}</option>`;
            });
            kelurahanSelect.disabled = false;
            rwSelect.innerHTML = '<option value="">Pilih Kelurahan dulu</option>';
            rwSelect.disabled = true;
            document.getElementById('id_wilayah').value = '';
        };
        
        document.getElementById('kelurahan').onchange = function() {
            const kec = document.getElementById('kecamatan').value;
            const kel = this.value;
            const rwSelect = document.getElementById('rw');
            
            if (!kel) {
                rwSelect.innerHTML = '<option value="">Pilih Kelurahan dulu</option>';
                rwSelect.disabled = true;
                document.getElementById('id_wilayah').value = '';
                return;
            }
            
            const rws = [];
            if (Array.isArray(wilayahData)) {
                wilayahData.forEach(w => {
                    if (w.kecamatan === kec && w.kelurahan === kel) {
                        rws.push(w);
                    }
                });
            }
            
            rwSelect.innerHTML = '<option value="">Pilih RW</option>';
            rws.forEach(r => {
                rwSelect.innerHTML += `<option value="${r.id_wilayah}" data-rw="${r.rw}">RW ${r.rw}</option>`;
            });
            rwSelect.disabled = false;
        };
        
        document.getElementById('rw').onchange = function() {
            document.getElementById('id_wilayah').value = this.value;
        };
        
        document.getElementById('nasabahForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const nama = document.getElementById('nama_lengkap').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const idWilayah = document.getElementById('id_wilayah').value;
            
            if (!nama || !email || !password || !idWilayah) {
                showToast('Data wajib tidak lengkap!', 'error');
                return;
            }
            
            if (password.length < 6) {
                showToast('Password minimal 6 karakter!', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('nama_lengkap', nama);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('no_telepon', document.getElementById('no_telepon').value);
            formData.append('alamat_detail', document.getElementById('alamat_detail').value);
            formData.append('poin', document.getElementById('poin').value);
            formData.append('saldo_tabungan', document.getElementById('saldo_tabungan').value);
            formData.append('id_wilayah', idWilayah);
            
            const fotoFile = document.getElementById('foto_profil').files[0];
            if (fotoFile) {
                formData.append('foto_profil', fotoFile);
            }
            
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => {
                        window.location.href = 'data_nasabah.php';
                    }, 2000);
                }
            } catch (error) {
                showToast('Terjadi kesalahan pada server', 'error');
            }
        };
        
        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
    </script>
</body>
</html>