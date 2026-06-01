<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $petugasId = $_GET['id'] ?? '';
    if (empty($petugasId)) {
        header("Location: data_petugas.php");
        exit;
    }

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    // Fetch current data
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($petugasId) . "&select=*");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result  = ($httpCode === 200) ? json_decode($resp, true) : [];
    $petugas = $result[0] ?? null;
    if (!$petugas) {
        header("Location: data_petugas.php");
        exit;
    }

    $isAktif = $petugas['status_aktif'] === true || $petugas['status_aktif'] === 'true';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        header('Content-Type: application/json');

        $petugas_id   = $_POST['petugas_id']   ?? '';
        $nama_petugas = trim($_POST['nama_petugas'] ?? '');
        $no_telepon   = trim($_POST['no_telepon']   ?? '');
        $status_aktif = ($_POST['status_aktif'] ?? 'true') === 'true';

        $data = [
            'nama_petugas' => $nama_petugas,
            'no_telepon'   => $no_telepon,
            'status_aktif' => $status_aktif
        ];

        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {

            // Hapus foto lama di bucket
            if (!empty($petugas['foto_profil'])) {
                $delBody = json_encode(["prefixes" => [$petugas['foto_profil']]]);
                $delCh   = curl_init();
                curl_setopt($delCh, CURLOPT_URL, $supabaseUrl . "/storage/v1/object/media");
                curl_setopt($delCh, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($delCh, CURLOPT_POSTFIELDS, $delBody);
                curl_setopt($delCh, CURLOPT_HTTPHEADER, [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey",
                    "Content-Type: application/json"
                ]);
                curl_setopt($delCh, CURLOPT_RETURNTRANSFER, true);
                curl_exec($delCh);
                curl_close($delCh);
            }

            $file      = $_FILES['foto_profil'];
            $filename  = 'petugas/' . time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);
            $uploadUrl = $supabaseUrl . "/storage/v1/object/media/" . $filename;
            $fileData  = file_get_contents($file['tmp_name']);

            $upCh = curl_init();
            curl_setopt($upCh, CURLOPT_URL, $uploadUrl);
            curl_setopt($upCh, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($upCh, CURLOPT_POSTFIELDS, $fileData);
            curl_setopt($upCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: " . $file['type'],
                "x-upsert: true",
                "Content-Length: " . filesize($file['tmp_name'])
            ]);
            curl_setopt($upCh, CURLOPT_RETURNTRANSFER, true);
            $upResp = curl_exec($upCh);
            $upCode = curl_getinfo($upCh, CURLINFO_HTTP_CODE);
            curl_close($upCh);

            if ($upCode === 200 || $upCode === 201) {
                $data['foto_profil'] = $filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Upload foto baru gagal', 'debug' => $upResp]);
                exit;
            }
        }

        $patchCh = curl_init($supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . $petugas_id);
        curl_setopt($patchCh, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($patchCh, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($patchCh, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);
        curl_setopt($patchCh, CURLOPT_RETURNTRANSFER, true);
        $patchResp = curl_exec($patchCh);
        $patchCode = curl_getinfo($patchCh, CURLINFO_HTTP_CODE);
        curl_close($patchCh);

        if ($patchCode === 200 || $patchCode === 204) {
            // Log
            $logData = [
                'id_admin'      => $_SESSION['id_admin'],
                'aktivitas'     => 'Mengedit data petugas: ' . $nama_petugas,
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

            echo json_encode(['success' => true, 'message' => 'Data petugas berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data', 'debug' => $patchResp]);
        }
        exit;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Petugas - DLH</title>
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
                        <h2>Edit Data Petugas</h2>
                    </div>

                    <form id="petugasForm" enctype="multipart/form-data">
                        <input type="hidden" id="petugas_id" value="<?= htmlspecialchars($petugas['id_petugas']) ?>">

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control-custom" id="nama_petugas"
                                value="<?= htmlspecialchars($petugas['nama_petugas'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" class="form-control-custom" id="no_telepon"
                                value="<?= htmlspecialchars($petugas['no_telepon'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status Aktif</label>
                            <select class="form-control-custom" id="status_aktif">
                                <option value="true"  <?= $isAktif ? 'selected' : '' ?>>Aktif</option>
                                <option value="false" <?= !$isAktif ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Foto Petugas Saat Ini</label>
                            <?php if (!empty($petugas['foto_profil'])): ?>
                                <div class="current-image">
                                    <img src="<?= htmlspecialchars(getSupabaseImageUrl($petugas['foto_profil'])) ?>"
                                        style="width:100px;height:100px;object-fit:cover;border-radius:12px;">
                                </div>
                            <?php else: ?>
                                <div style="color:#aaa;font-size:13px;margin-bottom:8px;">Tidak ada foto</div>
                            <?php endif; ?>

                            <label class="form-label" style="margin-top:12px;">Ganti Foto Petugas</label>
                            <div class="file-input-wrapper">
                                <label class="file-input-label">
                                    <i class="bi bi-cloud-upload"></i> Pilih File Baru
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
                    div.innerHTML = `<img src="${e.target.result}">
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
            formData.append('petugas_id',   document.getElementById('petugas_id').value);
            formData.append('nama_petugas', document.getElementById('nama_petugas').value);
            formData.append('no_telepon',   document.getElementById('no_telepon').value);
            formData.append('status_aktif', document.getElementById('status_aktif').value);
            const foto = document.getElementById('foto_profil').files[0];
            if (foto) formData.append('foto_profil', foto);

            try {
                const res  = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast('Data petugas berhasil diperbarui!', 'success');
                    setTimeout(() => window.location.href = 'data_petugas.php', 1500);
                } else {
                    showToast(data.message || 'Gagal mengupdate petugas', 'error');
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