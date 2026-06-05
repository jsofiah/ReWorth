<?php
session_start();

if (!isset($_SESSION['id_penjual'])) {
    header("Location: login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userId = $_SESSION['id_penjual'] ?? '';

function getSupabaseImageUrl($path) {
    if (empty($path)) {
        return null;
    }
    return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
}

// Ambil data penjual
$url = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq.$userId&select=*";
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

$dataPenjual = [];
if ($httpCode === 200) {
    $result = json_decode($response, true);
    $dataPenjual = $result[0] ?? [];
}

// Update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $nama_penjual   = $_POST['nama_penjual'] ?? '';
    $email          = $_POST['email'] ?? '';
    $rating         = $_POST['rating'] ?? 0;
    $nama_bank      = $_POST['nama_bank'] ?? '';
    $akun_rekening  = $_POST['akun_rekening'] ?? '';
    $alamat_penjual = $_POST['alamat_penjual'] ?? '';
    $password       = !empty($_POST['password']) ? md5($_POST['password']) : '';
    $latitude       = $_POST['latitude'] ?? null;
    $longitude      = $_POST['longitude'] ?? null;

    $fotoPath = $dataPenjual['foto_profil'] ?? '';

    // Upload foto baru
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
        $fileTmp  = $_FILES['foto_profil']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['foto_profil']['name']);
        $storagePath = "penjual/" . $fileName;
        $uploadUrl = $supabaseUrl . "/storage/v1/object/media/" . $storagePath;
        $fileData = file_get_contents($fileTmp);

        $uploadHeaders = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/octet-stream"
        ];

        $uploadCh = curl_init();
        curl_setopt($uploadCh, CURLOPT_URL, $uploadUrl);
        curl_setopt($uploadCh, CURLOPT_POST, true);
        curl_setopt($uploadCh, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($uploadCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($uploadCh, CURLOPT_HTTPHEADER, $uploadHeaders);
        curl_exec($uploadCh);
        $uploadCode = curl_getinfo($uploadCh, CURLINFO_HTTP_CODE);
        curl_close($uploadCh);

        if ($uploadCode === 200 || $uploadCode === 201) {
            // Hapus foto lama
            if (!empty($fotoPath)) {
                $deleteOldUrl = $supabaseUrl . "/storage/v1/object/media/" . $fotoPath;
                $deleteOldCh = curl_init();
                curl_setopt($deleteOldCh, CURLOPT_URL, $deleteOldUrl);
                curl_setopt($deleteOldCh, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($deleteOldCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($deleteOldCh, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey"
                ]);
                curl_exec($deleteOldCh);
                curl_close($deleteOldCh);
            }
            $fotoPath = $storagePath;
        }
    }

    // Update database
    $updateData = [
        "nama_penjual"   => $nama_penjual,
        "email"          => $email,
        "rating"         => $rating,
        "nama_bank"      => $nama_bank,
        "akun_rekening"  => $akun_rekening,
        "alamat_penjual" => $alamat_penjual,
        "latitude"       => $latitude,
        "longitude"      => $longitude,
        "foto_profil"    => $fotoPath
    ];

    if (!empty($password)) {
        $updateData['password'] = $password;
    }

    $updateUrl = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq.$userId";
    $updateCh = curl_init();
    curl_setopt($updateCh, CURLOPT_URL, $updateUrl);
    curl_setopt($updateCh, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($updateCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($updateCh, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($updateCh, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);
    curl_exec($updateCh);
    $updateCode = curl_getinfo($updateCh, CURLINFO_HTTP_CODE);
    curl_close($updateCh);

    if ($updateCode === 204) {
        $_SESSION['nama_penjual'] = $nama_penjual;
        $_SESSION['email'] = $email;
        $_SESSION['foto_profil'] = $fotoPath;
        echo json_encode(["success" => true, "message" => "Profil berhasil diperbarui"]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal memperbarui profil"]);
    }
    exit;
}

$userName  = $dataPenjual['nama_penjual'] ?? '';
$userEmail = $dataPenjual['email'] ?? '';
$userRating = $dataPenjual['rating'] ?? 0;
$userFoto  = $dataPenjual['foto_profil'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Toko</title>
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
                <a href="dashboard.php" class="nav-link-custom">
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
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_toko.php" class="nav-link-custom active">
                    <i class="bi bi-shop-window"></i>
                    <span>Pengaturan Toko</span>
                </a>
            </div>
            <!-- <div class="nav-item">
                <a href="pengaturan_premium.php" class="nav-link-custom">
                    <i class="bi bi-gem"></i>
                    <span>Pengaturan Premium</span>
                </a>
            </div> -->
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
                <h1 class="topbar-title">Pengaturan Toko</h1>
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
                            <?php if (!empty($userFoto)): ?>
                                <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>" id="previewFoto" alt="Foto Profil" onerror="this.style.display='none';document.getElementById('fallbackIcon').style.display='flex';">
                                <i class="bi bi-person-fill fallback-icon" id="fallbackIcon" style="display:none;"></i>
                            <?php else: ?>
                                <img src="" id="previewFoto" style="display:none;">
                                <i class="bi bi-person-fill fallback-icon" id="fallbackIcon"></i>
                            <?php endif; ?>
                            <label class="foto-upload-btn" for="inputFoto"><i class="bi bi-camera-fill"></i></label>
                            <input type="file" id="inputFoto" name="foto_profil" accept="image/*" style="display:none;">
                        </div>
                    </div>
                    <div class="form-section-settings">
                        <form id="formProfilUtama" enctype="multipart/form-data">
                            <div class="field-group">
                                <label class="field-label">Nama Toko</label>
                                <input type="text" class="field-input" id="inputNama" name="nama_penjual" placeholder="Masukkan nama toko" value="<?= htmlspecialchars($userName) ?>">
                                <span class="field-error" id="errNama"></span>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Email</label>
                                <input type="email" class="field-input" id="inputEmail" name="email" placeholder="Masukkan email" value="<?= htmlspecialchars($userEmail) ?>">
                                <span class="field-error" id="errEmail"></span>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Rating</label>
                                <input type="number" step="0.1" min="0" max="5" class="field-input" id="inputRating" name="rating" placeholder="Masukkan rating (0-5)" value="<?= htmlspecialchars($userRating) ?>">
                                <span class="field-error" id="errRating"></span>
                                <small class="text-muted">Contoh: 4.5 atau 4.3</small>
                            </div>
                            <button type="submit" class="btn-submit" id="btnSimpanProfil"><span class="btn-text">Simpan Perubahan</span><span class="btn-spinner" style="display:none;"><i class="bi bi-arrow-repeat spin"></i></span></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="setting-content-area">
            <div class="settings-card password-card">
                <div class="card-accent"></div>
                <div class="card-body-inner password-section">
                    <h2 class="section-title">Profil Detail</h2>
                    <form id="formProfilDetail">
                        <div class="two-columns">
                            <div class="field-group">
                                <label class="field-label">Nama Bank</label>
                                <select class="field-input" id="nama_bank">
                                    <?php
                                    $banks = ["Bank Danamon","Bank Mandiri","Bank Mega","Bank Permata","BCA","BNI","BRI","BSI","CIMB Niaga","SeaBank"];
                                    foreach ($banks as $bank):
                                    ?>
                                    <option value="<?= $bank ?>" <?= ($dataPenjual['nama_bank'] ?? '') === $bank ? 'selected' : '' ?>><?= $bank ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Nomor Rekening</label>
                                <input type="text" class="field-input" id="akun_rekening" value="<?= htmlspecialchars($dataPenjual['akun_rekening'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Alamat Penjual</label>
                            <textarea class="field-input" id="alamat_penjual" rows="4"><?= htmlspecialchars($dataPenjual['alamat_penjual'] ?? '') ?></textarea>
                        </div>

                        <div class="field-group">
                            <label class="field-label">Password</label>
                            <input type="password" class="field-input" id="password" placeholder="Kosongkan jika tidak ingin ganti password">
                        </div>

                        <div class="two-columns">
                            <div class="field-group">
                                <label class="field-label">Latitude</label>
                                <input type="text" class="field-input" id="latitude" placeholder="Latitude" value="<?= htmlspecialchars($dataPenjual['latitude'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label class="field-label">Longitude</label>
                                <input type="text" class="field-input" id="longitude" placeholder="Longitude" value="<?= htmlspecialchars($dataPenjual['longitude'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="button" class="btn-submit" id="getLocationBtn"><i class="bi bi-geo-alt-fill"></i> Ambil Koordinat Saat Ini</button>
                        <div class="coordinate-info mt-2" id="coordinateInfo"></div>

                        <button type="submit" class="btn-submit mt-4">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        document.getElementById('inputFoto').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                let preview = document.getElementById('previewFoto');
                if (!preview) {
                    const wrapper = document.querySelector('.foto-wrapper');
                    wrapper.innerHTML = `<img id="previewFoto" class="preview-foto"><label class="foto-upload-btn" for="inputFoto"><i class="bi bi-camera-fill"></i></label>`;
                    preview = document.getElementById('previewFoto');
                }
                preview.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });

        document.getElementById('formProfilUtama').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const rating = parseFloat(document.getElementById('inputRating').value);
            if (rating < 0 || rating > 5) {
                showToast('Rating harus antara 0-5', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('nama_penjual', document.getElementById('inputNama').value);
            formData.append('email', document.getElementById('inputEmail').value);
            formData.append('rating', document.getElementById('inputRating').value);
            formData.append('nama_bank', document.getElementById('nama_bank').value);
            formData.append('akun_rekening', document.getElementById('akun_rekening').value);
            formData.append('alamat_penjual', document.getElementById('alamat_penjual').value);
            formData.append('password', document.getElementById('password').value);
            formData.append('latitude', document.getElementById('latitude').value);
            formData.append('longitude', document.getElementById('longitude').value);
            const foto = document.getElementById('inputFoto').files[0];
            if (foto) formData.append('foto_profil', foto);

            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1200);
        });

        document.getElementById('formProfilDetail').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const rating = parseFloat(document.getElementById('inputRating').value);
            if (rating < 0 || rating > 5) {
                showToast('Rating harus antara 0-5', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('nama_penjual', document.getElementById('inputNama').value);
            formData.append('email', document.getElementById('inputEmail').value);
            formData.append('rating', document.getElementById('inputRating').value);
            formData.append('nama_bank', document.getElementById('nama_bank').value);
            formData.append('akun_rekening', document.getElementById('akun_rekening').value);
            formData.append('alamat_penjual', document.getElementById('alamat_penjual').value);
            formData.append('password', document.getElementById('password').value);
            formData.append('latitude', document.getElementById('latitude').value);
            formData.append('longitude', document.getElementById('longitude').value);
            const foto = document.getElementById('inputFoto').files[0];
            if (foto) formData.append('foto_profil', foto);

            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1200);
        });

        function getCurrentLocation() {
            const btn = document.getElementById('getLocationBtn');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const coordinateInfo = document.getElementById('coordinateInfo');
            if (!navigator.geolocation) {
                showToast('Browser tidak mendukung geolocation', 'error');
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Mengambil lokasi...';
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    latitudeInput.value = position.coords.latitude.toFixed(6);
                    longitudeInput.value = position.coords.longitude.toFixed(6);
                    coordinateInfo.innerHTML = 'Koordinat berhasil didapatkan';
                    showToast('Koordinat berhasil didapatkan', 'success');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Ambil Koordinat Saat Ini';
                },
                function() {
                    showToast('Gagal mengambil lokasi', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Ambil Koordinat Saat Ini';
                }
            );
        }
        document.getElementById('getLocationBtn').addEventListener('click', getCurrentLocation);

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