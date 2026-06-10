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

$subscription = requirePremium($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

$isPremium = $subscription['is_premium'];
$remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

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


$getPesanan = curlRequest(
    $supabaseUrl . "/rest/v1/pesanan?select=*,produk(*),pengguna(*)&order=created_at.desc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$semuaPesanan = json_decode($getPesanan['response'], true) ?? [];


$pesananList = [];
foreach ($semuaPesanan as $p) {
    if ($p['produk'] && $p['produk']['id_penjual'] == $userId) {
        $pesananList[] = $p;
    }
}


$filterStatus = $_GET['status'] ?? 'semua';
$search = $_GET['search'] ?? '';


function getStatusBadge($status) {
    $status = strtolower($status);
    
    if ($status == 'selesai') {
        return '<span class="status-badge status-selesai">Selesai</span>';
    } elseif ($status == 'diproses') {
        return '<span class="status-badge status-berlangsung">Diproses</span>';
    } elseif ($status == 'dikirim') {
        return '<span class="status-badge status-dikirim">Dikirim</span>';
    } elseif ($status == 'menunggu') {
        return '<span class="status-badge status-akan_datang">Menunggu Konfirmasi</span>';
    } elseif ($status == 'ditolak') {
        return '<span class="status-badge status-akan_datang">Ditolak</span>';
    } else {
        return '<span class="status-badge status-akan_datang">' . ucfirst($status) . '</span>';
    }
}


function escapeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan - ReWorth</title>
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
            <div class="nav-item"><a href="produk.php" class="nav-link-custom"><i class="bi bi-box-seam-fill"></i><span>Manajemen Produk</span></a></div>
            <div class="nav-item"><a href="pesanan.php" class="nav-link-custom active"><i class="bi bi-bag-check-fill"></i><span>Manajemen Pesanan</span></a></div>
            <div class="nav-item"><a href="langganan.php" class="nav-link-custom"><i class="bi bi-stars"></i><span>Langganan</span></a></div>
            <div class="nav-item"><a href="pembayaran_komisi.php" class="nav-link-custom"><i class="bi bi-cash-coin"></i><span>Pembayaran Komisi</span></a></div>
            <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
            <div class="nav-item"><a href="pengaturan_toko.php" class="nav-link-custom"><i class="bi bi-shop-window"></i><span>Pengaturan Toko</span></a></div>
            <div class="nav-item"><a href="pengaturan_premium.php" class="nav-link-custom"><i class="bi bi-gem"></i><span>Pengaturan Premium</span></a></div>
        </nav>
        <div class="sidebar-logout"><a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Manajemen Pesanan</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= escapeHtml($userName) ?></div>
                        <div class="topbar-user-email"><?= escapeHtml($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= escapeHtml(getSupabaseImageUrl($userFoto)) ?>" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
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
                    <input type="text" class="search-input" placeholder="Cari pesanan (nama produk, pembeli, alamat)..." id="searchInput">
                </div>
                <div class="filter-dropdown">
                    <button class="btn-filter" onclick="toggleFilter()">
                        <i class="bi bi-sliders2"></i> Filter 
                        <span id="filterBadge" class="filter-badge" style="display: none;"></span>
                    </button>
                    <div class="filter-box" id="filterBox">
                        <div class="filter-group">
                            <label>Status Pesanan</label>
                            <select id="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="menunggu">Menunggu Konfirmasi</option>
                                <option value="diproses">Diproses</option>
                                <option value="dikirim">Dikirim</option>
                                <option value="selesai">Selesai</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="button" onclick="resetFilters()">Reset</button>
                            <button type="button" onclick="applyFilter()">Terapkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table" id="dynamicTable">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>Nama Pembeli</th>
                                <th>Nama Produk</th>
                                <th>Alamat Pengiriman</th>
                                <th class="text-center">Total Bayar</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($pesananList as $p):

                                if ($filterStatus !== 'semua' && !empty($filterStatus)) {
                                    if ($p['status'] !== $filterStatus) continue;
                                }

                                if (!empty($search)) {
                                    $namaPembeli = strtolower($p['pengguna']['nama_lengkap'] ?? '');
                                    $namaProduk = strtolower($p['produk']['nama_produk'] ?? '');
                                    $alamat = strtolower($p['alamat_pengiriman'] ?? '');
                                    $searchLower = strtolower($search);
                                    if (strpos($namaProduk, $searchLower) === false && 
                                        strpos($namaPembeli, $searchLower) === false && 
                                        strpos($alamat, $searchLower) === false) {
                                        continue;
                                    }
                                }
                                
                                $namaPembeli = $p['pengguna']['nama_lengkap'] ?? '-';
                                $namaProduk = $p['produk']['nama_produk'] ?? '-';
                                $alamat = $p['alamat_pengiriman'] ?? '-';
                                $status = $p['status'];
                                $id_pesanan = $p['id_pesanan'];
                                $total_bayar = $p['total_bayar'] ?? 0;
                            ?>
                            <tr data-status="<?= escapeHtml($status) ?>" data-search="<?= escapeHtml(strtolower($namaPembeli . ' ' . $namaProduk . ' ' . $alamat)) ?>">
                                <td class="text-center"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= escapeHtml($namaPembeli) ?>
                                    </div>
                                </td>
                                <td><?= escapeHtml($namaProduk) ?></td>
                                <td>
                                    <div class="alamat-preview" title="<?= escapeHtml($alamat) ?>">
                                        <?= escapeHtml(substr($alamat, 0, 50)) ?>...
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                                </td>
                                <td class="text-center"><?= getStatusBadge($status) ?></td>
                                <td class="text-center">
                                    <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $p['id_pesanan'] ?>')">
                                        <i class="bi bi-file-earmark-text"></i> Lihat
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ($no == 1): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <p class="mt-2 mb-0">Belum ada pesanan</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="max-width: 550px;">
            <div class="modal-title">Detail Pesanan <button class="modal-close" onclick="closeModal('modalDetail')">&times;</button></div>
            <div id="detailContent"></div>
            <div class="modal-actions mt-3">
                <button class="btn-cancel" onclick="closeModal('modalDetail')">Tutup</button>
            </div>
        </div>
    </div>

    
    <div id="modalKirimContainer" class="modal-container">
        <div class="modal-title">Input Pengiriman</div>
        <div class="form-group">
            <label class="form-label">Jasa Kirim</label>
            <select id="jasaKirim" class="form-control-custom">
                <option value="">-- Pilih Jasa Kirim --</option>
                <option value="JNE">JNE</option>
                <option value="SiCepat">SiCepat</option>
                <option value="J&T">J&T</option>
                <option value="Pos Indonesia">Pos Indonesia</option>
                <option value="Ninja Express">Ninja Express</option>
                <option value="Grab Express">Grab Express</option>
                <option value="GoSend">GoSend</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Nomor Resi</label>
            <input type="text" id="nomorResi" class="form-control-custom" placeholder="Masukkan nomor resi">
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeKirimModal()">Batal</button>
            <button class="btn-save" onclick="submitKirim()">Kirim</button>
        </div>
    </div>

    
    <div id="modalTolakContainer" class="modal-container">
        <div class="modal-title">Tolak Pesanan</div>
        <div class="form-group">
            <label class="form-label">Alasan Penolakan</label>
            <textarea id="alasanTolak" class="form-control-custom" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeTolakModal()">Batal</button>
            <button class="btn-save" onclick="submitTolak()">Kirim</button>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPesananId = null;
        let currentFilters = {
            status: '<?= $filterStatus ?>',
            search: '<?= $search ?>'
        };

        function updateFilterBadge() {
            const badge = document.getElementById('filterBadge');
            if (!badge) return;
            
            let activeCount = 0;
            if (currentFilters.status && currentFilters.status !== 'semua') activeCount++;
            
            if (activeCount > 0) {
                badge.textContent = activeCount;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function toggleFilter() {
            const filterBox = document.getElementById('filterBox');
            if (filterBox) {
                filterBox.classList.toggle('show');
            }
        }

        function applyFilter() {
            const status = document.getElementById('filterStatus')?.value || '';
            const search = document.getElementById('searchInput')?.value || '';
            
            let queryParams = new URLSearchParams();
            if (status && status !== '') queryParams.append('status', status);
            if (search) queryParams.append('search', search);
            
            window.location.href = `pesanan.php?${queryParams.toString()}`;
        }

        function resetFilters() {
            window.location.href = 'pesanan.php';
        }

        function initFilters() {
            const filterStatus = document.getElementById('filterStatus');
            if (filterStatus && currentFilters.status && currentFilters.status !== 'semua') {
                filterStatus.value = currentFilters.status;
            }
            
            const searchInput = document.getElementById('searchInput');
            if (searchInput && currentFilters.search) {
                searchInput.value = currentFilters.search;
            }
        }


        function attachSearchListener() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            

            if (currentFilters.search) {
                searchInput.value = currentFilters.search;
            }
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const tbody = document.querySelector('#dynamicTable tbody');
                if (!tbody) return;
                
                const rows = tbody.querySelectorAll('tr');
                let visibleCount = 0;
                let no = 1;
                
                rows.forEach(row => {

                    if (row.cells.length === 1 && row.textContent.includes('Belum ada pesanan')) {
                        return;
                    }
                    
                    const searchText = row.getAttribute('data-search') || row.textContent.toLowerCase();
                    const isVisible = searchTerm === '' || searchText.includes(searchTerm);
                    
                    row.style.display = isVisible ? '' : 'none';
                    
                    if (isVisible) {
                        visibleCount++;
                        if (row.cells[0]) {
                            row.cells[0].textContent = no++;
                        }
                    }
                });
                

                const emptyRow = tbody.querySelector('.empty-row');
                if (visibleCount === 0 && !emptyRow) {
                    const tr = document.createElement('tr');
                    tr.className = 'empty-row';
                    tr.innerHTML = '<td colspan="7" class="text-center py-4"><i class="bi bi-inbox fs-1 text-muted"></i><p class="mt-2 mb-0">Tidak ada pesanan yang ditemukan</p></td>';
                    tbody.appendChild(tr);
                } else if (visibleCount > 0 && emptyRow) {
                    emptyRow.remove();
                }
            });
        }

        function openModal(id) { 
            document.getElementById(id).classList.add('show'); 
        }
        
        function closeModal(id) { 
            document.getElementById(id).classList.remove('show'); 
        }

        function lihatDetail(id) {
            fetch('pesanan_detail.php?id=' + id)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('detailContent').innerHTML = html;
                    openModal('modalDetail');
                })
                .catch(error => {
                    showToast('Gagal memuat detail pesanan', 'error');
                });
        }

        function konfirmasiPesanan(id) {
            if (!confirm('Konfirmasi pembayaran pesanan ini?')) return;
            
            fetch('pesanan_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_pesanan=' + encodeURIComponent(id) + '&status=diproses'
            })
            .then(res => res.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
        }

        function openKirimModal(id) {
            currentPesananId = id;
            document.getElementById('jasaKirim').value = '';
            document.getElementById('nomorResi').value = '';
            document.getElementById('modalKirimContainer').style.display = 'block';
        }

        function closeKirimModal() {
            document.getElementById('modalKirimContainer').style.display = 'none';
        }

        function submitKirim() {
            let jasaKirim = document.getElementById('jasaKirim').value;
            let nomorResi = document.getElementById('nomorResi').value;
            
            if (!jasaKirim) {
                showToast('Pilih jasa kirim terlebih dahulu!', 'error');
                return;
            }
            if (!nomorResi) {
                showToast('Masukkan nomor resi!', 'error');
                return;
            }
            
            fetch('pesanan_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_pesanan=' + encodeURIComponent(currentPesananId) + '&status=dikirim&jasa_kirim=' + encodeURIComponent(jasaKirim) + '&nomor_resi=' + encodeURIComponent(nomorResi)
            })
            .then(res => res.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                closeKirimModal();
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
        }

        function openTolakModal(id) {
            currentPesananId = id;
            document.getElementById('alasanTolak').value = '';
            document.getElementById('modalTolakContainer').style.display = 'block';
        }

        function closeTolakModal() {
            document.getElementById('modalTolakContainer').style.display = 'none';
        }

        function submitTolak() {
            let alasan = document.getElementById('alasanTolak').value;
            if (!alasan) {
                showToast('Masukkan alasan penolakan!', 'error');
                return;
            }
            
            fetch('pesanan_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_pesanan=' + encodeURIComponent(currentPesananId) + '&status=ditolak&alasan=' + encodeURIComponent(alasan)
            })
            .then(res => res.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                closeTolakModal();
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
        }

        function showToast(msg, type) {
            const icons = { 
                success: 'bi-check-circle-fill', 
                error: 'bi-x-circle-fill',
                info: 'bi-info-circle-fill'
            };
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


        document.addEventListener('click', function(e) {
            const filterBox = document.getElementById('filterBox');
            const filterBtn = document.querySelector('.btn-filter');
            if (filterBox && filterBtn && !filterBtn.contains(e.target) && !filterBox.contains(e.target)) {
                filterBox.classList.remove('show');
            }
        });


        document.addEventListener('DOMContentLoaded', function() {
            initFilters();
            updateFilterBadge();
            attachSearchListener();
        });
    </script>
</body>
</html>