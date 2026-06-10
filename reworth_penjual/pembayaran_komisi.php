<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['id_penjual'])) {
    header("Location: login.php");
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

require_once 'subscription_check.php';

$subscription = getSubscriptionStatus($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);
$isPremium = $subscription['is_premium'];
$isExpired = $subscription['is_expired'];
$remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);
$currentLangganan = $subscription['current_subscription'];

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


$getKomisi = curlRequest(
    $supabaseUrl . "/rest/v1/komisi?id_penjual=eq.$userId&order=periode_bulan.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$komisiList = json_decode($getKomisi['response'], true) ?? [];


$totalPending = 0;
foreach ($komisiList as $k) {
    if ($k['status_pembayaran'] == 'pending') {
        $totalPending += $k['total_komisi'];
    }
}


function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending">Belum Dibayar</span>';
        case 'dibayar':
            return '<span class="status-badge status-dibayar">Menunggu Konfirmasi</span>';
        case 'selesai':
            return '<span class="status-badge status-selesai">Selesai</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}


function formatRupiah($angka) {
    if (empty($angka)) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}


$per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_data = count($komisiList);
$total_pages = ceil($total_data / $per_page);
$start = ($current_page - 1) * $per_page;
$current_data = array_slice($komisiList, $start, $per_page);
$showing_from = $total_data > 0 ? $start + 1 : 0;
$showing_to = min($start + $per_page, $total_data);

$errorMessage = $_SESSION['subscription_error'] ?? '';
unset($_SESSION['subscription_error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Komisi - ReWorth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <?php if ($isPremium): ?>
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <?php endif; ?>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="pembayaran_komisi.php" class="nav-link-custom active"><i class="bi bi-cash-coin"></i><span>Pembayaran Komisi</span></a></div>
            <?php if ($isPremium): ?>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
            <?php endif; ?>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Pembayaran Komisi</h1>
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

        <div class="action-bar-wrap">
            <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            
            <div class="total-pending-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="opacity-75">Total Komisi Belum Dibayar</small>
                        <h3><?= formatRupiah($totalPending) ?></h3>
                    </div>
                    <i class="bi bi-wallet2" style="font-size: 48px; opacity: 0.5;"></i>
                </div>
            </div>

            
            <div class="card-custom">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Riwayat Komisi</h5>
                        <?php if ($totalPending > 0): ?>
                        <button class="btn btn-success" onclick="openBayarSemuaModal()">
                            <i class="bi bi-cash-stack"></i> Bayar Semua Komisi
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table">
                            <thead>
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>Periode Bulan</th>
                                    <th class="text-center">Total Komisi</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Tanggal Bayar</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($current_data)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-inbox fs-1 text-muted"></i>
                                            <p class="mt-2 mb-0">Belum ada data komisi</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $showing_from; foreach ($current_data as $k): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($k['periode_bulan'] ?? '-') ?></strong></td>
                                        <td class="text-center"><?= formatRupiah($k['total_komisi'] ?? 0) ?></td>
                                        <td class="text-center"><?= getStatusBadge($k['status_pembayaran'] ?? 'pending') ?></td>
                                        <td class="text-center">
                                            <?php 
                                            if (!empty($k['tanggal_pembayaran'])) {
                                                echo date('d/m/Y', strtotime($k['tanggal_pembayaran']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($k['status_pembayaran'] == 'pending'): ?>
                                                <button class="btn-aksi btn-bayar" onclick="openBayarModal('<?= $k['id_komisi'] ?>', '<?= htmlspecialchars($k['periode_bulan']) ?>', <?= $k['total_komisi'] ?>)">
                                                    <i class="bi bi-cash"></i> Bayar
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $k['id_komisi'] ?>')">
                                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="table-footer mt-3">
                        <div class="showing-text">Showing <b><?= $showing_from ?></b> to <b><?= $showing_to ?></b> of <b><?= $total_data ?></b> entries</div>
                        <div class="pagination-custom">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?= $current_page-1 ?>" class="page-btn page-btn-text">Prev</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>" class="page-btn <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?= $current_page+1 ?>" class="page-btn page-btn-text">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    
    <div id="modalBayar" class="modal-container">
        <div class="modal-title">Bayar Komisi</div>
        <form id="formBayarKomisi" enctype="multipart/form-data">
            <input type="hidden" id="komisi_id" name="komisi_id">
            <div class="form-group">
                <label class="form-label">Periode Bulan</label>
                <input type="text" id="periode_bulan" class="form-control-custom" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Total Komisi</label>
                <input type="text" id="total_komisi" class="form-control-custom" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Upload Bukti Pembayaran</label>
                <div class="file-input-wrapper">
                    <label class="file-input-label">
                        <i class="bi bi-images"></i>
                        Pilih Foto
                        <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*" onchange="previewImage(this)" required>
                    </label>
                </div>
                <div class="image-preview" id="imagePreview"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeBayarModal()">Batal</button>
                <button type="submit" class="btn-save">Kirim Bukti</button>
            </div>
        </form>
    </div>

    
    <div id="modalBayarSemua" class="modal-container">
        <div class="modal-title">Bayar Semua Komisi</div>
        <form id="formBayarSemua" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Total Komisi yang Harus Dibayar</label>
                <input type="text" id="total_semua_komisi" class="form-control-custom" readonly disabled style="font-size: 18px; font-weight: bold;">
            </div>
            <div class="form-group">
                <label class="form-label">Upload Bukti Pembayaran</label>
                <div class="file-input-wrapper">
                    <label class="file-input-label">
                        <i class="bi bi-images"></i>
                        Pilih Foto
                        <input type="file" id="bukti_pembayaran_semua" name="bukti_pembayaran" accept="image/*" onchange="previewImageSemua(this)" required>
                    </label>
                </div>
                <div class="image-preview" id="imagePreviewSemua"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeBayarSemuaModal()">Batal</button>
                <button type="submit" class="btn-save">Kirim Semua</button>
            </div>
        </form>
    </div>

    
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-title">Detail Komisi <button class="modal-close" onclick="closeModal('modalDetail')">&times;</button></div>
            <div id="detailContent"></div>
            <div class="modal-actions mt-3">
                <button class="btn-cancel" onclick="closeModal('modalDetail')">Tutup</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentKomisiId = null;

        function openBayarModal(id, periode, total) {
            currentKomisiId = id;
            document.getElementById('komisi_id').value = id;
            document.getElementById('periode_bulan').value = periode;
            document.getElementById('total_komisi').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
            document.getElementById('modalBayar').style.display = 'block';
        }

        function closeBayarModal() {
            document.getElementById('modalBayar').style.display = 'none';
            document.getElementById('formBayarKomisi').reset();
            document.getElementById('imagePreview').innerHTML = '';
        }

        function openBayarSemuaModal() {
            let totalPending = <?= $totalPending ?>;
            document.getElementById('total_semua_komisi').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalPending);
            document.getElementById('modalBayarSemua').style.display = 'block';
        }

        function closeBayarSemuaModal() {
            document.getElementById('modalBayarSemua').style.display = 'none';
            document.getElementById('formBayarSemua').reset();
            document.getElementById('imagePreviewSemua').innerHTML = '';
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}">`;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewImageSemua(input) {
            const preview = document.getElementById('imagePreviewSemua');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}">`;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }


        document.getElementById('formBayarKomisi')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('komisi_id', document.getElementById('komisi_id').value);
            formData.append('bukti_pembayaran', document.getElementById('bukti_pembayaran').files[0]);
            
            const btn = this.querySelector('.btn-save');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Mengirim...';
            
            try {
                const response = await fetch('pembayaran_komisi_proses.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                showToast(data.message, data.success ? 'success' : 'error');
                
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showToast('Terjadi kesalahan', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Kirim Bukti';
            }
        });


        document.getElementById('formBayarSemua')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('bayar_semua', 'true');
            formData.append('bukti_pembayaran', document.getElementById('bukti_pembayaran_semua').files[0]);
            
            const btn = this.querySelector('.btn-save');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Mengirim...';
            
            try {
                const response = await fetch('pembayaran_komisi_proses.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                showToast(data.message, data.success ? 'success' : 'error');
                
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showToast('Terjadi kesalahan', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Kirim Semua';
            }
        });

        function lihatDetail(id) {
            fetch('pembayaran_komisi_detail.php?id=' + id)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('detailContent').innerHTML = html;
                    openModal('modalDetail');
                })
                .catch(error => {
                    showToast('Gagal memuat detail', 'error');
                });
        }

        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        function showToast(msg, type) {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }


        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });


        const style = document.createElement('style');
        style.textContent = `.spin { animation: spin 1s linear infinite; } @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    </script>
</body>
</html>