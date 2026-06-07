<?php
    session_start();

    if (!isset($_SESSION['id_penjual'])) {
        header("Location: login.php");
        exit;
    }    
    
    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
    require_once 'subscription_check.php';

    $subscription = requirePremium($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

    $isPremium = $subscription['is_premium'];
    $remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

    $userName  = $_SESSION['nama_penjual'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';
    $userId    = $_SESSION['id_penjual'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) {
            return null;
        }

        $bucketUrl =
            "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/";

        return $bucketUrl . ltrim($path, '/');
    }

    function getProduk($supabaseUrl, $supabaseKey, $userId) {

        $url =
            $supabaseUrl .
            "/rest/v1/produk?" .
            "select=*,foto_produk(path_foto)" .
            "&id_penjual=eq.$userId" .
            "&order=created_at.desc";

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
            return json_decode($response, true) ?: [];
        }

        return [];
    }

    $produkList = getProduk($supabaseUrl, $supabaseKey, $userId);

    $per_page = 10;

    $current_page =
        isset($_GET['page'])
        ? (int) $_GET['page']
        : 1;

    $total_produk =
        count($produkList);

    $total_pages =
        ceil($total_produk / $per_page);

    $start =
        ($current_page - 1) * $per_page;

    $current_produk =
        array_slice($produkList, $start, $per_page);

    $showing_from =
        $total_produk > 0
        ? $start + 1
        : 0;

    $showing_to =
        min($start + $per_page, $total_produk);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjual – Manajemen Produk</title>
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
                <a href="pembayaran_komisi.php" class="nav-link-custom">
                    <i class="bi bi-cash-coin"></i>
                    <span>Pembayaran Komisi</span>
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
                    Manajemen Produk
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
                        <?php if (!empty($userFoto)):
                            $fotoUrl = getSupabaseImageUrl($userFoto);
                        ?>
                            <img
                                src="<?= htmlspecialchars($fotoUrl) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                            >
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar-wrap">
            <div class="action-bar">
                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        class="search-input"
                        placeholder="Cari produk..."
                        id="searchInput"
                    >
                </div>

                <button
                    class="btn-tambah"
                    onclick="window.location.href='produk_tambah.php'"
                >
                    <i class="bi bi-plus-lg"></i>
                    Tambah Produk
                </button>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table">
                        <colgroup>
                            <col style="width:50px;">
                            <col style="width:320px;">
                            <col style="width:350px;">
                            <col style="width:120px;">
                            <col style="width:180px;">
                            <col style="width:240px;">
                        </colgroup>

                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Produk</th>
                                <th>Deskripsi</th>
                                <th>Stok</th>
                                <th>Harga</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody id="tableBody">
                            <?php if (!empty($current_produk)): ?>
                                <?php foreach ($current_produk as $index => $p): ?>
                                    <?php
                                        $foto =
                                            $p['foto_produk'][0]['path_foto']
                                            ?? '';
                                    ?>

                                    <tr>
                                        <td>
                                            <?= $start + $index + 1 ?>
                                        </td>
                                        <td>
                                            <div class="produk-info">
                                                <?php if ($foto): ?>
                                                    <img
                                                        src="<?= getSupabaseImageUrl($foto) ?>"
                                                        class="produk-img"
                                                    >
                                                <?php else: ?>
                                                    <div
                                                        class="produk-img d-flex align-items-center justify-content-center"
                                                    >
                                                        <i class="bi bi-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="produk-nama">
                                                    <?= htmlspecialchars($p['nama_produk']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="produk-deskripsi">
                                                <?= htmlspecialchars($p['deskripsi_produk']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="stok-badge">
                                                <?= $p['stok'] ?> pcs
                                            </span>
                                        </td>
                                        <td>
                                            <div class="harga-text">
                                                Rp <?= number_format($p['harga'], 0, ',', '.') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button
                                                    class="btn-aksi btn-edit"
                                                    onclick="editProduk('<?= $p['id_produk'] ?>')"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                    Edit
                                                </button>
                                                <button
                                                    class="btn-aksi btn-hapus"
                                                    onclick="hapusProduk('<?= $p['id_produk'] ?>')"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-box-seam"></i>
                                            Belum ada produk tersedia
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div class="showing-text">
                        Showing
                        <b><?= $showing_from ?></b>
                        to
                        <b><?= $showing_to ?></b>
                        of
                        <b><?= $total_produk ?></b>
                        entries
                    </div>

                    <div class="pagination-custom">
                        <?php if ($current_page > 1): ?>
                            <a
                                href="?page=<?= $current_page - 1 ?>"
                                class="page-btn page-btn-text"
                            >
                                Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a
                                href="?page=<?= $i ?>"
                                class="page-btn <?= $i == $current_page ? 'active' : '' ?>"
                            >
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($current_page < $total_pages): ?>
                            <a
                                href="?page=<?= $current_page + 1 ?>"
                                class="page-btn page-btn-text"
                            >
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="modalHapus">
        <div class="modal-box" style="max-width:400px;">
            <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
            <div class="confirm-text">
                <h3>Hapus Produk?</h3>
                <p>Tindakan ini tidak dapat dibatalkan. Data produk akan dihapus secara permanen.</p>
            </div>
            <div class="modal-actions" style="justify-content:center; margin-top:20px;">
                <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
                <button class="btn-aksi btn-hapus" style="padding:10px 22px; font-size:14px; border-radius:12px;" onclick="confirmHapus()">
                    <i class="bi bi-trash3"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        let deletingId = null;

        document
            .getElementById('searchInput')
            .addEventListener('input', function() {

            const q =
                this.value.toLowerCase();

            document
                .querySelectorAll('#tableBody tr')
                .forEach(row => {

                const text =
                    row.textContent.toLowerCase();

                row.style.display =
                    text.includes(q)
                    ? ''
                    : 'none';
            });
        });

        function editProduk(id) {

            window.location.href =
                `produk_edit.php?id=${id}`;
        }

        function openModal(id) {

            document
                .getElementById(id)
                .classList.add('show');
        }

        function closeModal(id) {

            document
                .getElementById(id)
                .classList.remove('show');
        }

        document
            .querySelectorAll('.modal-overlay')
            .forEach(m => {

            m.addEventListener('click', e => {

                if (e.target === m) {

                    m.classList.remove('show');
                }
            });
        });

        function hapusProduk(id) {

            deletingId = id;

            openModal('modalHapus');
        }

        function confirmHapus() {

            if (!deletingId) return;

            fetch('produk_hapus.php', {

                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body:
                    'id=' +
                    encodeURIComponent(deletingId)
            })

            .then(res => res.json())

            .then(data => {

                console.log(data);

                if (data.success) {

                    showToast(
                        'Produk berhasil dihapus.',
                        'success'
                    );

                    closeModal('modalHapus');

                    setTimeout(() => {

                        location.reload();

                    }, 800);

                } else {

                    showToast(
                        data.message ||
                        'Gagal menghapus produk.',
                        'error'
                    );
                }
            })

            .catch(error => {

                console.error(error);

                showToast(
                    'Terjadi kesalahan server.',
                    'error'
                );
            });
        }

        function showToast(
            msg,
            type = 'success'
        ) {

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