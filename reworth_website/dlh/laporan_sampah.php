<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userRole  = $_SESSION['role'] ?? '';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function fetchData($url, $key) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) return json_decode($response, true) ?: [];
        return [];
    }

    $laporanValidasi = fetchData($supabaseUrl . "/rest/v1/lapor_sampah". "?select=*,pengguna!lapor_sampah_id_pengguna_fkey(nama_lengkap)". "&status=eq.menunggu". "&order=created_at.desc", $supabaseKey);
    $laporanPenanganan = fetchData($supabaseUrl . "/rest/v1/lapor_sampah?select=*,pengguna!lapor_sampah_id_pengguna_fkey(nama_lengkap),petugas_lapangan!lapor_sampah_id_petugas_fkey(nama_petugas)&status=in.(diproses,selesai,ditolak)&order=created_at.desc", $supabaseKey);

    $per_page = 10;

    $cur_val_page    = isset($_GET['val_page']) ? (int)$_GET['val_page'] : 1;
    $total_val       = count($laporanValidasi);
    $total_val_pages = max(1, ceil($total_val / $per_page));
    $val_start       = ($cur_val_page - 1) * $per_page;
    $cur_val         = array_slice($laporanValidasi, $val_start, $per_page);
    $val_to          = min($val_start + $per_page, $total_val);

    $cur_pen_page    = isset($_GET['pen_page']) ? (int)$_GET['pen_page'] : 1;
    $total_pen       = count($laporanPenanganan);
    $total_pen_pages = max(1, ceil($total_pen / $per_page));
    $pen_start       = ($cur_pen_page - 1) * $per_page;
    $cur_pen         = array_slice($laporanPenanganan, $pen_start, $per_page);
    $pen_to          = min($pen_start + $per_page, $total_pen);

    $activeTab = $_GET['tab'] ?? 'validasi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Laporan Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="laporan_sampah.php" class="nav-link-custom active"><i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span></a></div>
            <div class="nav-item"><a href="apresiasi_rw.php" class="nav-link-custom"><i class="bi bi-award-fill"></i><span>Apresiasi RW</span></a></div>
            <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
            <div class="nav-item"><a href="laporan_analitik.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a></div>
            <div class="nav-item"><a href="data_petugas.php" class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Petugas</span></a></div>
            <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
        </nav>
        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Laporan Sampah</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <i class="bi bi-person-fill" style="display:none;"></i>
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
                    <input type="text" class="search-input" placeholder="Cari laporan..." id="searchInput">
                </div>
                <div class="filter-dropdown">
                    <button class="btn-filter" onclick="toggleFilter()">
                        <i class="bi bi-sliders2"></i> Filter
                    </button>
                    <div class="filter-box">
                        <div class="filter-group">
                            <label>Jenis Sampah</label>
                            <select id="filterJenis">
                                <option value="">Semua Jenis</option>
                                <option value="organik">Organik</option>
                                <option value="anorganik">Anorganik</option>
                                <option value="b3">B3</option>
                            </select>
                        </div>
                        <div class="filter-group" id="filterStatusGroup" style="display:none;">
                            <label>Status</label>
                            <select id="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="diproses">Diproses</option>
                                <option value="selesai">Selesai</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Urutkan</label>
                            <select id="sortOrder">
                                <option value="desc">Terbaru</option>
                                <option value="asc">Terlama</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="button" onclick="resetFilter()">Reset</button>
                            <button type="button" onclick="applyFilter()">Terapkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="tab-header">
                    <button class="tab-btn <?= $activeTab === 'validasi' ? 'active' : '' ?>" onclick="switchTab('validasi', this)">Validasi</button>
                    <button class="tab-btn <?= $activeTab === 'penanganan' ? 'active' : '' ?>" onclick="switchTab('penanganan', this)">Penanganan</button>
                </div>

                <!-- TAB VALIDASI -->
                <div class="table-wrap" id="tab-validasi" style="<?= $activeTab !== 'validasi' ? 'display:none;' : '' ?>">
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table">
                            <colgroup>
                                <col style="width:55px;">
                                <col style="width:190px;">
                                <col style="width:120px;">
                                <col style="width:220px;">
                                <col style="width:120px;">
                                <col style="width:170px;">
                                <col style="width:100px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pelapor</th>
                                    <th>Jenis Sampah</th>
                                    <th>Lokasi</th>
                                    <th>Tgl Lapor</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="bodyValidasi">
                                <?php if (!empty($cur_val)): ?>
                                    <?php foreach ($cur_val as $idx => $l): ?>
                                    <tr data-date="<?= $l['created_at'] ?>" data-jenis="<?= strtolower($l['jenis_sampah'] ?? '') ?>">
                                        <td class="td-no"><?= $val_start + $idx + 1 ?></td>
                                        <td class="td-nama"><?= htmlspecialchars($l['pengguna']['nama_lengkap'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($l['jenis_sampah'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($l['lokasi'] ?? '-') ?></td>
                                        <td><?= !empty($l['created_at']) ? date('d M Y', strtotime($l['created_at'])) : '-' ?></td>
                                        <td><span class="status-badge status-akan_datang">Menunggu Konfirmasi</span></td>
                                        <td>
                                            <button class="btn-aksi btn-lihat" onclick="window.location.href='laporan_sampah_detail_validasi.php?id=<?= $l['id_laporan'] ?>'">
                                                <i class="bi bi-file-earmark-text"></i> Lihat
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center py-4" style="color:#6B8A7E;"><i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>Belum ada laporan menunggu validasi</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <div class="showing-text">Showing <b><?= $val_to ?></b> of <b><?= $total_val ?></b> entries</div>
                        <div class="pagination-custom">
                            <?php if ($cur_val_page > 1): ?>
                                <a href="?val_page=<?= $cur_val_page-1 ?>&tab=validasi" class="page-btn page-btn-text">Previous</a>
                            <?php else: ?>
                                <span class="page-btn page-btn-text disabled">Previous</span>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_val_pages; $i++): ?>
                                <a href="?val_page=<?= $i ?>&tab=validasi" class="page-btn <?= $i == $cur_val_page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($cur_val_page < $total_val_pages): ?>
                                <a href="?val_page=<?= $cur_val_page+1 ?>&tab=validasi" class="page-btn page-btn-text">Next</a>
                            <?php else: ?>
                                <span class="page-btn page-btn-text disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB PENANGANAN -->
                <div class="table-wrap" id="tab-penanganan" style="<?= $activeTab !== 'penanganan' ? 'display:none;' : '' ?>">
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table">
                            <colgroup>
                                <col style="width:55px;">
                                <col style="width:190px;">
                                <col style="width:120px;">
                                <col style="width:140px;">
                                <col style="width:120px;">
                                <col style="width:130px;">
                                <col style="width:100px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pelapor</th>
                                    <th>Jenis Sampah</th>
                                    <th>Petugas</th>
                                    <th>Tgl Lapor</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="bodyPenanganan">
                                <?php if (!empty($cur_pen)): ?>
                                    <?php foreach ($cur_pen as $idx => $l): ?>
                                    <?php
                                        $st = $l['status'] ?? '';
                                         $stClass = match($st) { 'diproses' => 'status-diproses', 'selesai' => 'status-selesai', default => 'status-akan_datang' };
                                        $stLabel = match($st) { 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak', default => ucfirst($st) };
                                    ?>
                                    <tr data-date="<?= $l['created_at'] ?>" data-status="<?= $st ?>" data-jenis="<?= strtolower($l['jenis_sampah'] ?? '') ?>">
                                        <td class="td-no"><?= $pen_start + $idx + 1 ?></td>
                                        <td class="td-nama"><?= htmlspecialchars($l['pengguna']['nama_lengkap'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($l['jenis_sampah'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($l['petugas_lapangan']['nama_petugas'] ?? '-') ?></td>
                                        <td><?= !empty($l['created_at']) ? date('d M Y', strtotime($l['created_at'])) : '-' ?></td>
                                        <td><span class="status-badge <?= $stClass ?>"><?= $stLabel ?></span></td>
                                        <td>
                                            <button class="btn-aksi btn-lihat" onclick="window.location.href='laporan_sampah_detail_penanganan.php?id=<?= $l['id_laporan'] ?>'">
                                                <i class="bi bi-file-earmark-text"></i> Lihat
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center py-4" style="color:#6B8A7E;"><i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>Belum ada data penanganan</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <div class="showing-text">Showing <b><?= $pen_to ?></b> of <b><?= $total_pen ?></b> entries</div>
                        <div class="pagination-custom">
                            <?php if ($cur_pen_page > 1): ?>
                                <a href="?pen_page=<?= $cur_pen_page-1 ?>&tab=penanganan" class="page-btn page-btn-text">Previous</a>
                            <?php else: ?>
                                <span class="page-btn page-btn-text disabled">Previous</span>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pen_pages; $i++): ?>
                                <a href="?pen_page=<?= $i ?>&tab=penanganan" class="page-btn <?= $i == $cur_pen_page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($cur_pen_page < $total_pen_pages): ?>
                                <a href="?pen_page=<?= $cur_pen_page+1 ?>&tab=penanganan" class="page-btn page-btn-text">Next</a>
                            <?php else: ?>
                                <span class="page-btn page-btn-text disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('tab-validasi').style.display   = tab === 'validasi'   ? '' : 'none';
            document.getElementById('tab-penanganan').style.display = tab === 'penanganan' ? '' : 'none';
            document.getElementById('filterStatusGroup').style.display = tab === 'penanganan' ? 'block' : 'none';
            document.getElementById('searchInput').placeholder = 'Cari laporan...';
            history.replaceState(null, '', '?tab=' + tab);
        }

        function toggleFilter() {
            document.querySelector('.filter-box').classList.toggle('show');
        }

        document.getElementById('searchInput').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            const activeTab = document.querySelector('.tab-btn.active').textContent.trim().toLowerCase();
            const bodyId = activeTab.includes('penanganan') ? '#bodyPenanganan tr' : '#bodyValidasi tr';
            document.querySelectorAll(bodyId).forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
            updateRowNumbers(activeTab.includes('penanganan') ? '#bodyPenanganan' : '#bodyValidasi');
        });

        function applyFilter() {
            const activeTab = document.querySelector('.tab-btn.active').textContent.trim().toLowerCase();
            const isPenanganan = activeTab.includes('penanganan');
            const jenis  = document.getElementById('filterJenis').value.toLowerCase();
            const status = document.getElementById('filterStatus').value;
            const sort   = document.getElementById('sortOrder').value;
            const bodyId = isPenanganan ? '#bodyPenanganan' : '#bodyValidasi';
            const tbody  = document.querySelector(bodyId);
            let rows = Array.from(tbody.querySelectorAll('tr'));

            rows.forEach(row => {
                let visible = true;
                if (jenis && row.dataset.jenis !== jenis) visible = false;
                if (isPenanganan && status && row.dataset.status !== status) visible = false;
                row.style.display = visible ? '' : 'none';
            });

            rows.sort((a, b) => {
                const dA = new Date(a.dataset.date || 0);
                const dB = new Date(b.dataset.date || 0);
                return sort === 'asc' ? dA - dB : dB - dA;
            });
            rows.forEach(r => tbody.appendChild(r));
            updateRowNumbers(bodyId);
            document.querySelector('.filter-box').classList.remove('show');
        }

        function resetFilter() {
            document.getElementById('filterJenis').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('sortOrder').value = 'desc';
            const activeTab = document.querySelector('.tab-btn.active').textContent.trim().toLowerCase();
            const bodyId = activeTab.includes('penanganan') ? '#bodyPenanganan tr' : '#bodyValidasi tr';
            document.querySelectorAll(bodyId).forEach(row => row.style.display = '');
            applyFilter();
        }

        function updateRowNumbers(selector) {
            let no = 1;
            document.querySelectorAll(selector + ' tr').forEach(row => {
                if (row.style.display !== 'none') {
                    const td = row.querySelector('td:first-child');
                    if (td) td.textContent = no++;
                }
            });
        }

        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const params  = new URLSearchParams(window.location.search);
            const tab     = params.get('tab') || 'validasi';
            const btn     = tab === 'penanganan' ? document.querySelectorAll('.tab-btn')[1] : document.querySelectorAll('.tab-btn')[0];
            switchTab(tab, btn);
            const msg = params.get('msg');
            if (msg) showToast(decodeURIComponent(msg), params.get('msg_type') || 'success');
        });
    </script>
</body>
</html>