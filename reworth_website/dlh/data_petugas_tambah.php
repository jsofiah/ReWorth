<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        header('Content-Type: application/json');

        $nama_petugas = trim($_POST['nama_petugas'] ?? '');
        $no_telepon   = trim($_POST['no_telepon']   ?? '');

        $status_aktif = ($_POST['status_aktif'] ?? 'true') === 'true';

        if (empty($nama_petugas) || empty($no_telepon)) {
            echo json_encode(['success' => false, 'message' => 'Data wajib tidak lengkap']);
            exit;
        }

        $foto_path = '';

        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $file      = $_FILES['foto_profil'];
            $filename  = 'petugas/' . time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);
            $uploadUrl = $supabaseUrl . "/storage/v1/object/media/" . $filename;
            $fileData  = file_get_contents($file['tmp_name']);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: " . $file['type'],
                "x-upsert: true"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $upResp = curl_exec($ch);
            $upCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($upCode == 200 || $upCode == 201) {
                $foto_path = $filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Upload foto gagal', 'debug' => $upResp]);
                exit;
            }
        }

        $data = [
            'nama_petugas' => $nama_petugas,
            'no_telepon'   => $no_telepon,
            'status_aktif' => $status_aktif,   // boolean
            'foto_profil'  => $foto_path,
            'created_at'   => date('c')
        ];

        $ch = curl_init($supabaseUrl . "/rest/v1/petugas_lapangan");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $newData = json_decode($response, true);
            $newId   = $newData[0]['id_petugas'] ?? null;


            $logData = [
                'id_admin'      => $_SESSION['id_admin'],
                'aktivitas'     => 'Menambahkan petugas baru: ' . $nama_petugas,
                'tabel_terkait' => 'petugas_lapangan',
                'created_at'    => date('c')
            ];
            $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
            curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($logCh, CURLOPT_POSTFIELDS, json_encode($logData));
            curl_setopt($logCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=minimal"
            ]);
            curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($logCh);
            curl_close($logCh);

            echo json_encode(['success' => true, 'message' => 'Petugas berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data', 'debug' => $response]);
        }
        exit;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Petugas - DLH</title>
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
                <a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
            </div>
            <div class="nav-item">
                <a href="laporan_sampah.php" class="nav-link-custom"><i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span></a>
            </div>
            <div class="nav-item">
                <a href="apresiasi_rw.php" class="nav-link-custom"><i class="bi bi-award-fill"></i><span>Apresiasi RW</span></a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a>
            </div>
            <div class="nav-item">
                <a href="laporan_analitik.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a>
            </div>
            <div class="nav-item">
                <a href="data_petugas.php" class="nav-link-custom active"><i class="bi bi-people-fill"></i><span>Data Petugas</span></a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a>
            </div>
        </nav>
        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Data Petugas</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
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
                        <h2>Tambah Data Petugas</h2>
                    </div>

                    <form id="petugasForm" enctype="multipart/form-data">

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control-custom" id="nama_petugas"
                                placeholder="Masukkan nama lengkap petugas" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" class="form-control-custom" id="no_telepon"
                                placeholder="Contoh: 6281234567890" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status Aktif</label>
                            <select class="form-control-custom" id="status_aktif">
                                <option value="true">Aktif</option>
                                <option value="false">Nonaktif</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Foto Petugas</label>
                            <div class="file-input-wrapper">
                                <label class="file-input-label">
                                    <i class="bi bi-cloud-upload"></i> Pilih file
                                    <input type="file" id="foto_profil" accept="image/*" onchange="previewImage(this)">
                                </label>
                                <span class="selected-filename" id="fileName">No file chosen</span>
                            </div>
                            <div class="image-preview" id="imagePreview"></div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel"
                                onclick="window.location.href='data_petugas.php'">Batal</button>
                            <button type="submit" class="btn-submit">Simpan Data Petugas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            document.getElementById('fileName').textContent = input.files[0]?.name || 'No file chosen';
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage(this)">
                            <i class="bi bi-x"></i></button>`;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(btn) {
            btn.closest('.preview-item').remove();
            document.getElementById('foto_profil').value = '';
            document.getElementById('fileName').textContent = 'No file chosen';
        }

        document.getElementById('petugasForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('nama_petugas', document.getElementById('nama_petugas').value);
            formData.append('no_telepon',   document.getElementById('no_telepon').value);
            formData.append('status_aktif', document.getElementById('status_aktif').value);
            const foto = document.getElementById('foto_profil').files[0];
            if (foto) formData.append('foto_profil', foto);

            try {
                const res  = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast('Petugas berhasil ditambahkan!', 'success');
                    setTimeout(() => window.location.href = 'data_petugas.php', 1500);
                } else {
                    showToast(data.message || 'Gagal menambahkan petugas', 'error');
                }
            } catch { showToast('Terjadi kesalahan pada server', 'error'); }
        });

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