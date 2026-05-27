<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $eventId = $_GET['id'] ?? '';
    if (empty($eventId)) {
        header("Location: event_lingkungan.php");
        exit;
    }

    function getEventDetail($supabaseUrl, $supabaseKey, $eventId) {
        $url = $supabaseUrl . "/rest/v1/event?id_event=eq." . $eventId . "&select=*";
        $headers = ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }

    $event = getEventDetail($supabaseUrl, $supabaseKey, $eventId);
    if (!$event) {
        header("Location: event_lingkungan.php");
        exit;
    }

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function simpanLogAdmin(
        $supabaseUrl,
        $supabaseKey,
        $idAdmin,
        $aktivitas,
        $tabelTerkait,
        $idData
    ) {

        $url = $supabaseUrl . "/rest/v1/log_admin";

        $data = [
            'id_admin' => $idAdmin,
            'aktivitas' => $aktivitas,
            'tabel_terkait' => $tabelTerkait,
            'id_data' => $idData,
            'created_at' => date('c')
        ];

        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);

        curl_close($ch);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        header('Content-Type: application/json');

        if (!isset($_SESSION['role'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized'
            ]);
            exit;
        }

        $event_id = $_POST['event_id'] ?? '';
        $nama_event = $_POST['nama_event'] ?? '';
        $narasumber = $_POST['narasumber'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $max_partisipan = $_POST['max_partisipan'] ?? 0;
        $status = $_POST['status'] ?? 'akan_datang';
        $lokasi = $_POST['lokasi'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $waktu = $_POST['waktu'] ?? '';
        $persyaratan = $_POST['persyaratan'] ?? '';
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;

        $data = [
            'nama_event' => $nama_event,
            'narasumber' => $narasumber,
            'deskripsi' => $deskripsi,
            'max_partisipan' => (int)$max_partisipan,
            'status' => $status,
            'lokasi' => $lokasi,
            'tanggal' => $tanggal,
            'waktu' => $waktu,
            'persyaratan' => $persyaratan,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        if (
            isset($_FILES['foto_event']) &&
            $_FILES['foto_event']['error'] === UPLOAD_ERR_OK
        ) {
            if (!empty($event['foto_event'])) {
                $deleteUrl =
                    $supabaseUrl .
                    "/storage/v1/object/media/" .
                    $event['foto_event'];

                $deleteCh = curl_init();

                curl_setopt($deleteCh, CURLOPT_URL, $deleteUrl);
                curl_setopt($deleteCh, CURLOPT_CUSTOMREQUEST, "DELETE");

                curl_setopt($deleteCh, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey"
                ]);

                curl_setopt($deleteCh, CURLOPT_RETURNTRANSFER, true);

                curl_exec($deleteCh);

                curl_close($deleteCh);
            }

            $file = $_FILES['foto_event'];

            $filename =
                'event/' .
                time() .
                '_' .
                preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);

            $uploadUrl =
                $supabaseUrl .
                "/storage/v1/object/media/" .
                $filename;

            $fileData = file_get_contents($file['tmp_name']);

            $uploadCh = curl_init();

            curl_setopt($uploadCh, CURLOPT_URL, $uploadUrl);

            curl_setopt($uploadCh, CURLOPT_CUSTOMREQUEST, "POST");

            curl_setopt($uploadCh, CURLOPT_POSTFIELDS, $fileData);

            curl_setopt($uploadCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: " . $file['type'],
                "x-upsert: true",
                "Content-Length: " . filesize($file['tmp_name'])
            ]);

            curl_setopt($uploadCh, CURLOPT_RETURNTRANSFER, true);

            $uploadResponse = curl_exec($uploadCh);

            $uploadHttpCode = curl_getinfo($uploadCh, CURLINFO_HTTP_CODE);

            $curlError = curl_error($uploadCh);

            curl_close($uploadCh);

            if ($uploadHttpCode === 200 || $uploadHttpCode === 201) {

                $data['foto_event'] = $filename;

            } else {

                echo json_encode([
                    'success' => false,
                    'message' => 'Upload foto baru gagal',
                    'debug' => $uploadResponse,
                    'http_code' => $uploadHttpCode,
                    'curl_error' => $curlError,
                    'upload_url' => $uploadUrl
                ]);

                exit;
            }
        }

        $ch = curl_init(
            $supabaseUrl . "/rest/v1/event?id_event=eq." . $event_id
        );

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
            simpanLogAdmin(
                $supabaseUrl,
                $supabaseKey,
                $_SESSION['id_admin'],
                'Mengedit event: ' . $nama_event,
                'event',
                $event_id
            );

            echo json_encode([
                'success' => true,
                'message' => 'Event berhasil diupdate'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengupdate data',
                'debug' => $response
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
    <title>Edit Event - Bank Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Bank Sampah Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="transaksi_setor_sampah.php" class="nav-link-custom">
                    <i class="bi bi-recycle"></i>
                    <span>Transaksi Setor Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="penarikan_saldo.php" class="nav-link-custom">
                    <i class="bi bi-wallet2"></i>
                    <span>Penarikan Saldo</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom active">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>Event Lingkungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_nasabah.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Nasabah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_sampah.php" class="nav-link-custom">
                    <i class="bi bi-trash-fill"></i>
                    <span>Data Sampah</span>
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
                <h1 class="topbar-title">Event Lingkungan</h1>
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
                        <h2>Edit Event</h2>
                    </div>
                    <form id="eventForm" enctype="multipart/form-data">
                        <input type="hidden" id="event_id" value="<?= $event['id_event'] ?>">
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Nama Event</label>
                                <input type="text" class="form-control-custom" id="nama_event" value="<?= htmlspecialchars($event['nama_event'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Narasumber</label>
                                <input type="text" class="form-control-custom" id="narasumber" value="<?= htmlspecialchars($event['narasumber'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control-custom" id="deskripsi"><?= htmlspecialchars($event['deskripsi'] ?? '') ?></textarea>
                        </div>

                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Max Peserta</label>
                                <input type="number" class="form-control-custom" id="max_partisipan" value="<?= $event['max_partisipan'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status Event</label>
                                <select class="form-control-custom" id="status">
                                    <option value="akan_datang" <?= ($event['status'] ?? '') == 'akan_datang' ? 'selected' : '' ?>>Akan Datang</option>
                                    <option value="berlangsung" <?= ($event['status'] ?? '') == 'berlangsung' ? 'selected' : '' ?>>Berlangsung</option>
                                    <option value="selesai" <?= ($event['status'] ?? '') == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lokasi / Alamat</label>
                            <input type="text" class="form-control-custom" id="lokasi" value="<?= htmlspecialchars($event['lokasi'] ?? '') ?>" required>
                        </div>

                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control-custom" id="tanggal" value="<?= $event['tanggal'] ?? '' ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control-custom" id="waktu" value="<?= substr($event['waktu'] ?? '09:00', 0, 5) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Persyaratan Peserta</label>
                            <textarea class="form-control-custom" id="persyaratan"><?= htmlspecialchars($event['persyaratan'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Foto Event Saat Ini</label>
                            <?php if (!empty($event['foto_event'])): ?>
                                <div class="current-image">
                                    <img src="<?= getSupabaseImageUrl($event['foto_event']) ?>" style="width:150px;height:150px;object-fit:cover;border-radius:12px;">
                                </div>
                            <?php endif; ?>
                            
                            <label class="form-label">Ganti Foto Event</label>
                            <div class="file-input-wrapper">
                                <label class="file-input-label">
                                    <i class="bi bi-cloud-upload"></i> Pilih File Baru
                                    <input type="file" id="foto_event" accept="image/*" onchange="previewImage(this)">
                                </label>
                                <span class="selected-filename" id="fileName">No file chosen</span>
                            </div>
                            <div class="image-preview" id="imagePreview"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lokasi Koordinat (opsional)</label>
                            <div class="row-2cols" style="margin-bottom: 10px;">
                                <input type="text" class="form-control-custom" id="latitude" placeholder="Latitude" value="<?= $event['latitude'] ?? '' ?>">
                                <input type="text" class="form-control-custom" id="longitude" placeholder="Longitude" value="<?= $event['longitude'] ?? '' ?>">
                            </div>
                            <button type="button" class="btn-location" id="getLocationBtn">
                                <i class="bi bi-geo-alt-fill"></i> Ambil Koordinat Saat Ini
                            </button>
                            <div class="coordinate-info" id="coordinateInfo"></div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='event_lingkungan.php'">Batal</button>
                            <button type="submit" class="btn-submit">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        function previewImage(input) {
            const fileName = input.files[0]?.name || 'No file chosen';
            document.getElementById('fileName').textContent = fileName;
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-image" onclick="removeImage(this)"><i class="bi bi-x"></i></button>`;
                    preview.appendChild(div);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removeImage(btn) {
            btn.closest('.preview-item').remove();
            document.getElementById('foto_event').value = '';
            document.getElementById('fileName').textContent = 'No file chosen';
        }
        
        function getCurrentLocation() {
            const btn = document.getElementById('getLocationBtn');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const coordinateInfo = document.getElementById('coordinateInfo');
            
            if (!navigator.geolocation) {
                showToast('Browser Anda tidak mendukung fitur Geolocation', 'error');
                return;
            }
            
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="location-loading"></span> Mendapatkan lokasi...';
            btn.disabled = true;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    latitudeInput.value = lat.toFixed(6);
                    longitudeInput.value = lng.toFixed(6);
                    
                    coordinateInfo.innerHTML = '<i class="bi bi-check-circle-fill" style="color: #10b981;"></i> Koordinat berhasil diperbarui!';
                    coordinateInfo.style.color = '#10b981';
                    
                    showToast('Koordinat berhasil diperbarui!', 'success');
                    
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    
                    setTimeout(() => {
                        if (coordinateInfo.innerHTML.includes('berhasil')) {
                            coordinateInfo.innerHTML = '';
                        }
                    }, 3000);
                },
                function(error) {
                    let errorMessage = '';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Izin akses lokasi ditolak. Silakan aktifkan izin lokasi di browser Anda.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Informasi lokasi tidak tersedia.';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Waktu permintaan lokasi habis. Silakan coba lagi.';
                            break;
                        default:
                            errorMessage = 'Terjadi kesalahan saat mengambil lokasi.';
                            break;
                    }
                    
                    coordinateInfo.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="color: #ef4444;"></i> ' + errorMessage;
                    coordinateInfo.style.color = '#ef4444';
                    
                    showToast(errorMessage, 'error');
                    
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    
                    setTimeout(() => {
                        if (coordinateInfo.innerHTML.includes('Gagal') || coordinateInfo.innerHTML.includes('ditolak')) {
                            coordinateInfo.innerHTML = '';
                        }
                    }, 5000);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
        
        document.getElementById('getLocationBtn').addEventListener('click', getCurrentLocation);
        
        document.getElementById('eventForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('event_id', document.getElementById('event_id').value);
            formData.append('nama_event', document.getElementById('nama_event').value);
            formData.append('narasumber', document.getElementById('narasumber').value);
            formData.append('deskripsi', document.getElementById('deskripsi').value);
            formData.append('max_partisipan', document.getElementById('max_partisipan').value);
            formData.append('status', document.getElementById('status').value);
            formData.append('lokasi', document.getElementById('lokasi').value);
            formData.append('tanggal', document.getElementById('tanggal').value);
            formData.append('waktu', document.getElementById('waktu').value);
            formData.append('persyaratan', document.getElementById('persyaratan').value);
            formData.append('latitude', document.getElementById('latitude').value);
            formData.append('longitude', document.getElementById('longitude').value);
            
            const fotoFile = document.getElementById('foto_event').files[0];
            if (fotoFile) formData.append('foto_event', fotoFile);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Event berhasil diupdate!', 'success');
                    setTimeout(() => window.location.href = 'event_lingkungan.php', 1500);
                } else {
                    showToast(result.message || 'Gagal mengupdate event', 'error');
                }
            } catch (error) {
                showToast('Terjadi kesalahan pada server', 'error');
            }
        });
        
        function showToast(msg, type) {
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