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

    function getEventsWithFilter($supabaseUrl, $supabaseKey, $role = '', $userId = '') {
        $baseUrl = $supabaseUrl . "/rest/v1/event?select=*,admin!inner(nama_admin,email,id_role,role!inner(nama_role))&order=tanggal.desc";
        
        if ($role === 'bank sampah') {
            $baseUrl .= "&admin.role.nama_role=eq.bank%20sampah";
        }
        elseif ($role === 'dlh') {
            $baseUrl .= "&admin.role.nama_role=eq.dlh";
        }

        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
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

    function getPendaftarEvent($supabaseUrl, $supabaseKey, $role = '') {
        $url =
            $supabaseUrl .
            "/rest/v1/pendaftar_event?" .
            "select=*,event!inner(nama_event,admin!inner(role!inner(nama_role)))" .
            "&order=created_at.desc";

        if ($role === 'bank sampah') {

            $url .=
                "&event.admin.role.nama_role=eq.bank%20sampah";

        } elseif ($role === 'dlh') {

            $url .=
                "&event.admin.role.nama_role=eq.dlh";
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

    $events = getEventsWithFilter($supabaseUrl, $supabaseKey, $userRole, $userId);
    $pendaftarEvents = getPendaftarEvent($supabaseUrl, $supabaseKey,$userRole);

    if (!empty($events)) {
        foreach ($events as &$event) {
            $statusMap = [
                'akan_datang' => 'Akan Datang',
                'berlangsung' => 'Berlangsung', 
                'selesai' => 'Selesai'
            ];
            
            $event['status_display'] = $statusMap[$event['status']] ?? ucfirst($event['status'] ?? 'Akan Datang');
            $event['status_class'] = strtolower(str_replace(' ', '_', $event['status_display']));
            $event['tanggal_format'] = !empty($event['tanggal']) ? date('d M Y', strtotime($event['tanggal'])) : '-';
        }
        unset($event);
    }

    $per_page = 10;

    $current_event_page =
        isset($_GET['event_page'])
        ? (int) $_GET['event_page']
        : 1;

    $total_events =
        count($events);

    $total_event_pages =
        ceil($total_events / $per_page);

    $event_start =
        ($current_event_page - 1) * $per_page;

    $current_events =
        array_slice($events, $event_start, $per_page);

    $event_showing_from =
        $total_events > 0
        ? $event_start + 1
        : 0;

    $event_showing_to =
        min($event_start + $per_page, $total_events);

    $current_pendaftar_page =
        isset($_GET['pendaftar_page'])
        ? (int) $_GET['pendaftar_page']
        : 1;

    $total_pendaftar =
        count($pendaftarEvents);

    $total_pendaftar_pages =
        ceil($total_pendaftar / $per_page);

    $pendaftar_start =
        ($current_pendaftar_page - 1) * $per_page;

    $current_pendaftar =
        array_slice(
            $pendaftarEvents,
            $pendaftar_start,
            $per_page
        );

    $pendaftar_showing_from =
        $total_pendaftar > 0
        ? $pendaftar_start + 1
        : 0;

    $pendaftar_showing_to =
        min(
            $pendaftar_start + $per_page,
            $total_pendaftar
        );

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah – Event Lingkungan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Bank Sampah Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="transaksi_setor_sampah.php" class="nav-link-custom">
                    <i class="bi bi-recycle"></i> Transaksi Setor Sampah
                </a>
            </div>
            <div class="nav-item">
                <a href="penarikan_saldo.php" class="nav-link-custom">
                    <i class="bi bi-wallet2"></i> Penarikan Saldo
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom active">
                    <i class="bi bi-calendar-event-fill"></i> Event Lingkungan
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i> Laporan dan Keuangan
                </a>
            </div>
            <div class="nav-item">
                <a href="data_nasabah.php" class="nav-link-custom">
                    <i class="bi bi-people-fill"></i> Data Nasabah
                </a>
            </div>
            <div class="nav-item">
                <a href="data_sampah.php" class="nav-link-custom">
                    <i class="bi bi-trash-fill"></i> Data Sampah
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom">
                    <i class="bi bi-gear-fill"></i> Pengaturan Akun
                </a>
            </div>
        </nav>

        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Event Lingkungan</h1>
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
                    <input 
                        type="text" 
                        class="search-input" 
                        placeholder="Cari event..." 
                        id="searchInput"
                    >
                </div>

                <div class="filter-dropdown">

                    <button class="btn-filter" onclick="toggleFilter()">
                        <i class="bi bi-sliders2"></i> Filter
                    </button>

                    <div class="filter-box">
                        <div class="filter-group" id="eventFilterGroup">
                            <label>Event</label>
                            <select id="filterEvent">
                                <option value="">Semua Event</option>

                                <?php
                                $eventNames = [];

                                foreach ($events as $ev) {
                                    $nama = $ev['nama_event'];

                                    if (!in_array($nama, $eventNames)) {
                                        $eventNames[] = $nama;

                                        echo '<option value="' .
                                            htmlspecialchars(strtolower($nama)) .
                                            '">' .
                                            htmlspecialchars($nama) .
                                            '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="filter-group" id="statusFilterGroup">
                            <label>Status</label>
                            <select id="filterStatus">
                                <option value="">Semua Status</option>
                                <option value="akan_datang">Akan Datang</option>
                                <option value="berlangsung">Berlangsung</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Urutkan</label>
                            <select id="sortTanggal">
                                <option value="desc">Terbaru</option>
                                <option value="asc">Terlama</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="button" onclick="resetFilter()">
                                Reset
                            </button>

                            <button type="button" onclick="applyFilter()">
                                Terapkan
                            </button>
                        </div>

                    </div>
                </div>

                <button class="btn-tambah" id="btnTambah" onclick="openTambah()">
                    <i class="bi bi-plus-lg"></i> Tambah Event
                </button>

            </div>
        </div>

        <div class="content-area">
            <div class="card-custom">
                <div class="tab-header">
                    <button class="tab-btn active" onclick="switchTab('event', this)">Event</button>
                    <button class="tab-btn" onclick="switchTab('pendaftar', this)">Pendaftar Event</button>
                </div>

                <div class="table-wrap" id="tab-event">
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table" id="eventTable">
                            <colgroup>
                                <col style="width: 60px;">
                                <col style="width: 250px;">
                                <col style="width: 100px;">
                                <col style="width: 220px;">
                                <col style="width: 150px;">
                                <col style="width: 320px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Event</th>
                                    <th>Tanggal</th>
                                    <th>Lokasi</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (!empty($events)): ?>
                                    <?php foreach ($current_events as $idx => $e): ?>
                                    <tr data-id="<?= $e['id_event'] ?>"
                                        data-date="<?= $e['tanggal'] . ' ' . ($e['waktu'] ?? '00:00:00') ?>">
                                        <td class="td-no"><?= $idx + 1 ?></td>
                                        <td class="td-nama">
                                            <?= htmlspecialchars($e['nama_event']) ?>
                                        </td>
                                        <td><?= date('d M Y', strtotime($e['tanggal'])) ?> <?= substr($e['waktu'], 0, 5) ?? '' ?></td>
                                        <td><?= htmlspecialchars($e['lokasi']) ?></td>
                                        <td>
                                            <?php
                                            $statusText = $e['status_display'] ?? 'Akan Datang';
                                            $statusClass = $e['status_class'] ?? 'akan_datang';
                                            echo "<span class='status-badge status-{$statusClass}'>$statusText</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button class="btn-aksi btn-lihat" onclick="lihatEvent('<?= $e['id_event'] ?>')">
                                                    <i class="bi bi-file-earmark-text"></i> Lihat
                                                </button>
                                                
                                                <?php
                                                    $allowedRoles = ['dlh', 'admin', 'bank sampah'];
                                                    
                                                    if (in_array($userRole, $allowedRoles)):
                                                ?>
                                                <button class="btn-aksi btn-edit" onclick="editEvent('<?= $e['id_event'] ?>')">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <button class="btn-aksi btn-hapus" onclick="hapusEvent('<?= $e['id_event'] ?>')">
                                                    <i class="bi bi-trash3"></i> Hapus
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4" style="color: #6B8A7E;">
                                            <i class="bi bi-calendar-x" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            Belum ada event tersedia
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer" id="footer-event">
                        <div class="showing-text">
                            Showing
                            <b><?= $event_showing_from ?></b>
                            to
                            <b><?= $event_showing_to ?></b>
                            of
                            <b><?= $total_events ?></b>
                            entries
                        </div>
    
                        <div class="pagination-custom">
                            <?php if ($current_event_page > 1): ?>
                                <a href="?event_page=<?= $current_event_page - 1 ?>"
                                class="page-btn page-btn-text">
                                    Prev
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_event_pages; $i++): ?>
                                <a href="?event_page=<?= $i ?>"
                                class="page-btn <?= $i == $current_event_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_event_page < $total_event_pages): ?>
                                <a href="?event_page=<?= $current_event_page + 1 ?>"
                                class="page-btn page-btn-text">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="table-wrap" id="tab-pendaftar" style="display:none;">
                    <div class="table-scroll-wrapper">
                        <table class="responsive-table">
                            <colgroup>
                                <col style="width: 60px;">
                                <col style="width: 220px;">
                                <col style="width: 170px;">
                                <col style="width: 250px;">
                                <col style="width: 260px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Lengkap</th>
                                    <th>No Telepon</th>
                                    <th>Email</th>
                                    <th>Nama Event</th>
                                </tr>
                            </thead>

                            <tbody id="pendaftarTableBody">
                                <?php if (!empty($pendaftarEvents)): ?>
                                    <?php foreach ($current_pendaftar as $idx => $p): ?>
                                    <tr data-date="<?= $p['created_at'] ?>">
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <?= htmlspecialchars($p['nama_lengkap'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($p['no_telepon'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($p['email'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($p['event']['nama_event'] ?? '-') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            Belum ada pendaftar event
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer"
                        id="footer-pendaftar"
                        style="display:none;">
    
                        <div class="showing-text">
                            Showing
                            <b><?= $pendaftar_showing_from ?></b>
                            to
                            <b><?= $pendaftar_showing_to ?></b>
                            of
                            <b><?= $total_pendaftar ?></b>
                            entries
                        </div>
    
                        <div class="pagination-custom">
                            <?php if ($current_pendaftar_page > 1): ?>
                                <a href="?pendaftar_page=<?= $current_pendaftar_page - 1 ?>&tab=pendaftar"
                                class="page-btn page-btn-text">
                                    Prev
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pendaftar_pages; $i++): ?>
                                <a href="?pendaftar_page=<?= $i ?>&tab=pendaftar"
                                class="page-btn <?= $i == $current_pendaftar_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_pendaftar_page < $total_pendaftar_pages): ?>
                                <a href="?pendaftar_page=<?= $current_pendaftar_page + 1 ?>&tab=pendaftar"
                                class="page-btn page-btn-text">
                                    Next
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalHapus">
        <div class="modal-box" style="max-width:400px;">
            <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
            <div class="confirm-text">
                <h3>Hapus Event?</h3>
                <p>Tindakan ini tidak dapat dibatalkan. Data event akan dihapus secara permanen.</p>
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
        const events = <?= json_encode($events) ?>;
        let editingId = null;
        let deletingId = null;

        function switchTab(tab, el) {

            document
                .querySelectorAll('.tab-btn')
                .forEach(b => b.classList.remove('active'));

            el.classList.add('active');

            document.getElementById('tab-event').style.display =
                tab === 'event' ? '' : 'none';

            document.getElementById('tab-pendaftar').style.display =
                tab === 'pendaftar' ? '' : 'none';

            document.getElementById('footer-event').style.display =
                tab === 'event' ? 'flex' : 'none';

            document.getElementById('footer-pendaftar').style.display =
                tab === 'pendaftar' ? 'flex' : 'none';

            const searchInput =
                document.getElementById('searchInput');

            const btnTambah =
                document.getElementById('btnTambah');

            const statusFilterGroup =
                document.getElementById('statusFilterGroup');

            const filterStatusGroup =
                document.getElementById('statusFilterGroup');

            const filterEventGroup =
                document.getElementById('eventFilterGroup');

            const sortLabel =
                document.querySelector(
                    '.filter-group label'
                );

            const sortSelect =
                document.getElementById('sortTanggal');

            if (tab === 'pendaftar') {

                searchInput.placeholder =
                    'Cari pendaftar...';

                btnTambah.style.display = 'none';
                filterStatusGroup.style.display = 'none';
                filterEventGroup.style.display = 'block';
                sortLabel.textContent = 'Urutkan Nama';

                sortSelect.innerHTML = `
                    <option value="az">Nama A - Z</option>
                    <option value="za">Nama Z - A</option>
                `;

            } else if (tab === 'event') {

                searchInput.placeholder =
                    'Cari event...';

                btnTambah.style.display = 'flex';

                filterStatusGroup.style.display = 'block';

                filterEventGroup.style.display = 'none';

                sortLabel.textContent = 'Urutkan Tanggal';

                sortSelect.innerHTML = `
                    <option value="desc">Terbaru</option>
                    <option value="asc">Terlama</option>
                `;
            }

        }

        document
            .getElementById('searchInput')
            .addEventListener('input', function() {

            const q =
                this.value.toLowerCase();

            const activeTab =
                document.querySelector('.tab-btn.active')
                .textContent
                .trim()
                .toLowerCase();

            const targetTable =
                activeTab.includes('pendaftar')
                ? '#pendaftarTableBody tr'
                : '#tableBody tr';

            document.querySelectorAll(targetTable)
                .forEach(row => {

                const text =
                    row.textContent.toLowerCase();

                row.style.display =
                    text.includes(q) ? '' : 'none';
            });

            if (activeTab.includes('pendaftar')) {
                updateRowNumbers('#pendaftarTableBody');
            } else {
                updateRowNumbers('#tableBody');
            }
        });

        function toggleFilter() {
            document
                .querySelector('.filter-box')
                .classList.toggle('show');
        }

        function applyFilter() {

            const activeTab =
                document.querySelector('.tab-btn.active')
                .textContent
                .trim()
                .toLowerCase();

            const isPendaftar =
                activeTab.includes('pendaftar');

            const sortValue =
                document.getElementById('sortTanggal').value;

            if (isPendaftar) {
                const selectedEvent =
                    document.getElementById('filterEvent').value
                    .toLowerCase();

                const tbody =
                    document.getElementById('pendaftarTableBody');

                let rows =
                    Array.from(tbody.querySelectorAll('tr'));

                rows.forEach(row => {

                    const eventName =
                        row.children[4]
                        .textContent
                        .trim()
                        .toLowerCase();

                    let visible = true;

                    if (
                        selectedEvent &&
                        eventName !== selectedEvent
                    ) {
                        visible = false;
                    }

                    row.style.display =
                        visible ? '' : 'none';
                });

                rows.sort((a, b) => {
                    const namaA =
                        a.children[1]
                        .textContent
                        .trim()
                        .toLowerCase();

                    const namaB =
                        b.children[1]
                        .textContent
                        .trim()
                        .toLowerCase();

                    return sortValue === 'az'
                        ? namaA.localeCompare(namaB)
                        : namaB.localeCompare(namaA);
                });
                rows.forEach(row => tbody.appendChild(row));
                updateRowNumbers('#pendaftarTableBody');

            } else {
                const status =
                    document.getElementById('filterStatus').value;

                const tbody =
                    document.getElementById('tableBody');

                let rows =
                    Array.from(tbody.querySelectorAll('tr'));

                rows.forEach(row => {
                    const badge =
                        row.querySelector('.status-badge');
                    const rowStatus =
                        badge
                            ?.textContent
                            .trim()
                            .toLowerCase()
                            .replaceAll(' ', '_');
                    let visible = true;
                    if (status && rowStatus !== status) {
                        visible = false;
                    }
                    row.style.display =
                        visible ? '' : 'none';
                });

                rows.sort((a, b) => {

                    const dateA =
                        new Date(a.dataset.date);

                    const dateB =
                        new Date(b.dataset.date);

                    return sortValue === 'asc'
                        ? dateB - dateA
                        : dateA - dateB;
                });
                rows.forEach(row => tbody.appendChild(row));
                updateRowNumbers('#tableBody');
            }
            document
                .querySelector('.filter-box')
                .classList.remove('show');
        }

        function resetFilter() {
            const activeTab =
                document.querySelector('.tab-btn.active')
                .textContent
                .trim()
                .toLowerCase();

            const isPendaftar =
                activeTab.includes('pendaftar');

            if (isPendaftar) {
                document.getElementById('filterEvent').value = '';
                document.getElementById('sortTanggal').value = '';
                const rows =
                    document.querySelectorAll('#pendaftarTableBody tr');
                rows.forEach(row => {
                    row.style.display = '';
                });
            } else {
                document.getElementById('filterStatus').value = '';
                document.getElementById('sortTanggal').value = 'desc';
                const rows =
                    document.querySelectorAll('#tableBody tr');
                rows.forEach(row => {
                    row.style.display = '';
                });
            }

            applyFilter();
        }

        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) m.classList.remove('show');
            });
        });

        function lihatEvent(id) {
            window.location.href = `event_lihat.php?id=${id}`;
        }

        function editEvent(id) {
            window.location.href = `event_edit.php?id=${id}`;
        }

        function openTambah() {
            window.location.href = 'event_tambah.php';
        }

        function row(label, val) {
            return `<tr><td style="padding:6px 0; color:#6B8A7E; width:130px; vertical-align:top;">${label}</td>
                    <td style="padding:6px 0; font-weight:500;">${val}</td>
                </tr>`;
        }

        function capitalize(s) {
            if (!s) return '-';
            return s.charAt(0).toUpperCase() + s.slice(1);
        }

        function hapusEvent(id) { 
            deletingId = id; 
            openModal('modalHapus'); 
        }

        function confirmHapus() {
            if (!deletingId) return;

            fetch('event_hapus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + encodeURIComponent(deletingId)
            })
            .then(res => res.json())
            .then(data => {
                console.log(data);

                if (data.success) {

                    showToast('Event berhasil dihapus.', 'success');

                    setTimeout(() => {
                        location.reload();
                    }, 800);

                } else {

                    console.error(data);

                    showToast(data.message || 'Gagal menghapus event.', 'error');
                }

                closeModal('modalHapus');
            })
            .catch(() => {
                showToast('Terjadi kesalahan server.', 'error');
            });
        }

        document.querySelectorAll('.page-btn:not(.page-btn-text)').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.page-btn:not(.page-btn-text)').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        function showToast(msg, type = 'success') {
            const icons = { 
                success: 'bi-check-circle-fill', 
                error: 'bi-x-circle-fill', 
                info: 'bi-info-circle-fill' 
            };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }

        function updateRowNumbers(tableSelector) {
            const rows =
                document.querySelectorAll(
                    `${tableSelector} tr`
                );

            let no = 1;

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    row.children[0].textContent = no++;
                }
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const active =
                params.get('tab');
            if (active === 'pendaftar') {
                const btn =
                    document.querySelectorAll('.tab-btn')[1];
                switchTab('pendaftar', btn);
            } else {
                const btn =
                    document.querySelectorAll('.tab-btn')[0];
                switchTab('event', btn);
            }
        });
    </script>

</body>
</html>