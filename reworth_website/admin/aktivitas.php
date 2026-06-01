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

    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pengguna';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Aktivitas</title>
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
                <a href="pembayaran_komisi.php" class="nav-link-custom">
                    <i class="bi bi-cash-coin"></i>
                    <span>Pembayaran Komisi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="aktivitas.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Aktivitas</h1>
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
                            <label>Jenis Aktivitas</label>
                            <select id="filterJenisAktivitas">
                                <option value="">Semua Jenis</option>
                                <option value="lapor_sampah">Lapor Sampah</option>
                                <option value="setor_sampah">Setor Sampah</option>
                                <option value="tukar_poin">Tukar Poin</option>
                                <option value="pesanan">Belanja</option>
                                <option value="pendaftar_event">Event</option>
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
                                <option value="terbaru">Terbaru</option>
                                <option value="terlama">Terlama</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="button" onclick="resetFilters()">
                                Reset
                            </button>
                            <button type="button" onclick="applyFilters()">
                                Terapkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="tab-header">
                    <button class="tab-btn <?= $current_tab == 'pengguna' ? 'active' : '' ?>" data-tab="pengguna">Pengguna</button>
                    <button class="tab-btn <?= $current_tab == 'admin' ? 'active' : '' ?>" data-tab="admin">Admin</button>
                    <button class="tab-btn <?= $current_tab == 'bank_sampah' ? 'active' : '' ?>" data-tab="bank_sampah">Bank Sampah</button>
                    <button class="tab-btn <?= $current_tab == 'dlh' ? 'active' : '' ?>" data-tab="dlh">DLH</button>
                </div>

                <div class="tab-content" id="tabContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTab = '<?= $current_tab ?>';
        let currentPage = <?= $current_page ?>;
        
        let currentFilters = {
            jenis_aktivitas: '',
            date_from: '',
            date_to: '',
            sort_by: 'terbaru'
        };

        function loadTabContent(tab, page = 1) {
            const tabContent = document.getElementById('tabContent');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            loadingOverlay.style.display = 'flex';
            
            let file = '';
            switch(tab) {
                case 'pengguna':
                    file = 'aktivitas_pengguna.php';
                    break;
                case 'admin':
                    file = 'aktivitas_admin.php';
                    break;
                case 'bank_sampah':
                    file = 'aktivitas_bank_sampah.php';
                    break;
                case 'dlh':
                    file = 'aktivitas_dlh.php';
                    break;
                default:
                    file = 'aktivitas_pengguna.php';
            }
            
            let queryParams = new URLSearchParams({
                page: page,
                t: Date.now()
            });
            
            if (tab === 'pengguna') {
                if (currentFilters.jenis_aktivitas) queryParams.append('jenis_aktivitas', currentFilters.jenis_aktivitas);
                if (currentFilters.date_from) queryParams.append('date_from', currentFilters.date_from);
                if (currentFilters.date_to) queryParams.append('date_to', currentFilters.date_to);
                if (currentFilters.sort_by) queryParams.append('sort_by', currentFilters.sort_by);
            }
            
            fetch(`${file}?${queryParams.toString()}`)
                .then(response => response.text())
                .then(html => {
                    tabContent.innerHTML = html;
                    attachTableEventListeners();
                    loadingOverlay.style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    tabContent.innerHTML = '<div class="alert alert-danger m-3">Gagal memuat data. Silakan refresh halaman.</div>';
                    loadingOverlay.style.display = 'none';
                });
        }
        
        function attachTableEventListeners() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                const newSearchInput = searchInput.cloneNode(true);
                searchInput.parentNode.replaceChild(newSearchInput, searchInput);
                
                newSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const tbody = document.querySelector('#dynamicTable tbody');
                    if (!tbody) return;
                    
                    const rows = tbody.querySelectorAll('tr');
                    let no = 1;
                    
                    rows.forEach(row => {
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
        
        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            url.searchParams.set('page', '1');
            window.history.pushState({}, '', url);
            
            currentTab = tab;
            currentPage = 1;
            
            if (tab !== 'pengguna') {
                resetFilters(true);
            }
            
            loadTabContent(tab, 1);
        }
        
        function changePage(page) {
            currentPage = page;
            loadTabContent(currentTab, page);
            
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.history.pushState({}, '', url);
        }
        
        function toggleFilter() {
            const filterBox = document.getElementById('filterBox');
            if (filterBox) {
                filterBox.classList.toggle('show');
            }
        }
        
        function applyFilters() {
            const jenisAktivitas = document.getElementById('filterJenisAktivitas')?.value || '';
            const dateFrom = document.getElementById('filterDateFrom')?.value || '';
            const dateTo = document.getElementById('filterDateTo')?.value || '';
            const sortBy = document.getElementById('sortBy')?.value || 'terbaru';
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                showToast('Tanggal "Dari" tidak boleh lebih besar dari "Sampai"', 'error');
                return;
            }
            
            currentFilters = {
                jenis_aktivitas: jenisAktivitas,
                date_from: dateFrom,
                date_to: dateTo,
                sort_by: sortBy
            };
            
            currentPage = 1;
            updateFilterBadge();
            closeFilter();
            loadTabContent(currentTab, 1);
            
            showToast('Filter diterapkan', 'success');
        }
        
        function resetFilters(silent = false) {
            const jenisAktivitas = document.getElementById('filterJenisAktivitas');
            const dateFromInput = document.getElementById('filterDateFrom');
            const dateToInput = document.getElementById('filterDateTo');
            const sortBySelect = document.getElementById('sortBy');
            
            if (jenisAktivitas) jenisAktivitas.value = '';
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            if (sortBySelect) sortBySelect.value = 'terbaru';
            
            currentFilters = {
                jenis_aktivitas: '',
                date_from: '',
                date_to: '',
                sort_by: 'terbaru'
            };
            
            updateFilterBadge();
            closeFilter();
            
            if (!silent) {
                currentPage = 1;
                loadTabContent(currentTab, 1);
                showToast('Filter direset', 'info');
            }
        }
        
        function updateFilterBadge() {
            const badge = document.getElementById('filterBadge');
            if (!badge) return;
            
            let activeCount = 0;
            if (currentFilters.jenis_aktivitas) activeCount++;
            if (currentFilters.date_from) activeCount++;
            if (currentFilters.date_to) activeCount++;
            
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
            
            if (dateFrom) dateFrom.max = today;
            if (dateTo) dateTo.max = today;
            
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
        
        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
        
        document.addEventListener('click', function(event) {
            const filterDropdown = document.querySelector('.filter-dropdown');
            const filterBox = document.getElementById('filterBox');
            
            if (filterDropdown && filterBox && !filterDropdown.contains(event.target)) {
                filterBox.classList.remove('show');
            }
        });
        
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                switchTab(tab, this);
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            initDateInputs();
            loadTabContent(currentTab, currentPage);
        });
    </script>
</body>
</html>