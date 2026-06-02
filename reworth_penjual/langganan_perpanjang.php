<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['id_penjual'])) {
    header("Location: login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userId = $_SESSION['id_penjual'] ?? '';
$userName = $_SESSION['nama_penjual'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$userFoto = $_SESSION['foto_profil'] ?? '';

function getSupabaseImageUrl($path) {
    if (empty($path)) return null;
    return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
}

function curlRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['response' => $response, 'httpCode' => $httpCode];
}

// Ambil data penjual
$getPenjual = curlRequest(
    $supabaseUrl . "/rest/v1/penjual?id_penjual=eq.$userId",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$penjualData = json_decode($getPenjual['response'], true);
$penjual = (is_array($penjualData) && isset($penjualData[0])) ? $penjualData[0] : [];

// Ambil langganan aktif saat ini
$getLangganan = curlRequest(
    $supabaseUrl . "/rest/v1/langganan?id_penjual=eq.$userId&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$allData = json_decode($getLangganan['response'], true);
$allLangganan = is_array($allData) ? $allData : [];

$langgananAktif = null;
$today = date('Y-m-d');
$isExpired = true;
$tanggalMulai = '';
$tanggalSelesai = '';

foreach ($allLangganan as $l) {
    if ($l['status'] === 'aktif') {
        $tanggalMulai = $l['tanggal_mulai'];
        $tanggalSelesai = $l['tanggal_selesai'];
        if ($tanggalSelesai >= $today) {
            $langgananAktif = $l;
            $isExpired = false;
        }
        break;
    }
}

$HARGA = 70000;
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== 0) {
        $message = "Harap upload bukti pembayaran!";
        $messageType = "error";
    } else {
        $file = $_FILES['bukti_pembayaran'];
        $fileName = 'langganan/' . time() . '_' . $userId . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);
        $fileData = file_get_contents($file['tmp_name']);
        
        $uploadResult = curlRequest(
            $supabaseUrl . "/storage/v1/object/media/" . $fileName,
            'POST',
            $fileData,
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/octet-stream"]
        );
        
        if ($uploadResult['httpCode'] == 200 || $uploadResult['httpCode'] == 201) {
            $tanggalMulaiBaru = date('Y-m-d');
            $tanggalSelesaiBaru = date('Y-m-d', strtotime("+3 months"));
            
            $dataLangganan = [
                'id_penjual' => $userId,
                'tanggal_mulai' => $tanggalMulaiBaru,
                'tanggal_selesai' => $tanggalSelesaiBaru,
                'status' => 'menunggu_verifikasi',
                'jumlah_bayar' => $HARGA,
                'bukti_pembayaran' => $fileName
            ];
            
            $insertResult = curlRequest(
                $supabaseUrl . "/rest/v1/langganan",
                'POST',
                json_encode($dataLangganan),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json", "Prefer: return=minimal"]
            );
            
            if ($insertResult['httpCode'] == 201 || $insertResult['httpCode'] == 200) {
                $message = "Pengajuan perpanjangan berhasil! Menunggu verifikasi admin.";
                $messageType = "success";
                echo "<script>setTimeout(function(){ window.location.href = 'langganan.php'; }, 2000);</script>";
            } else {
                $message = "Gagal menyimpan data langganan!";
                $messageType = "error";
            }
        } else {
            $message = "Gagal upload bukti pembayaran!";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjang Langganan - ReWorth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom active"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Langganan</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
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
                    <div class="form-section-settings" style="flex: 1;">
                        <form method="POST" enctype="multipart/form-data" id="formPerpanjang">
                            <!-- Kolom Kiri: Informasi Langganan -->
                            <div class="field-group">
                                <h2 class="section-title">Perpanjang Langganan</h2>
                                <label class="field-label">Nama Toko</label>
                                <input type="text" class="field-input" value="<?= htmlspecialchars($penjual['nama_penjual'] ?? $userName) ?>" readonly disabled>
                            </div>

                            <div class="field-group">
                                <label class="field-label">Tanggal Mulai</label>
                                <input type="text" class="field-input" value="<?= !$isExpired ? date('d F Y', strtotime($tanggalMulai)) : '-' ?>" readonly disabled>
                            </div>

                            <div class="field-group">
                                <label class="field-label">Tanggal Selesai</label>
                                <input type="text" class="field-input" value="<?= !$isExpired ? date('d F Y', strtotime($tanggalSelesai)) : '-' ?>" readonly disabled>
                            </div>

                            <div class="field-group">
                                <label class="field-label">Status</label>
                                <input type="text" class="field-input" value="<?= $isExpired ? 'Kadaluarsa' : 'Aktif' ?>" readonly disabled>
                            </div>

                            <!-- Kolom Kanan: QR Code & Upload Bukti -->
                            <div class="foto-section">
                                <label class="form-label">Scan Kode QR untuk Pembayaran</label>
                                <div class="field-group">
                                    <img src="img/qr.png" alt="QR Code" style="width: 150px; height: 150px;">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Upload Bukti Transfer</label>
                                    <div class="file-input-wrapper">
                                        <label class="file-input-label">
                                            <i class="bi bi-images"></i>
                                            Pilih Foto
                                            <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*" onchange="previewFile(this)">
                                        </label>
                                        <span class="selected-filename" id="fileName">Belum ada file dipilih</span>
                                    </div>
                                    <div class="image-preview" id="imagePreview"></div>
                                </div>
                            </div>

                            <!-- Tombol Batal dan Kirim -->
                            <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #E2EDE8;">
                                <button type="button" class="btn-cancel" onclick="window.location.href='langganan.php'">BATAL</button>
                                <button type="submit" class="btn-submit" id="btnKirim">KIRIM</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewFile(input) {
            const preview = document.getElementById('imagePreview');
            const fileName = document.getElementById('fileName');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                fileName.textContent = file.name;
                
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        div.innerHTML = `<img src="${e.target.result}">`;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `<div class="alert alert-info">File: ${file.name}</div>`;
                }
            } else {
                fileName.textContent = 'Belum ada file dipilih';
            }
        }

        // Validasi dan loading state on submit
        document.getElementById('formPerpanjang')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('bukti_pembayaran');
            
            // Cek apakah file sudah dipilih
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                showToast('Harap upload bukti pembayaran terlebih dahulu!', 'error');
                return false;
            }
            
            // Loading state
            const btn = document.getElementById('btnKirim');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="btn-spinner"><i class="bi bi-arrow-repeat spin"></i></span> Mengirim...';
            }
        });

        function showToast(msg, type) {
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