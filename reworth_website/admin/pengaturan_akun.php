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

    function getSupabaseImageUrl($path) {
        if (empty($path)) {
            return null;
        }
        $bucketUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";
        return $bucketUrl . ltrim($path, '/');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        header('Content-Type: application/json');

        if (!isset($_SESSION['id_admin'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized'
            ]);
            exit;
        }

        $idAdmin = $_SESSION['id_admin'];

        $nama_admin = trim($_POST['nama_admin'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $password_ulang = trim($_POST['password_ulang'] ?? '');

        $currentUrl =
            $supabaseUrl .
            "/rest/v1/admin?id_admin=eq." .
            $idAdmin .
            "&select=*";

        $currentCh = curl_init();

        curl_setopt($currentCh, CURLOPT_URL, $currentUrl);
        curl_setopt($currentCh, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($currentCh, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);

        $currentResponse = curl_exec($currentCh);

        curl_close($currentCh);

        $currentData = json_decode($currentResponse, true);

        $currentAdmin = $currentData[0] ?? null;

        if (!$currentAdmin) {

            echo json_encode([
                'success' => false,
                'message' => 'Data admin tidak ditemukan'
            ]);

            exit;
        }

        $data = [
            'nama_admin' => $nama_admin,
            'email' => $email
        ];

        if (
            isset($_FILES['foto_profil']) &&
            $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK
        ) {

            $getUrl =
                $supabaseUrl .
                "/rest/v1/admin?id_admin=eq." .
                $idAdmin .
                "&select=foto_profil";

            $getCh = curl_init();

            curl_setopt($getCh, CURLOPT_URL, $getUrl);
            curl_setopt($getCh, CURLOPT_RETURNTRANSFER, true);

            curl_setopt($getCh, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);

            $getResponse = curl_exec($getCh);

            curl_close($getCh);

            $oldData = json_decode($getResponse, true);

            $oldFoto = $oldData[0]['foto_profil'] ?? '';


            if (!empty($oldFoto)) {
                $deleteUrl =
                    $supabaseUrl .
                    "/storage/v1/object/media/" .
                    $oldFoto;

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

            $filename =
                'admin/' .
                time() .
                '_' .
                preg_replace(
                    '/[^A-Za-z0-9.\-_]/',
                    '',
                    $file['name']
                );

            $uploadUrl =
                $supabaseUrl .
                "/storage/v1/object/media/" .
                $filename;

            $fileData =
                file_get_contents($file['tmp_name']);

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
            $uploadHttpCode =
                curl_getinfo($uploadCh, CURLINFO_HTTP_CODE);
            curl_close($uploadCh);

            if (
                $uploadHttpCode === 200 ||
                $uploadHttpCode === 201
            ) {
                $data['foto_profil'] = $filename;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Upload foto gagal',
                    'debug' => $uploadResponse
                ]);
                exit;
            }
        }

        if (!empty($password)) {

            if ($password !== $password_ulang) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Konfirmasi password tidak sama'
                ]);

                exit;
            }

            $data['password'] = md5($password);
        }

        $tidakAdaPerubahan =
            $currentAdmin['nama_admin'] === $nama_admin &&
            $currentAdmin['email'] === $email &&
            empty($password) &&
            !isset($data['foto_profil']);

        if ($tidakAdaPerubahan) {

            echo json_encode([
                'success' => false,
                'message' => 'Tidak ada perubahan data'
            ]);

            exit;
        }

        $updateUrl =
            $supabaseUrl .
            "/rest/v1/admin?id_admin=eq." .
            $idAdmin;

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

        $httpCode =
            curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (
            $httpCode === 200 ||
            $httpCode === 204
        ) {

            $_SESSION['nama_admin'] = $nama_admin;
            $_SESSION['email'] = $email;

            if (isset($data['foto_profil'])) {
                $_SESSION['foto_profil'] =
                    $data['foto_profil'];
            }

            echo json_encode([
                'success' => true,
                'message' => 'Profil berhasil diperbarui'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Gagal update profil',
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
    <title>Admin – Pengaturan Akun</title>
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
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="kelola_akun.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i>
                    Kelola Akun
                </a>
            </div>
            <div class="nav-item">
                <a href="kelola_data_master.php" class="nav-link-custom">
                    <i class="bi bi-database-fill-gear"></i>
                    Kelola Data Master
                </a>
            </div>
            <div class="nav-item">
                <a href="monitor_transaksi.php" class="nav-link-custom">
                    <i class="bi bi-arrow-left-right"></i>
                    Monitor Transaksi
                </a>
            </div>
            <div class="nav-item">
                <a href="pembayaran_komisi.php" class="nav-link-custom">
                    <i class="bi bi-cash-coin"></i>
                    Pembayaran Komisi
                </a>
            </div>
            <div class="nav-item">
                <a href="aktivitas.php" class="nav-link-custom">
                    <i class="bi bi-activity"></i>
                    Aktivitas
                </a>
            </div>
            <div class="nav-item">
                <a href="sponsor.php" class="nav-link-custom">
                    <i class="bi bi-megaphone-fill"></i>
                    Sponsor
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    Laporan dan Keuangan
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom active">
                    <i class="bi bi-gear-fill"></i>
                    Pengaturan Akun
                </a>
            </div>
        </nav>

        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Pengaturan Akun</h1>
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

        <div class="setting-bar-wrap">
            <div class="settings-card">
                <div class="card-accent"></div>
                <div class="card-body-inner">
                    <div class="foto-section">
                        <div class="foto-wrapper">
                            <?php if (!empty($userFoto)): ?>
                                <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                                    id="previewFoto"
                                    alt="Foto Profil"
                                    onerror="this.style.display='none';document.getElementById('fallbackIcon').style.display='flex';">
                                <i class="bi bi-person-fill fallback-icon"
                                id="fallbackIcon"
                                style="display:none;"></i>
                            <?php else: ?>
                                <img src=""
                                    id="previewFoto"
                                    style="display:none;">
                                <i class="bi bi-person-fill fallback-icon"
                                id="fallbackIcon"></i>
                            <?php endif; ?>
                            <label class="foto-upload-btn" for="inputFoto">
                                <i class="bi bi-camera-fill"></i>
                            </label>

                            <input type="file"
                                id="inputFoto"
                                name="foto_profil"
                                accept="image/*"
                                style="display:none;">
                        </div>
                    </div>

                    <div class="form-section-settings">
                        <form id="formProfil" enctype="multipart/form-data">
                            <div class="field-group">
                                <label class="field-label">
                                    Nama Lengkap
                                </label>

                                <input type="text"
                                    class="field-input"
                                    id="inputNama"
                                    name="nama_admin"
                                    placeholder="Masukkan nama lengkap"
                                    value="<?= htmlspecialchars($userName) ?>">

                                <span class="field-error" id="errNama"></span>

                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    Email
                                </label>

                                <input type="email"
                                    class="field-input"
                                    id="inputEmail"
                                    name="email"
                                    placeholder="Masukkan email"
                                    value="<?= htmlspecialchars($userEmail) ?>">
                                <span class="field-error" id="errEmail"></span>
                            </div>

                            <button type="submit"
                                    class="btn-submit"
                                    id="btnSimpanProfil">
                                <span class="btn-text">
                                    Simpan Perubahan
                                </span>

                                <span class="btn-spinner"
                                    style="display:none;">
                                    <i class="bi bi-arrow-repeat spin"></i>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="setting-content-area">
            <div class="settings-card password-card">
                <div class="card-accent"></div>
                <div class="card-body-inner password-section">
                    <h2 class="section-title">
                        Ganti Password
                    </h2>
    
                    <form id="formPassword">
                        <div class="field-group">
    
                            <label class="field-label">
                                Password
                            </label>
    
                            <div class="input-password-wrap">
    
                                <input type="password"
                                    class="field-input"
                                    id="inputPassword"
                                    name="password"
                                    placeholder="Masukkan password">
    
                                <button type="button"
                                        class="toggle-pw"
                                        data-target="inputPassword">
    
                                    <i class="bi bi-eye-slash-fill"></i>
                                </button>
                            </div>
                            <span class="field-error"
                                id="errPassword"></span>
                        </div>
    
                        <div class="field-group">
                            <label class="field-label">
                                Ulangi Password
                            </label>
    
                            <div class="input-password-wrap">
                                <input type="password"
                                    class="field-input"
                                    id="inputPasswordUlang"
                                    name="password_ulang"
                                    placeholder="Masukkan password">
    
                                <button type="button"
                                        class="toggle-pw"
                                        data-target="inputPasswordUlang">
                                    <i class="bi bi-eye-slash-fill"></i>
                                </button>
                            </div>
    
                            <span class="field-error"
                                id="errPasswordUlang"></span>
                        </div>
    
                        <button type="submit"
                                class="btn-submit"
                                id="btnSimpanPassword">
                            <span class="btn-text">
                                Simpan Perubahan
                            </span>
    
                            <span class="btn-spinner"
                                style="display:none;">
                                <i class="bi bi-arrow-repeat spin"></i>
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('formProfil')
        .addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append(
                'nama_admin',
                document.getElementById('inputNama').value
            );
            formData.append(
                'email',
                document.getElementById('inputEmail').value
            );
            const foto =
                document.getElementById('inputFoto').files[0];
            if (foto) {
                formData.append('foto_profil', foto);
            }
            const res = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            showToast(
                data.message,
                data.success ? 'success' : 'error'
            );

            if (data.success) {
                setTimeout(() => {
                    location.reload();
                }, 1200);
            }
        });

        document.getElementById('formPassword')
        .addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append(
                'nama_admin',
                document.getElementById('inputNama').value
            );
            formData.append(
                'email',
                document.getElementById('inputEmail').value
            );
            formData.append(
                'password',
                document.getElementById('inputPassword').value
            );
            formData.append(
                'password_ulang',
                document.getElementById('inputPasswordUlang').value
            );
            const res = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();
            showToast(
                data.message,
                data.success ? 'success' : 'error'
            );
            if (data.success) {
                location.reload();
            }
        });
    </script>
    <script>
        document.getElementById('inputFoto')
        .addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                const preview =
                    document.getElementById('previewFoto');
                const fallback =
                    document.getElementById('fallbackIcon');
                preview.src = ev.target.result;
                preview.style.display = 'block';
                fallback.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    </script>
    <script>
        document.querySelectorAll('.toggle-pw')
        .forEach(button => {
            button.addEventListener('click', function() {
                const targetId =
                    this.dataset.target;
                const input =
                    document.getElementById(targetId);
                const icon =
                    this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove(
                        'bi-eye-slash-fill'
                    );
                    icon.classList.add(
                        'bi-eye-fill'
                    );
                } else {
                    input.type = 'password';
                    icon.classList.remove(
                        'bi-eye-fill'
                    );
                    icon.classList.add(
                        'bi-eye-slash-fill'
                    );
                }
            });
        });
    </script>
    <script>
        function showToast(msg, type = 'success') {

        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            info: 'bi-info-circle-fill'
        };

        const div = document.createElement('div');

        div.className = `toast-item ${type}`;

        div.innerHTML = `
            <i class="bi ${icons[type] || icons.info} toast-icon"></i>
            <span>${msg}</span>
        `;

        document.getElementById('toastContainer').appendChild(div);

        setTimeout(() => {
            div.remove();
        }, 3500);
    }
    </script>
</body>
</html>