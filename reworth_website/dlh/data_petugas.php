<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userRole  = $_SESSION['role']        ?? '';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function getPetugasList($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/petugas_lapangan?select=*&order=created_at.desc";
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) return json_decode($response, true) ?: [];
        return [];
    }

    function getTotalTugasPerPetugas($supabaseUrl, $supabaseKey) {
        // lapor_sampah.id_petugas → petugas_lapangan
        $url = $supabaseUrl . "/rest/v1/lapor_sampah?select=id_petugas";
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $map = [];
        if ($httpCode === 200) {
            $data = json_decode($response, true) ?: [];
            foreach ($data as $row) {
                $id = $row['id_petugas'] ?? null;
                if ($id) $map[$id] = ($map[$id] ?? 0) + 1;
            }
        }
        return $map;
    }

    $petugasList     = getPetugasList($supabaseUrl, $supabaseKey);
    $tugasPerPetugas = getTotalTugasPerPetugas($supabaseUrl, $supabaseKey);

    foreach ($petugasList as &$p) {
        $p['total_tugas'] = $tugasPerPetugas[$p['id_petugas']] ?? 0;
    }
    unset($p);

    $per_page     = 10;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $total_data   = count($petugasList);
    $total_pages  = max(1, ceil($total_data / $per_page));
    $start        = ($current_page - 1) * $per_page;
    $current_data = array_slice($petugasList, $start, $per_page);

    $showing_from = $total_data > 0 ? $start + 1 : 0;
    $showing_to   = min($start + $per_page, $total_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Data Petugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_sampah.php" class="nav-link-custom">
                    <i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="apresiasi_rw.php" class="nav-link-custom">
                    <i class="bi bi-award-fill"></i><span>Apresiasi RW</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom">
                    <i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_analitik.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_petugas.php" class="nav-link-custom active">
                    <i class="bi bi-people-fill"></i><span>Data Petugas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom">
                    <i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span>
                </a>
            </div>
        </nav>
        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Data Petugas</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): ?>
                            <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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
                    <input type="text" class="search-input" placeholder="Cari nama petugas..." id="searchInput">
                </div>

                <div class="filter-dropdown">
                    <button class="btn-filter" onclick="toggleFilter()">
                        <i class="bi bi-sliders2"></i> Filter
                    </button>
                    <div class="filter-box">
                        <div class="filter-group">
                            <label>Status</label>
                            <select id="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Urutkan</label>
                            <select id="sortNama">
                                <option value="az">Nama A – Z</option>
                                <option value="za">Nama Z – A</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="button" onclick="resetFilter()">Reset</button>
                            <button type="button" onclick="applyFilter()">Terapkan</button>
                        </div>
                    </div>
                </div>

                <button class="btn-tambah" onclick="window.location.href='data_petugas_tambah.php'">
                    <i class="bi bi-plus-lg"></i> Tambah Petugas
                </button>
            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="table-wrap">
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table">
                            <colgroup>
                                <col style="width:60px;">
                                <col style="width:220px;">
                                <col style="width:170px;">
                                <col style="width:130px;">
                                <col style="width:130px;">
                                <col style="width:280px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Petugas</th>
                                    <th>No. Telepon</th>
                                    <th>Total Tugas</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (!empty($current_data)): ?>
                                    <?php foreach ($current_data as $idx => $p):
                                        // status_aktif adalah boolean
                                        $isAktif    = $p['status_aktif'] === true || $p['status_aktif'] === 'true';
                                        $statusText = $isAktif ? 'Aktif' : 'Nonaktif';
                                        $statusKey  = $isAktif ? 'aktif' : 'nonaktif';
                                        $badgeClass = $isAktif ? 'status-berlangsung' : 'status-selesai';
                                    ?>
                                    <tr data-nama="<?= htmlspecialchars(strtolower($p['nama_petugas'] ?? '')) ?>"
                                        data-status="<?= $statusKey ?>">
                                        <td class="td-no"><?= $start + $idx + 1 ?></td>
                                        <td><?= htmlspecialchars($p['nama_petugas'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($p['no_telepon'] ?? '-') ?></td>
                                        <td><?= $p['total_tugas'] ?> Tugas</td>
                                        <td>
                                            <span class="status-badge <?= $badgeClass ?>">
                                                <?= $statusText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button class="btn-aksi btn-lihat"
                                                    onclick="window.location.href='data_petugas_lihat.php?id=<?= $p['id_petugas'] ?>'">
                                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                                </button>
                                                <button class="btn-aksi btn-edit"
                                                    onclick="window.location.href='data_petugas_edit.php?id=<?= $p['id_petugas'] ?>'">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <button class="btn-aksi btn-hapus"
                                                    onclick="hapusPetugas('<?= $p['id_petugas'] ?>')">
                                                    <i class="bi bi-trash3"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4" style="color:#6B8A7E;">
                                            <i class="bi bi-people" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                            Belum ada data petugas
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div class="showing-text">
                            Showing <b><?= $showing_from ?></b> to <b><?= $showing_to ?></b> of <b><?= $total_data ?></b> entries
                        </div>
                        <div class="pagination-custom">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?= $current_page - 1 ?>" class="page-btn page-btn-text">Prev</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>"
                                   class="page-btn <?= $i == $current_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?= $current_page + 1 ?>" class="page-btn page-btn-text">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal-overlay" id="modalHapus">
        <div class="modal-box" style="max-width:400px;">
            <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
            <div class="confirm-text">
                <h3>Hapus Petugas?</h3>
                <p>Tindakan ini tidak dapat dibatalkan. Data petugas akan dihapus secara permanen.</p>
            </div>
            <div class="modal-actions" style="justify-content:center;margin-top:20px;">
                <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
                <button class="btn-aksi btn-hapus"
                    style="padding:10px 22px;font-size:14px;border-radius:12px;"
                    onclick="confirmHapus()">
                    <i class="bi bi-trash3"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let deletingId = null;

        document.getElementById('searchInput').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#tableBody tr').forEach(row => {
                const nama = row.dataset.nama || '';
                row.style.display = nama.includes(q) ? '' : 'none';
            });
            updateRowNumbers();
        });

        function toggleFilter() {
            document.querySelector('.filter-box').classList.toggle('show');
        }

        function applyFilter() {
            const status  = document.getElementById('filterStatus').value;
            const sortVal = document.getElementById('sortNama').value;
            const tbody   = document.getElementById('tableBody');
            let rows      = Array.from(tbody.querySelectorAll('tr'));

            rows.forEach(row => {
                const rowStatus = row.dataset.status || '';
                row.style.display = (status && rowStatus !== status) ? 'none' : '';
            });

            rows.sort((a, b) => {
                const namaA = (a.dataset.nama || '');
                const namaB = (b.dataset.nama || '');
                return sortVal === 'az' ? namaA.localeCompare(namaB) : namaB.localeCompare(namaA);
            });
            rows.forEach(row => tbody.appendChild(row));
            updateRowNumbers();
            document.querySelector('.filter-box').classList.remove('show');
        }

        function resetFilter() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('sortNama').value = 'az';
            document.querySelectorAll('#tableBody tr').forEach(row => row.style.display = '');
            applyFilter();
        }

        function openModal(id)  { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
        });

        function hapusPetugas(id) { deletingId = id; openModal('modalHapus'); }

        function confirmHapus() {
            if (!deletingId) return;
            fetch('data_petugas_hapus.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(deletingId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Petugas berhasil dihapus.', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Gagal menghapus petugas.', 'error');
                }
                closeModal('modalHapus');
            })
            .catch(() => showToast('Terjadi kesalahan server.', 'error'));
        }

        function updateRowNumbers() {
            let no = 1;
            document.querySelectorAll('#tableBody tr').forEach(row => {
                if (row.style.display !== 'none') {
                    const td = row.querySelector('.td-no');
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
    </script>
</body>
</html>