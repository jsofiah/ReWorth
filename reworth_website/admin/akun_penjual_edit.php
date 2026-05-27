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
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    // Ambil ID penjual dari URL
    $idPenjual = $_GET['id'] ?? '';
    if (empty($idPenjual)) {
        header("Location: kelola_akun.php?tab=penjual");
        exit;
    }

    function getPenjualById($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq." . $id . "&select=*";
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
            return !empty($data) ? $data[0] : null;
        }
        return null;
    }

    $penjual = getPenjualById($supabaseUrl, $supabaseKey, $idPenjual);
    if (!$penjual) {
        header("Location: kelola_akun.php?tab=penjual&error=notfound");
        exit;
    }

    $userName = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto = $_SESSION['foto_profil'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $nama = $_POST['nama_penjual'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_ulang = $_POST['password_ulang'] ?? '';
        $status = $_POST['status'] ?? 'menunggu_verifikasi';
        
        if (empty($nama) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Data wajib tidak lengkap']);
            exit;
        }
        
        $data = [
            'nama_penjual' => $nama,
            'email' => $email,
            'status' => $status,
        ];
        
        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
                exit;
            }
            if ($password !== $password_ulang) {
                echo json_encode(['success' => false, 'message' => 'Konfirmasi password tidak sama']);
                exit;
            }
            $data['password'] = md5($password);
        }
        
        $foto_path = $penjual['foto_profil'] ?? '';
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            if (!empty($foto_path)) {
                $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $foto_path;
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
            
            $file = $_FILES['foto_profil'];
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'penjual/' . time() . '_' . uniqid() . '.' . $extension;
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
                $data['foto_profil'] = $filename;
            }
        }
        
        if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] == '1') {
            if (!empty($foto_path)) {
                $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $foto_path;
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
            $data['foto_profil'] = '';
        }
        
        $updateUrl = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq." . $idPenjual;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $updateUrl);
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
            $statusText = ($status == 'verified') ? 'Verified' : 'Menunggu Verifikasi';
            $logData = [
                'id_admin' => $_SESSION['id_admin'],
                'aktivitas' => 'Mengedit penjual: ' . $nama . ' (Status: ' . $statusText . ')',
                'tabel_terkait' => 'penjual',
                'id_data' => $idPenjual,
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
            
            echo json_encode(['success' => true, 'message' => 'Penjual berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data']);
        }
        exit;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Edit Penjual</title>
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
                <a href="kelola_akun.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Edit Penjual</h1>
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
                        <h2>Edit Penjual</h2>
                    </div>
                    <form id="penjualForm" enctype="multipart/form-data">
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Nama Penjual <span class="text-danger">*</span></label>
                                <input type="text" class="form-control-custom" id="nama_penjual" placeholder="Masukkan nama penjual/toko" required value="<?= htmlspecialchars($penjual['nama_penjual'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control-custom" id="email" placeholder="contoh@email.com" required value="<?= htmlspecialchars($penjual['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Password <span class="text-muted" style="font-size: 11px;">(Kosongkan jika tidak diubah)</span></label>
                                <div class="input-password-wrap">
                                    <input type="password" class="form-control-custom" id="password" placeholder="Minimal 6 karakter">
                                    <button type="button" class="toggle-pw" data-target="password">
                                        <i class="bi bi-eye-slash-fill"></i>
                                    </button>
                                </div>
                                <small class="text-muted" style="font-size: 11px;">Minimal 6 karakter, isi hanya jika ingin mengubah password</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ulangi Password</label>
                                <div class="input-password-wrap">
                                    <input type="password" class="form-control-custom" id="password_ulang" placeholder="Ulangi password">
                                    <button type="button" class="toggle-pw" data-target="password_ulang">
                                        <i class="bi bi-eye-slash-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-control-custom" id="status" required>
                                    <option value="menunggu_verifikasi" <?= ($penjual['status'] ?? 'menunggu_verifikasi') == 'menunggu_verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                                    <option value="verified" <?= ($penjual['status'] ?? '') == 'verified' ? 'selected' : '' ?>>Verified</option>
                                </select>
                                <small class="text-muted" style="font-size: 11px;">Status penjual, jika Verified maka dapat mengakses semua fitur</small>
                            </div>
                            <div class="form-group">
                                <!-- Kosong untuk menjaga layout -->
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Foto Profil</label>
                            <div class="file-upload-wrapper">
                                <label class="file-upload-label">
                                    <i class="bi bi-cloud-upload"></i>
                                    <span id="fileName"><?= !empty($penjual['foto_profil']) ? 'Ganti foto' : 'Pilih file foto' ?></span>
                                    <input type="file" class="file-upload-input" id="foto_profil" accept="image/*" onchange="previewImage(this)">
                                </label>
                            </div>
                            <div class="image-preview" id="imagePreview">
                                <?php if (!empty($penjual['foto_profil'])): ?>
                                    <div class="preview-item">
                                        <img src="<?= htmlspecialchars(getSupabaseImageUrl($penjual['foto_profil'])) ?>" alt="Preview">
                                        <button type="button" class="remove-image" onclick="removeExistingImage()"><i class="bi bi-x"></i></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted" style="font-size: 11px;">Format: JPG, PNG, GIF. Maksimal 2MB</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="window.location.href='kelola_akun.php?tab=penjual'">Batal</button>
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
            const fileName = input.files[0]?.name || 'Pilih file foto';
            document.getElementById('fileName').textContent = fileName;
            
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                if (input.files[0].size > 2 * 1024 * 1024) {
                    showToast('Ukuran file terlalu besar! Maksimal 2MB', 'error');
                    input.value = '';
                    document.getElementById('fileName').textContent = 'Pilih file foto';
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
            document.getElementById('foto_profil').value = '';
            document.getElementById('fileName').textContent = 'Pilih file foto';
        }
        
        function removeExistingImage() {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            document.getElementById('foto_profil').value = '';
            document.getElementById('fileName').textContent = 'Pilih file foto';
            
            let hiddenInput = document.getElementById('hapus_foto');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'hapus_foto';
                hiddenInput.id = 'hapus_foto';
                hiddenInput.value = '1';
                document.getElementById('penjualForm').appendChild(hiddenInput);
            }
        }
        
        // Toggle password visibility
        document.querySelectorAll('.toggle-pw').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye-slash-fill');
                    icon.classList.add('bi-eye-fill');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-fill');
                    icon.classList.add('bi-eye-slash-fill');
                }
            });
        });
        
        document.getElementById('penjualForm').onsubmit = async function(e) {
            e.preventDefault();
            
            const nama = document.getElementById('nama_penjual').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const passwordUlang = document.getElementById('password_ulang').value;
            const status = document.getElementById('status').value;
            
            if (!nama || !email) {
                showToast('Data wajib tidak lengkap!', 'error');
                return;
            }
            
            if (password && password.length < 6) {
                showToast('Password minimal 6 karakter!', 'error');
                return;
            }
            
            if (password !== passwordUlang) {
                showToast('Konfirmasi password tidak sama!', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('nama_penjual', nama);
            formData.append('email', email);
            formData.append('status', status);
            if (password) {
                formData.append('password', password);
                formData.append('password_ulang', passwordUlang);
            }
            
            const fotoFile = document.getElementById('foto_profil').files[0];
            if (fotoFile) {
                formData.append('foto_profil', fotoFile);
            }
            
            const hapusFoto = document.getElementById('hapus_foto');
            if (hapusFoto) {
                formData.append('hapus_foto', hapusFoto.value);
            }
            
            const submitBtn = document.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-spinner"><i class="bi bi-arrow-repeat spin"></i></span> Menyimpan...';
            
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => {
                        window.location.href = 'kelola_akun.php?tab=penjual';
                    }, 2000);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Simpan Perubahan';
                }
            } catch (error) {
                showToast('Terjadi kesalahan pada server', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Simpan Perubahan';
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