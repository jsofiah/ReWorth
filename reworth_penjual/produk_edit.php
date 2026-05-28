<?php
    session_start();

    if (!isset($_SESSION['id_penjual'])) {
        header("Location: login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_penjual'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';
    $userId    = $_SESSION['id_penjual'] ?? '';

    $produkId = $_GET['id'] ?? '';

    if (empty($produkId)) {
        header("Location: produk.php");
        exit;
    }

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return
            "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" .
            ltrim($path, '/');
    }

    function curlRequest(
        $url,
        $method = 'GET',
        $data = null,
        $headers = []
    ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $data
            );
        }
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            $headers
        );
        $response = curl_exec($ch);
        $httpCode =
            curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [
            'response' => $response,
            'httpCode' => $httpCode
        ];
    }

    //hapus foto
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['hapus_foto'])
    ) {

        header('Content-Type: application/json');
        $fotoId = $_POST['hapus_foto'];
        $detailFotoUrl =
            $supabaseUrl .
            "/rest/v1/foto_produk?id_foto=eq." .
            $fotoId .
            "&select=*";
        $detailRequest = curlRequest(
            $detailFotoUrl,
            'GET',
            null,
            [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        );
        $detailResult =
            json_decode(
                $detailRequest['response'],
                true
            );
        $foto = $detailResult[0] ?? null;
        if (!$foto) {
            echo json_encode([
                'success' => false,
                'message' => 'Foto tidak ditemukan'
            ]);
            exit;
        }

        // HAPUS STORAGE
        if (!empty($foto['path_foto'])) {
            $deleteStorageUrl =
                $supabaseUrl .
                "/storage/v1/object/media/" .
                $foto['path_foto'];

            curlRequest(
                $deleteStorageUrl,
                'DELETE',
                null,
                [
                    "apikey: $supabaseKey",
                    "Authorization: Bearer $supabaseKey"
                ]
            );
        }

        // HAPUS DATABASE
        $deleteDbUrl =
            $supabaseUrl .
            "/rest/v1/foto_produk?id_foto=eq." .
            $fotoId;

        $deleteRequest = curlRequest(
            $deleteDbUrl,
            'DELETE',
            null,
            [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Prefer: return=minimal"
            ]
        );

        if (
            $deleteRequest['httpCode'] == 200 ||
            $deleteRequest['httpCode'] == 204
        ) {

            echo json_encode([
                'success' => true,
                'message' => 'Foto berhasil dihapus'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Gagal menghapus foto'
            ]);
        }

        exit;
    }

    //update produk
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        !isset($_POST['hapus_foto'])
    ) {

        header('Content-Type: application/json');

        $nama_produk =
            $_POST['nama_produk'] ?? '';

        $deskripsi_produk =
            $_POST['deskripsi_produk'] ?? '';

        $stok =
            $_POST['stok'] ?? 0;

        $harga =
            $_POST['harga'] ?? 0;

        if (
            empty($nama_produk) ||
            empty($stok) ||
            empty($harga)
        ) {

            echo json_encode([
                'success' => false,
                'message' => 'Data wajib belum lengkap'
            ]);

            exit;
        }

        // UPDATE PRODUK
        $updateData = [
            'nama_produk' => $nama_produk,
            'deskripsi_produk' => $deskripsi_produk,
            'stok' => (int)$stok,
            'harga' => (float)$harga,
        ];

        $updateUrl =
            $supabaseUrl .
            "/rest/v1/produk?id_produk=eq." .
            $produkId;

        $updateRequest = curlRequest(
            $updateUrl,
            'PATCH',
            json_encode($updateData),
            [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=minimal"
            ]
        );

        if (
            $updateRequest['httpCode'] !== 204 &&
            $updateRequest['httpCode'] !== 200
        ) {

            echo json_encode([
                'success' => false,
                'message' => 'Gagal update produk'
            ]);

            exit;
        }

        // TAMBAH FOTO BARU TANPA HAPUS FOTO LAMA
        if (
            isset($_FILES['foto_produk']) &&
            !empty($_FILES['foto_produk']['name'][0])
        ) {

            foreach (
                $_FILES['foto_produk']['tmp_name']
                as $index => $tmpName
            ) {

                if (
                    $_FILES['foto_produk']['error'][$index] !== 0
                ) {
                    continue;
                }

                $originalName =
                    $_FILES['foto_produk']['name'][$index];

                $fileType =
                    $_FILES['foto_produk']['type'][$index];

                $filename =
                    'produk/' .
                    time() .
                    '_' .
                    rand(1000,9999) .
                    '_' .
                    preg_replace(
                        '/[^A-Za-z0-9.\-_]/',
                        '',
                        $originalName
                    );

                $storageUrl =
                    $supabaseUrl .
                    "/storage/v1/object/media/" .
                    $filename;

                $fileData =
                    file_get_contents($tmpName);

                $upload = curl_init();

                curl_setopt(
                    $upload,
                    CURLOPT_URL,
                    $storageUrl
                );

                curl_setopt(
                    $upload,
                    CURLOPT_POST,
                    true
                );

                curl_setopt(
                    $upload,
                    CURLOPT_POSTFIELDS,
                    $fileData
                );

                curl_setopt(
                    $upload,
                    CURLOPT_HTTPHEADER,
                    [
                        "apikey: $supabaseKey",
                        "Authorization: Bearer $supabaseKey",
                        "Content-Type: $fileType",
                        "x-upsert: true"
                    ]
                );

                curl_setopt(
                    $upload,
                    CURLOPT_RETURNTRANSFER,
                    true
                );

                $uploadResponse =
                    curl_exec($upload);

                $uploadCode =
                    curl_getinfo(
                        $upload,
                        CURLINFO_HTTP_CODE
                    );

                curl_close($upload);

                if (
                    $uploadCode == 200 ||
                    $uploadCode == 201
                ) {

                    $fotoData = [
                        'id_produk' => $produkId,
                        'path_foto' => $filename
                    ];

                    curlRequest(
                        $supabaseUrl .
                        "/rest/v1/foto_produk",
                        'POST',
                        json_encode($fotoData),
                        [
                            "apikey: $supabaseKey",
                            "Authorization: Bearer $supabaseKey",
                            "Content-Type: application/json",
                            "Prefer: return=minimal"
                        ]
                    );
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Produk berhasil diupdate'
        ]);

        exit;
    }

    
    //ambil data produk
    $produkUrl =
        $supabaseUrl .
        "/rest/v1/produk?id_produk=eq." .
        $produkId .
        "&select=*";

    $produkRequest = curlRequest(
        $produkUrl,
        'GET',
        null,
        [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]
    );

    $produkResult =
        json_decode(
            $produkRequest['response'],
            true
        );

    $produk = $produkResult[0] ?? null;

    if (!$produk) {
        header("Location: produk.php");
        exit;
    }

    //ambil foto produk

    $fotoUrl =
        $supabaseUrl .
        "/rest/v1/foto_produk?id_produk=eq." .
        $produkId;

    $fotoRequest = curlRequest(
        $fotoUrl,
        'GET',
        null,
        [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]
    );

    $fotoProduk =
        json_decode(
            $fotoRequest['response'],
            true
        ) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk</title>
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
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="produk.php" class="nav-link-custom active">
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
                <a href="pengaturan_toko.php" class="nav-link-custom">
                    <i class="bi bi-shop-window"></i>
                    <span>Pengaturan Toko</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_premium.php" class="nav-link-custom">
                    <i class="bi bi-gem"></i>
                    <span>Pengaturan Premium</span>
                </a>
            </div>
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
                <h1 class="topbar-title">
                    Edit Produk
                </h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name">
                            <?= htmlspecialchars($userName) ?>
                        </div>
                        <div class="topbar-user-email">
                            <?= htmlspecialchars($userEmail) ?>
                        </div>
                    </div>

                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img
                                src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                            >
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
                        <h2>Edit Produk</h2>
                    </div>
                    <form
                        id="produkForm"
                        enctype="multipart/form-data"
                    >
                        <div class="form-group">
                            <label class="form-label">
                                Nama Produk
                            </label>
                            <input
                                type="text"
                                class="form-control-custom"
                                id="nama_produk"
                                value="<?= htmlspecialchars($produk['nama_produk'] ?? '') ?>"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Deskripsi Produk
                            </label>
                            <textarea
                                class="form-control-custom"
                                id="deskripsi_produk"
                            ><?= htmlspecialchars($produk['deskripsi_produk'] ?? '') ?></textarea>
                        </div>
                        <div class="row-2cols">
                            <div class="form-group">
                                <label class="form-label">
                                    Stok
                                </label>
                                <input
                                    type="number"
                                    class="form-control-custom"
                                    id="stok"
                                    value="<?= $produk['stok'] ?? 0 ?>"
                                    required
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    Harga
                                </label>
                                <input
                                    type="number"
                                    class="form-control-custom"
                                    id="harga"
                                    value="<?= $produk['harga'] ?? 0 ?>"
                                    required
                                >
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Foto Saat Ini
                            </label>
                            <div class="image-preview">
                                <?php if (!empty($fotoProduk)): ?>
                                    <?php foreach ($fotoProduk as $foto): ?>
                                        <div
                                            class="preview-item"
                                            id="foto-<?= $foto['id_foto'] ?>"
                                        >
                                            <img
                                                src="<?= htmlspecialchars(getSupabaseImageUrl($foto['path_foto'])) ?>"
                                            >
                                            <button
                                                type="button"
                                                class="remove-image"
                                                onclick="hapusFoto(
                                                    '<?= $foto['id_foto'] ?>'
                                                )"
                                            >
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted">
                                        Belum ada foto produk
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Upload Foto Baru
                            </label>
                            <div class="file-input-wrapper">
                                <label class="file-input-label">
                                    <i class="bi bi-images"></i>
                                    Pilih Foto
                                    <input
                                        type="file"
                                        id="foto_produk"
                                        name="foto_produk[]"
                                        accept="image/*"
                                        multiple
                                        onchange="previewImages(this)"
                                    >
                                </label>
                                <span
                                    class="selected-filename"
                                    id="fileName"
                                >
                                    Belum ada file dipilih
                                </span>
                            </div>
                            <div
                                class="image-preview"
                                id="imagePreview"
                            ></div>
                        </div>
                        <div class="form-actions">
                            <button
                                type="button"
                                class="btn-cancel"
                                onclick="window.location.href='produk.php'"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="btn-submit"
                            >
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="toast-container" id="toastContainer"></div>

    <script>
        let selectedFiles = [];
        function previewImages(input) {
            const newFiles =
                Array.from(input.files);
            selectedFiles = [
                ...selectedFiles,
                ...newFiles
            ];

            renderPreview();
            input.value = '';
        }

        function renderPreview() {
            const preview =
                document.getElementById('imagePreview');
            preview.innerHTML = '';
            document.getElementById('fileName').textContent =
                selectedFiles.length + ' file dipilih';
            selectedFiles.forEach((file, index) => {
                const reader =
                    new FileReader();
                reader.onload = function(e) {
                    const div =
                        document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}">
                        <button
                            type="button"
                            class="remove-image"
                            onclick="removeImage(${index})"
                        >
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            renderPreview();
        }

        async function hapusFoto(idFoto) {
            const konfirmasi =
                confirm('Hapus foto ini?');
            if (!konfirmasi) return;
            const formData =
                new FormData();
            formData.append(
                'hapus_foto',
                idFoto
            );
            try {
                const response =
                    await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                const result =
                    await response.json();
                if (result.success) {
                    document
                        .getElementById(`foto-${idFoto}`)
                        .remove();
                    showToast(
                        'Foto berhasil dihapus',
                        'success'
                    );
                } else {
                    showToast(
                        result.message,
                        'error'
                    );
                }
            } catch (error) {
                showToast(
                    'Terjadi kesalahan server',
                    'error'
                );
            }
        }

        document
            .getElementById('produkForm')
            .addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData =
                new FormData();
            formData.append(
                'nama_produk',
                document.getElementById('nama_produk').value
            );
            formData.append(
                'deskripsi_produk',
                document.getElementById('deskripsi_produk').value
            );
            formData.append(
                'stok',
                document.getElementById('stok').value
            );
            formData.append(
                'harga',
                document.getElementById('harga').value
            );
            selectedFiles.forEach(file => {
                formData.append(
                    'foto_produk[]',
                    file
                );
            });

            try {
                const response =
                    await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                const result =
                    await response.json();
                if (result.success) {
                    showToast(
                        'Produk berhasil diupdate',
                        'success'
                    );
                    setTimeout(() => {
                        window.location.href =
                            'produk.php';
                    }, 1200);
                } else {
                    showToast(
                        result.message ||
                        'Gagal update produk',
                        'error'
                    );
                }
            } catch (error) {
                showToast(
                    'Terjadi kesalahan server',
                    'error'
                );
            }
        });

        function showToast(msg, type = 'success') {
            const icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-x-circle-fill',
                info: 'bi-info-circle-fill'
            };
            const div =
                document.createElement('div');
            div.className =
                `toast-item ${type}`;
            div.innerHTML = `
                <i class="bi ${icons[type]} toast-icon"></i>
                <span>${msg}</span>
            `;
            document
                .getElementById('toastContainer')
                .appendChild(div);
            setTimeout(() => {
                div.remove();
            }, 3500);
        }
    </script>
</body>
</html>