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

        if (!isset($_SESSION['role'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized'
            ]);
            exit;
        }

        $nama_event = $_POST['nama_event'] ?? '';
        $narasumber = $_POST['narasumber'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $max_partisipan = $_POST['max_partisipan'] ?? 0;
        $status = $_POST['status'] ?? 'akan_datang';
        $lokasi = $_POST['lokasi'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $waktu = $_POST['waktu'] ?? '';
        $persyaratan = $_POST['persyaratan'] ?? '';
        $latitude = $_POST['latitude'] ?? '';
        $longitude = $_POST['longitude'] ?? '';
        $id_pembuat = $_POST['id_pembuat'] ?? '';

        if (empty($nama_event) || empty($lokasi) || empty($tanggal)) {

            echo json_encode([
                'success' => false,
                'message' => 'Data wajib tidak lengkap'
            ]);

            exit;
        }

        $foto_path = '';

        if (
            isset($_FILES['foto_event']) &&
            $_FILES['foto_event']['error'] === UPLOAD_ERR_OK
        ) {

            $file = $_FILES['foto_event'];

            $filename = 'event/' . time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);

            $storageUrl =
                $supabaseUrl .
                "/storage/v1/object/media/" .
                $filename;

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

            error_log("UPLOAD RESPONSE: " . $response);
            error_log("UPLOAD CODE: " . $httpCode);

            if ($httpCode == 200 || $httpCode == 201) {

                $foto_path = $filename;

            } else {

                echo json_encode([
                    'success' => false,
                    'message' => 'Upload foto gagal',
                    'debug' => $response,
                    'http_code' => $httpCode
                ]);
                exit;
            }
        }

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
            'longitude' => $longitude,
            'id_pembuat' => $id_pembuat,
            'foto_event' => $foto_path
        ];

        $ch = curl_init($supabaseUrl . "/rest/v1/event");

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($data)
        );

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

        if ($httpCode === 201 || $httpCode === 200) {
            $getEventUrl =
                $supabaseUrl .
                "/rest/v1/event?select=id_event&order=created_at.desc&limit=1";
            $getCh = curl_init();
            curl_setopt($getCh, CURLOPT_URL, $getEventUrl);
            curl_setopt($getCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($getCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);
            $getResponse = curl_exec($getCh);
            $getHttpCode = curl_getinfo($getCh, CURLINFO_HTTP_CODE);
            curl_close($getCh);
            $eventResult = json_decode($getResponse, true);
            $newEventId = $eventResult[0]['id_event'] ?? null;

            $logData = [
                'id_admin' => $_SESSION['id_admin'],
                'aktivitas' => 'Menambahkan event baru: ' . $nama_event,
                'tabel_terkait' => 'event',
                'id_data' => $newEventId,
                'created_at' => date('c')
            ];

            $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
            curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt(
                $logCh,
                CURLOPT_POSTFIELDS,
                json_encode($logData)
            );
            curl_setopt($logCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ]);
            curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
            $logResponse = curl_exec($logCh);
            $logHttpCode = curl_getinfo($logCh, CURLINFO_HTTP_CODE);
            $logError = curl_error($logCh);
            curl_close($logCh);

            $pengumumanData = [
                'judul' => 'Event Baru: ' . $nama_event,
                'deskripsi' => 'Yuk daftar dan ikuti event "' . $nama_event . '"! Jangan sampai ketinggalan, segera daftarkan diri Anda.',
                'created_at' => date('c')
            ];

            $pengumumanCh = curl_init($supabaseUrl . "/rest/v1/pengumuman");
            curl_setopt($pengumumanCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($pengumumanCh, CURLOPT_POSTFIELDS, json_encode($pengumumanData));
            curl_setopt($pengumumanCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=minimal"
            ]);
            curl_setopt($pengumumanCh, CURLOPT_RETURNTRANSFER, true);
            $pengumumanResponse = curl_exec($pengumumanCh);
            $pengumumanHttpCode = curl_getinfo($pengumumanCh, CURLINFO_HTTP_CODE);
            $pengumumanError = curl_error($pengumumanCh);
            curl_close($pengumumanCh);

            $fcmPayload = [
                'topic' => 'all_users',
                'title' => 'Event Baru: ' . $nama_event,
                'body'  => 'Yuk daftar dan ikuti event "' . $nama_event . '"! Jangan sampai ketinggalan!'
            ];

            $chFcm = curl_init();
            curl_setopt($chFcm, CURLOPT_URL, "https://rxzrbyqqhkxemdjbcntc.supabase.co/functions/v1/send-user-notification");
            curl_setopt($chFcm, CURLOPT_POST, true);
            curl_setopt($chFcm, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
            curl_setopt($chFcm, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $supabaseKey",
                "apikey: $supabaseKey",
                "Content-Type: application/json"
            ]);
            curl_setopt($chFcm, CURLOPT_RETURNTRANSFER, true);
            $fcmResponse = curl_exec($chFcm);
            $fcmHttpCode = curl_getinfo($chFcm, CURLINFO_HTTP_CODE);
            $fcmError = curl_error($chFcm);
            curl_close($chFcm);

            echo json_encode([
                'success' => true,
                'message' => 'Event berhasil ditambahkan',
                'debug_log' => [
                    'event_id' => $newEventId,
                    'log_response' => $logResponse,
                    'log_http_code' => $logHttpCode,
                    'log_error' => $logError,
                    'pengumuman_response' => $pengumumanResponse,
                    'pengumuman_http_code' => $pengumumanHttpCode,
                    'pengumuman_error' => $pengumumanError
                ]
            ]);
            exit;
        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyimpan data',
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
    <title>Tambah Event - DLH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_sampah.php" class="nav-link-custom">
                    <i class="bi bi-exclamation-diamond-fill"></i>
                    <span>Laporan Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="apresiasi_rw.php" class="nav-link-custom">
                    <i class="bi bi-award-fill"></i>
                    <span>Apresiasi RW</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom active">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>Event Lingkungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_analitik.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Analitik</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_petugas.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Petugas</span>
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
                        <h2>Tambah Event</h2>
                    </div>
                    <form id="eventForm" enctype="multipart/form-data">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Nama Event</label>
                                <input type="text" class="form-control-custom" id="nama_event" placeholder="Masukkan nama event/kegiatan" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Narasumber</label>
                                <input type="text" class="form-control-custom" id="narasumber" placeholder="Nama narasumber/instansi">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control-custom" id="deskripsi" placeholder="Tujuan, manfaat, dan detail kegiatan"></textarea>
                        </div>

                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Max Peserta</label>
                                <input type="number" class="form-control-custom" id="max_partisipan" placeholder="0 (tidak terbatas)" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status Event</label>
                                <select class="form-control-custom" id="status">
                                    <option value="akan_datang">Akan Datang</option>
                                    <option value="berlangsung">Berlangsung</option>
                                    <option value="selesai">Selesai</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lokasi / Alamat</label>
                            <input type="text" class="form-control-custom" id="lokasi" placeholder="Alamat lengkap tempat kegiatan" required>
                        </div>

                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control-custom" id="tanggal" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control-custom" id="waktu" value="09:00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Persyaratan Peserta</label>
                            <textarea class="form-control-custom" id="persyaratan" placeholder="Syarat yang harus dipenuhi peserta"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Upload Foto Event</label>
                            <div class="file-input-wrapper">
                                <label class="file-input-label">
                                    <i class="bi bi-cloud-upload"></i> Pilih File
                                    <input type="file" id="foto_event" accept="image/*" onchange="previewImage(this)">
                                </label>
                                <span class="selected-filename" id="fileName">No file chosen</span>
                            </div>
                            <div class="image-preview" id="imagePreview"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lokasi Koordinat (wajib)</label>
                            <div class="row-2cols" style="margin-bottom: 10px;">
                                <input type="text" class="form-control-custom" id="latitude" placeholder="Latitude">
                                <input type="text" class="form-control-custom" id="longitude" placeholder="Longitude">
                            </div>
                            <button type="button" class="btn-location" id="getLocationBtn">
                                <i class="bi bi-geo-alt-fill"></i> Ambil Koordinat Saat Ini
                            </button>
                            <div class="coordinate-info" id="coordinateInfo"></div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='event_lingkungan.php'">Batal</button>
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
            const fileName = input.files[0]?.name || 'No file chosen';
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
                    
                    coordinateInfo.innerHTML = '<i class="bi bi-check-circle-fill" style="color: #10b981;"></i> Koordinat berhasil didapatkan!';
                    coordinateInfo.style.color = '#10b981';
                    
                    showToast('Koordinat berhasil didapatkan!', 'success');
                    
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
            
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            
            if (!latitude || !longitude) {
                showToast('Koordinat lokasi harus diisi! Silakan ambil koordinat terlebih dahulu.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('nama_event', document.getElementById('nama_event').value);
            formData.append('narasumber', document.getElementById('narasumber').value);
            formData.append('deskripsi', document.getElementById('deskripsi').value);
            formData.append('max_partisipan', document.getElementById('max_partisipan').value);
            formData.append('status', document.getElementById('status').value);
            formData.append('lokasi', document.getElementById('lokasi').value);
            formData.append('tanggal', document.getElementById('tanggal').value);
            formData.append('waktu', document.getElementById('waktu').value);
            formData.append('persyaratan', document.getElementById('persyaratan').value);
            formData.append('latitude', latitude);
            formData.append('longitude', longitude);
            formData.append('id_pembuat', '<?= $userId ?>');
            
            const fotoFile = document.getElementById('foto_event').files[0];
            if (fotoFile) {
                formData.append('foto_event', fotoFile);
            }
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Event berhasil ditambahkan!', 'success');
                    setTimeout(() => {
                        window.location.href = 'event_lingkungan.php';
                    }, 1500);
                } else {
                    showToast(result.message || 'Gagal menambahkan event', 'error');
                }
            } catch (error) {
                showToast('Terjadi kesalahan pada server', 'error');
            }
        });
        
        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
        
        const today = new Date();
        const futureDate = new Date(today.setDate(today.getDate() + 7));
        document.getElementById('tanggal').value = futureDate.toISOString().split('T')[0];
    </script>
</body>
</html>