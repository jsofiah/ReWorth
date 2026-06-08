<?php
    require_once 'role_check.php';

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

    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'langganan';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Kelola Data Master</title>
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
                <a href="kelola_data_master.php" class="nav-link-custom active">
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
                <h1 class="topbar-title">Kelola Data Master</h1>
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
                <button class="btn-tambah" id="btnTambah" onclick="openTambah()">
                    <i class="bi bi-plus-lg"></i> Tambah Data
                </button>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="tab-header">
                    <button class="tab-btn <?= $current_tab == 'langganan' ? 'active' : '' ?>" data-tab="langganan">Langganan</button>
                    <button class="tab-btn <?= $current_tab == 'pengeluaran' ? 'active' : '' ?>" data-tab="pengeluaran">Pengeluaran</button>
                    <button class="tab-btn <?= $current_tab == 'reward' ? 'active' : '' ?>" data-tab="reward">Reward</button>
                    <button class="tab-btn <?= $current_tab == 'role' ? 'active' : '' ?>" data-tab="role">Role</button>
                    <button class="tab-btn <?= $current_tab == 'wilayah' ? 'active' : '' ?>" data-tab="wilayah">Wilayah</button>
                </div>

                <div class="tab-content" id="tabContent">
                    <!-- Content akan dimuat via AJAX -->
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
                <h3>Hapus Data?</h3>
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

    <div class="toast-container" id="toastContainer"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTab = '<?= $current_tab ?>';
        let currentPage = <?= $current_page ?>;
        let deletingTab = null;
        let deletingId = null;

        function loadTabContent(tab, page = 1) {
            const tabContent = document.getElementById('tabContent');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            loadingOverlay.style.display = 'flex';
            
            let file = '';
            switch(tab) {
                case 'langganan':
                    file = 'data_langganan.php';
                    break;
                case 'pengeluaran':
                    file = 'data_pengeluaran.php';
                    break;
                case 'reward':
                    file = 'data_reward.php';
                    break;
                case 'role':
                    file = 'data_role.php';
                    break;
                case 'wilayah':
                    file = 'data_wilayah.php';
                    break;
            }
            
            fetch(`${file}?page=${page}&t=${Date.now()}`)
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
                    let visibleCount = 0;
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
            
            loadTabContent(tab, 1);
        }
        
        function changePage(page) {
            currentPage = page;
            loadTabContent(currentTab, page);
            
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.history.pushState({}, '', url);
        }
        
        function lihatData(tab, id) {
            let file = '';
            switch(tab) {
                case 'langganan':
                    file = `data_langganan_lihat.php?id=${id}`;
                    break;
                case 'pengeluaran':
                    file = `data_pengeluaran_lihat.php?id=${id}`;
                    break;
                case 'reward':
                    file = `data_reward_lihat.php?id=${id}`;
                    break;
                case 'role':
                    file = `data_role_lihat.php?id=${id}`;
                    break;
                case 'wilayah':
                    file = `data_wilayah_lihat.php?id=${id}`;
                    break;
                default:
                    return;
            }
            window.location.href = file;
        }

        function editData(tab, id) {
            let file = '';
            switch(tab) {
                case 'langganan':
                    file = `data_langganan_edit.php?id=${id}`;
                    break;
                case 'pengeluaran':
                    file = `data_pengeluaran_edit.php?id=${id}`;
                    break;
                case 'reward':
                    file = `data_reward_edit.php?id=${id}`;
                    break;
                case 'role':
                    file = `data_role_edit.php?id=${id}`;
                    break;
                case 'wilayah':
                    file = `data_wilayah_edit.php?id=${id}`;
                    break;
                default:
                    return;
            }
            window.location.href = file;
        }

        function hapusData(tab, id) {
            deletingTab = tab;
            deletingId = id;
            openModal('modalHapus');
        }

        function confirmHapus() {
            if (!deletingId || !deletingTab) return;
            
            fetch('data_hapus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tab=${deletingTab}&id=${deletingId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Data berhasil dihapus.', 'success');
                    setTimeout(() => {
                        loadTabContent(currentTab, currentPage);
                    }, 800);
                } else {
                    showToast(data.message || 'Gagal menghapus data.', 'error');
                }
                closeModal('modalHapus');
            })
            .catch(() => {
                showToast('Terjadi kesalahan server.', 'error');
                closeModal('modalHapus');
            });
        }

        function openTambah() {
            const activeTab = document.querySelector('.tab-btn.active').getAttribute('data-tab');
            
            switch(activeTab) {
                case 'langganan':
                    window.location.href = 'data_langganan_tambah.php';
                    break;
                case 'pengeluaran':
                    window.location.href = 'data_pengeluaran_tambah.php';
                    break;
                case 'reward':
                    window.location.href = 'data_reward_tambah.php';
                    break;
                case 'role':
                    window.location.href = 'data_role_tambah.php';
                    break;
                case 'wilayah':
                    window.location.href = 'data_wilayah_tambah.php';
                    break;
                default:
                    window.location.href = 'data_langganan_tambah.php';
            }
        }
        
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
        
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                switchTab(tab, this);
            });
        });
        
        loadTabContent(currentTab, currentPage);
        
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) m.classList.remove('show');
            });
        });
    </script>
</body>
</html>