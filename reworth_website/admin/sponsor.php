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

    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'sponsor';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Sponsor</title>
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
                <a href="aktivitas.php" class="nav-link-custom">
                    <i class="bi bi-activity"></i>
                    <span>Aktivitas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="sponsor.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Kelola Sponsor</h1>
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
                <button class="btn-tambah" id="btnTambah" onclick="openTambah()">
                    <i class="bi bi-plus-lg"></i> Tambah
                </button>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="tab-header">
                    <button class="tab-btn <?= $current_tab == 'sponsor' ? 'active' : '' ?>" data-tab="sponsor">Sponsor</button>
                    <button class="tab-btn <?= $current_tab == 'sponsor_kontribusi' ? 'active' : '' ?>" data-tab="sponsor_kontribusi">Kontribusi</button>
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

    <div class="modal-overlay" id="modalHapus">
        <div class="modal-box" style="max-width:400px;">
            <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
            <div class="confirm-text">
                <h3>Hapus Sponsor?</h3>
                <p>Tindakan ini tidak dapat dibatalkan. Data akan dihapus secara permanen.</p>
            </div>
            <div class="modal-actions" style="justify-content:center; margin-top:20px;">
                <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
                <button class="btn-aksi btn-hapus" style="padding:10px 22px; font-size:14px; border-radius:12px;" onclick="confirmHapus()">
                    <i class="bi bi-trash3"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalLihat">
        <div class="modal-box2" style="max-width:500px;">
            <div class="modal-header-custom">
                <h3><i class="bi bi-eye-fill"></i> Detail Sponsor</h3>
                <button class="btn-close-modal" onclick="closeModal('modalLihat')">&times;</button>
            </div>
            <div class="modal-body-custom" id="detailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button class="btn-cancel" onclick="closeModal('modalLihat')">Tutup</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTab = '<?= $current_tab ?>';
        let currentPage = <?= $current_page ?>;
        let deletingTab = null;
        let deletingId = null;
        let viewDataType = null;
        let viewDataId = null;
        
        let currentFilters = {
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
                case 'sponsor':
                    file = 'sponsor_data.php';
                    break;
                case 'sponsor_kontribusi':
                    file = 'sponsor_kontribusi.php';
                    break;
                default:
                    file = 'sponsor_data.php';
            }
            

            let queryParams = new URLSearchParams({
                page: page,
                t: Date.now(),
                date_from: currentFilters.date_from,
                date_to: currentFilters.date_to,
                sort_by: currentFilters.sort_by
            });
            
            fetch(`${file}?${queryParams.toString()}`)
                .then(response => response.text())
                .then(html => {
                    tabContent.innerHTML = html;
                    attachTableEventListeners();
                    attachActionButtons();
                    updateFilterBadge();
                    updateSortBySelect();
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
                    performSearch(this.value.toLowerCase());
                });
            }
        }
        
        function performSearch(searchTerm) {
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
        }
        
        function attachActionButtons() {
            document.querySelectorAll('.btn-lihat').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tab = this.getAttribute('data-tab');
                    const id = this.getAttribute('data-id');
                    lihatData(tab, id);
                });
            });
            
            document.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tab = this.getAttribute('data-tab');
                    const id = this.getAttribute('data-id');
                    editData(tab, id);
                });
            });
            
            document.querySelectorAll('.btn-hapus').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tab = this.getAttribute('data-tab');
                    const id = this.getAttribute('data-id');
                    hapusData(tab, id);
                });
            });
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
            
            resetFilters(true);
            
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
        
        function applyFilter() {
            const dateFrom = document.getElementById('filterDateFrom')?.value || '';
            const dateTo = document.getElementById('filterDateTo')?.value || '';
            const sortBy = document.getElementById('sortBy')?.value || 'terbaru';
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                showToast('Tanggal "Dari" tidak boleh lebih besar dari "Sampai"', 'error');
                return;
            }
            
            currentFilters = {
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
            const dateFromInput = document.getElementById('filterDateFrom');
            const dateToInput = document.getElementById('filterDateTo');
            const sortBySelect = document.getElementById('sortBy');
            
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            if (sortBySelect) sortBySelect.value = 'terbaru';
            
            currentFilters = {
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
            if (currentFilters.date_from) activeCount++;
            if (currentFilters.date_to) activeCount++;
            
            if (activeCount > 0) {
                badge.textContent = activeCount;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        function updateSortBySelect() {
            const sortBySelect = document.getElementById('sortBy');
            if (sortBySelect && currentFilters.sort_by) {
                sortBySelect.value = currentFilters.sort_by;
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
        
        function lihatData(tab, id) {
            viewDataType = tab;
            viewDataId = id;
            
            openModal('modalLihat');
            
            let apiUrl = '';
            switch(tab) {
                case 'sponsor':
                    apiUrl = `sponsor_detail.php?id=${id}`;
                    break;
                case 'sponsor_kontribusi':
                    apiUrl = `sponsor_kontribusi_detail.php?id=${id}`;
                    break;
                default:
                    apiUrl = `sponsor_detail.php?id=${id}`;
            }
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDetailData(tab, data.data);
                    } else {
                        showErrorDetail(data.message || 'Gagal memuat data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorDetail('Terjadi kesalahan saat memuat data');
                });
        }
        
        function showErrorDetail(message) {
            const detailContent = document.getElementById('detailContent');
            if (detailContent) {
                detailContent.innerHTML = `
                    <div class="alert alert-danger m-3">
                        <i class="bi bi-exclamation-triangle-fill"></i> ${escapeHtml(message)}
                    </div>
                `;
            }
        }
        
        function displayDetailData(tab, data) {
            let html = '<div class="detail-info">';
            
            if (tab === 'sponsor') {
                html += `
                    <div class="detail-row">
                        <label>Nama Sponsor:</label>
                        <span>${escapeHtml(data.nama_sponsor || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <label>Kontak:</label>
                        <span>${escapeHtml(data.kontak || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <label>Jenis Sponsor:</label>
                        <span>${escapeHtml(data.jenis_sponsor || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <label>Tanggal Bergabung:</label>
                        <span>${formatDate(data.created_at || '-')}</span>
                    </div>
                `;
                
                if (data.logo) {
                    const logoUrl = `<?= $supabaseUrl ?>/storage/v1/object/public/media/${data.logo}`;
                    html += `
                        <div class="detail-row">
                            <label>Logo:</label>
                            <div class="mt-2">
                                <img src="${escapeHtml(logoUrl)}" 
                                    style="max-width: 150px; max-height: 150px; border-radius: 8px; object-fit: cover;"
                                    onerror="this.style.display='none'">
                            </div>
                        </div>
                    `;
                }
            } else if (tab === 'sponsor_kontribusi') {
                html += `
                    <div class="detail-row">
                        <label>Nama Sponsor:</label>
                        <span>${escapeHtml(data.nama_sponsor || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <label>Jenis Kontribusi:</label>
                        <span>${escapeHtml(data.jenis_kontribusi || '-')}</span>
                    </div>
                `;
                
                if (data.jenis_kontribusi === 'Uang') {
                    html += `
                        <div class="detail-row">
                            <label>Nominal Uang:</label>
                            <span>${formatRupiah(data.nominal_uang || 0)}</span>
                        </div>
                    `;
                } 
                else if (data.jenis_kontribusi === 'Barang') {
                    html += `
                        <div class="detail-row">
                            <label>Nama Barang:</label>
                            <span>${escapeHtml(data.nama_barang || '-')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Jumlah Barang:</label>
                            <span>${formatNumber(data.jumlah_barang || 0)}</span>
                        </div>
                    `;
                }
                
                html += `
                    <div class="detail-row">
                        <label>Keterangan:</label>
                        <span>${escapeHtml(data.keterangan || '-')}</span>
                    </div>
                    <div class="detail-row">
                        <label>Tanggal:</label>
                        <span>${formatTanggalIndo(data.tanggal || '-')}</span>
                    </div>
                `;
            }

            
            html += '</div>';
            const detailContent = document.getElementById('detailContent');
            if (detailContent) {
                detailContent.innerHTML = html;
            }
        }

        function formatRupiah(angka) {
            if (!angka || angka === 0) return 'Rp 0';
            return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function formatTanggalIndo(dateString) {
            if (!dateString || dateString === '-') return '-';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                
                const bulan = [
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                ];
                
                const hari = date.getDate();
                const bulanIndex = date.getMonth();
                const tahun = date.getFullYear();
                
                return hari + ' ' + bulan[bulanIndex] + ' ' + tahun;
            } catch(e) {
                return dateString;
            }
        }

        
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'disetujui':
                    return 'bg-success';
                case 'pending':
                    return 'bg-warning';
                case 'ditolak':
                    return 'bg-danger';
                default:
                    return 'bg-secondary';
            }
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
        
        function formatNumber(num) {
            if (!num) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        function formatDate(dateString) {
            if (!dateString || dateString === '-') return '-';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                return date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                });
            } catch(e) {
                return dateString;
            }
        }
        
        function editData(tab, id) {
            switch(tab) {
                case 'sponsor':
                    window.location.href = `sponsor_edit.php?id=${id}`;
                    break;
                case 'sponsor_kontribusi':
                    window.location.href = `sponsor_kontribusi_edit.php?id=${id}`;
                    break;
                default:
                    window.location.href = `sponsor_edit.php?id=${id}`;
            }
        }
        
        function hapusData(tab, id) {
            deletingTab = tab;
            deletingId = id;
            openModal('modalHapus');
        }
        
        function confirmHapus() {
            if (!deletingId || !deletingTab) return;
            
            showLoading();
            
            fetch('sponsor_hapus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tab=${deletingTab}&id=${deletingId}`
            })
            .then(res => res.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('Data berhasil dihapus.', 'success');
                    setTimeout(() => {
                        loadTabContent(currentTab, currentPage);
                    }, 800);
                } else {
                    showToast(data.message || 'Gagal menghapus data.', 'error');
                }
                closeModal('modalHapus');
            })
            .catch(() => {
                hideLoading();
                showToast('Terjadi kesalahan server.', 'error');
                closeModal('modalHapus');
            });
        }
        
        function openTambah() {
            const activeTab = document.querySelector('.tab-btn.active')?.getAttribute('data-tab') || 'sponsor';
            
            switch(activeTab) {
                case 'sponsor':
                    window.location.href = 'sponsor_tambah.php';
                    break;
                case 'sponsor_kontribusi':
                    window.location.href = 'sponsor_kontribusi_tambah.php';
                    break;
                default:
                    window.location.href = 'sponsor_tambah.php';
            }
        }
        
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
            
            if (id === 'modalLihat') {
                const detailContent = document.getElementById('detailContent');
                if (detailContent) {
                    detailContent.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-success" role="status"></div>
                            <p class="mt-2">Memuat data...</p>
                        </div>
                    `;
                }
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
        
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.style.display = 'flex';
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.style.display = 'none';
        }
        
        document.addEventListener('click', function(event) {
            const filterDropdown = document.querySelector('.filter-dropdown');
            const filterBox = document.getElementById('filterBox');
            
            if (filterDropdown && filterBox && !filterDropdown.contains(event.target)) {
                filterBox.classList.remove('show');
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    switchTab(tab, this);
                });
            });
            
            document.querySelectorAll('.modal-overlay').forEach(m => {
                m.addEventListener('click', e => {
                    if (e.target === m) {
                        m.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });
            
            initDateInputs();
            
            loadTabContent(currentTab, currentPage);
        });
    </script>
</body>
</html>