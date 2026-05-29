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

    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'dibayar') {
            return '<span class="status-badge status-berlangsung">Menunggu Konfirmasi</span>';
        } elseif ($status == 'pending') {
            return '<span class="status-badge status-akan_datang">Belum Membayar</span>';
        } else {
            return '<span class="status-badge status-akan_datang">None</span>';
        }
    }

    function formatRupiah($angka) {
        if (empty($angka)) return 'Rp 0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    function getKomisi($supabaseUrl, $supabaseKey, $filters = []) {
        $sortBy = $filters['sort_by'] ?? 'terbaru';
        $orderBy = "created_at.desc";
        
        switch($sortBy) {
            case 'terbaru':
                $orderBy = "created_at.desc";
                break;
            case 'terlama':
                $orderBy = "created_at.asc";
                break;
            case 'nama_asc':
                $orderBy = "penjual(nama_penjual).asc";
                break;
            case 'nama_desc':
                $orderBy = "penjual(nama_penjual).desc";
                break;
            default:
                $orderBy = "created_at.desc";
        }
        
        $url = $supabaseUrl . "/rest/v1/komisi?select=*,penjual(nama_penjual)&order=" . $orderBy;
        
        if (!empty($filters['date_from'])) {
            $url .= "&tanggal_pembayaran=gte." . $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $url .= "&tanggal_pembayaran=lte." . $filters['date_to'];
        }
        if (!empty($filters['status'])) {
            $url .= "&status_pembayaran=eq." . $filters['status'];
        }
        
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

    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'terbaru';
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    
    $filters = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort_by' => $sortBy,
        'status' => $statusFilter
    ];
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;

    $allKomisi = getKomisi($supabaseUrl, $supabaseKey, $filters);
    $total = count($allKomisi);
    $start = ($page - 1) * $per_page;
    $paginatedData = array_slice($allKomisi, $start, $per_page);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? $start + 1 : 0;
    $end_number = min($start + $per_page, $total);
    
    $current_page = $page;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Pembayaran Komisi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Memuat data...</p>
        </div>
    </div>

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
                <a href="kelola_akun.php" class="nav-link-custom">
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
                <a href="pembayaran_komisi.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Pembayaran Komisi</h1>
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

        <div class="action-bar-wrap">
            <div class="action-bar">
                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" placeholder="Cari..." id="searchInput">
                </div>
                <div class="filter-dropdown">
                    <button class="btn-filter" onclick="toggleFilter()">
                        <i class="bi bi-sliders2"></i> Filter 
                        <span id="filterBadge" class="filter-badge" style="display: none;"></span>
                    </button>
                    <div class="filter-box" id="filterBox">
                        <div class="filter-group">
                            <label>Status Pembayaran</label>
                            <select id="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="pending">Belum Membayar</option>
                                <option value="dibayar">Menunggu Konfirmasi</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Dari Tanggal</label>
                            <input type="date" id="filterDateFrom" max="">
                        </div>

                        <div class="filter-group">
                            <label>Sampai Tanggal</label>
                            <input type="date" id="filterDateTo" min="">
                        </div>

                        <div class="filter-group">
                            <label>Urutkan Berdasarkan</label>
                            <select id="sortBy">
                                <option value="terbaru">Terbaru (Tanggal)</option>
                                <option value="terlama">Terlama (Tanggal)</option>
                                <option value="nama_asc">Nama A-Z</option>
                                <option value="nama_desc">Nama Z-A</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="button" onclick="resetFilters()">
                                Reset
                            </button>
                            <button type="button" onclick="applyFilter()">
                                Terapkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="table-wrap">
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table" id="dynamicTable">
                            <colgroup>
                                <col style="width: 60px;">
                                <col style="width: 200px;">
                                <col style="width: 150px;">
                                <col style="width: 120px;">
                                <col style="width: 130px;">
                                <col style="width: 100px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Penjual/Toko</th>
                                    <th>Total Komisi</th>
                                    <th>Periode Bulan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($paginatedData)): ?>
                                    <?php foreach ($paginatedData as $idx => $k): ?>
                                    <tr data-id="<?= $k['id_komisi'] ?>">
                                        <td><?= $start_number + $idx ?> </td>
                                        <td class="table-cell-content">
                                            <?php 
                                                $namaPenjual = '-';
                                                if (isset($k['penjual']) && is_array($k['penjual'])) {
                                                    $namaPenjual = htmlspecialchars($k['penjual']['nama_toko'] ?? $k['penjual']['nama_penjual'] ?? '-');
                                                } elseif (isset($k['penjual'])) {
                                                    $namaPenjual = htmlspecialchars($k['penjual'] ?? '-');
                                                }
                                                echo $namaPenjual;
                                            ?>
                                        </td>
                                        <td class="table-cell-content"><?= formatRupiah($k['total_komisi'] ?? 0) ?> </td>
                                        <td class="table-cell-content"><?= htmlspecialchars($k['periode_bulan'] ?? '-') ?> </td>
                                        <td class="table-cell-content"><?= getStatusBadge($k['status_pembayaran'] ?? '') ?> </td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <a href="pembayaran_komisi_detail.php?id=<?= $k['id_komisi'] ?>" class="btn-aksi btn-lihat" style="text-decoration: none">
                                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4" style="color: #6B8A7E;">
                                            <i class="bi bi-cash-coin" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            Belum ada data komisi
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="table-footer">
                        <div class="showing-text">
                            Showing <b><?= $start_number ?></b> to <b><?= $end_number ?></b> of <b><?= $total ?></b> entries
                        </div>
                        <div class="pagination-custom">
                            <?php if ($page > 1): ?>
                                <a href="javascript:void(0)" onclick="changePage(<?= $page - 1 ?>)" class="page-btn page-btn-text">Prev</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="javascript:void(0)" onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="javascript:void(0)" onclick="changePage(<?= $page + 1 ?>)" class="page-btn page-btn-text">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = <?= $current_page ?>;
        
        let currentFilters = {
            date_from: '<?= $dateFrom ?>',
            date_to: '<?= $dateTo ?>',
            sort_by: '<?= $sortBy ?>',
            status: '<?= $statusFilter ?>'
        };

        function loadContent(page = 1) {
            let queryParams = new URLSearchParams({
                page: page,
                date_from: currentFilters.date_from,
                date_to: currentFilters.date_to,
                sort_by: currentFilters.sort_by,
                status: currentFilters.status
            });
            
            window.location.href = `pembayaran_komisi.php?${queryParams.toString()}`;
        }
        
        function changePage(page) {
            loadContent(page);
        }
        
        function toggleFilter() {
            const filterBox = document.getElementById('filterBox');
            if (filterBox) {
                filterBox.classList.toggle('show');
            }
        }
        
        function applyFilter() {
            const status = document.getElementById('filterStatus')?.value || '';
            const dateFrom = document.getElementById('filterDateFrom')?.value || '';
            const dateTo = document.getElementById('filterDateTo')?.value || '';
            const sortBy = document.getElementById('sortBy')?.value || 'terbaru';
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                showToast('Tanggal "Dari" tidak boleh lebih besar dari "Sampai"', 'error');
                return;
            }
            
            let queryParams = new URLSearchParams({
                page: 1,
                date_from: dateFrom,
                date_to: dateTo,
                sort_by: sortBy,
                status: status
            });
            
            window.location.href = `pembayaran_komisi.php?${queryParams.toString()}`;
        }
        
        function resetFilters() {
            window.location.href = 'pembayaran_komisi.php';
        }
        
        function updateFilterBadge() {
            const badge = document.getElementById('filterBadge');
            if (!badge) return;
            
            let activeCount = 0;
            if (currentFilters.date_from) activeCount++;
            if (currentFilters.date_to) activeCount++;
            if (currentFilters.status) activeCount++;
            
            if (activeCount > 0) {
                badge.textContent = activeCount;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        function closeFilter() {
            const filterBox = document.getElementById('filterBox');
            if (filterBox) {
                filterBox.classList.remove('show');
            }
        }
        
        function initDateInputs() {
            const today = new Date().toISOString().split('T')[0];
            const dateFrom = document.getElementById('filterDateFrom');
            const dateTo = document.getElementById('filterDateTo');
            const sortBySelect = document.getElementById('sortBy');
            const filterStatus = document.getElementById('filterStatus');
            
            if (dateFrom) {
                dateFrom.max = today;
                dateFrom.value = currentFilters.date_from;
            }
            if (dateTo) {
                dateTo.max = today;
                dateTo.value = currentFilters.date_to;
            }
            if (sortBySelect) {
                sortBySelect.value = currentFilters.sort_by;
            }
            if (filterStatus) {
                filterStatus.value = currentFilters.status;
            }
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    dateTo.min = this.value;
                    if (dateTo.value && dateTo.value < this.value) {
                        dateTo.value = this.value;
                    }
                });
                
                dateTo.addEventListener('change', function() {
                    if (dateFrom.value && this.value < dateFrom.value) {
                        this.value = dateFrom.value;
                    }
                });
            }
        }

        function attachTableEventListeners() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const tbody = document.querySelector('#dynamicTable tbody');
                    if (!tbody) return;
                    
                    const rows = tbody.querySelectorAll('tr');
                    let no = 1;
                    
                    rows.forEach(row => {
                        if (row.cells.length === 1 && row.textContent.includes('Belum ada data')) {
                            return;
                        }
                        
                        const text = row.textContent.toLowerCase();
                        const isVisible = text.includes(searchTerm);
                        row.style.display = isVisible ? '' : 'none';
                        if (isVisible && row.cells[0]) {
                            row.cells[0].textContent = no++;
                        }
                    });
                });
            }
        }
        
        function showToast(msg, type = 'success') {
            const icons = { 
                success: 'bi-check-circle-fill', 
                error: 'bi-x-circle-fill', 
                info: 'bi-info-circle-fill' 
            };
            
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${escapeHtml(msg)}</span>`;
            
            toastContainer.appendChild(div);
            
            setTimeout(() => {
                div.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => div.remove(), 300);
            }, 3500);
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initDateInputs();
            updateFilterBadge();
            attachTableEventListeners();
        });
    </script>
</body>
</html>