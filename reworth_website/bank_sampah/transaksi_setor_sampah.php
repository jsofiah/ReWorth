<?php
require_once 'role_check.php';

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

function supabaseGet($supabaseUrl, $supabaseKey, $endpoint) {
    $ch = curl_init($supabaseUrl . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? (json_decode($res, true) ?: []) : [];
}

function formatStatus($status) {
    $map = [
        'menunggu' => ['label' => 'Menunggu Konfirmasi', 'class' => 'menunggu'],
        'diproses' => ['label' => 'Diproses',            'class' => 'diproses'],
        'selesai'  => ['label' => 'Selesai',             'class' => 'selesai'],
        'ditolak'  => ['label' => 'Ditolak',             'class' => 'menunggu'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status ?? 'Menunggu'), 'class' => 'menunggu'];
}

function formatJadwal($jadwal) {
    if (empty($jadwal)) return '-';
    $tgl     = !empty($jadwal['tanggal'])       ? date('d M Y', strtotime($jadwal['tanggal'])) : '';
    $mulai   = !empty($jadwal['waktu_mulai'])   ? substr($jadwal['waktu_mulai'],  0, 5) : '';
    $selesai = !empty($jadwal['waktu_selesai']) ? substr($jadwal['waktu_selesai'], 0, 5) : '';
    return $tgl . '<br><span style="color:#6B8A7E;font-size:11px;">' . $mulai . ' – ' . $selesai . '</span>';
}

function formatRupiah($num) {
    return 'Rp' . number_format((float)($num ?? 0), 0, ',', '.');
}


$allTransaksi = supabaseGet(
    $supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?select=*,pengguna(nama_lengkap,alamat_detail),jadwal_ambil(tanggal,waktu_mulai,waktu_selesai)&order=created_at.desc"
);

$verifikasiList = array_values(
    array_filter($allTransaksi, fn($t) => ($t['status'] ?? '') === 'menunggu')
);

$allTransaksi = array_values(
    array_filter($allTransaksi, fn($t) => ($t['status'] ?? '') !== 'menunggu')
);


$per_page = 10;

$cur_verif_page    = max(1, (int)($_GET['verif_page'] ?? 1));
$total_verif       = count($verifikasiList);
$total_verif_pages = max(1, ceil($total_verif / $per_page));
$verif_start       = ($cur_verif_page - 1) * $per_page;
$cur_verif         = array_slice($verifikasiList, $verif_start, $per_page);
$verif_from        = $total_verif > 0 ? $verif_start + 1 : 0;
$verif_to          = min($verif_start + $per_page, $total_verif);

$cur_all_page    = max(1, (int)($_GET['all_page'] ?? 1));
$total_all       = count($allTransaksi);
$total_all_pages = max(1, ceil($total_all / $per_page));
$all_start       = ($cur_all_page - 1) * $per_page;
$cur_all         = array_slice($allTransaksi, $all_start, $per_page);
$all_from        = $total_all > 0 ? $all_start + 1 : 0;
$all_to          = min($all_start + $per_page, $total_all);


$jadwalList = [];
foreach ($allTransaksi as $t) {
    if (!empty($t['jadwal_ambil']['tanggal'])) {
        $key = $t['id_jadwal'];
        if (!isset($jadwalList[$key])) {
            $j = $t['jadwal_ambil'];
            $jadwalList[$key] = date('d M Y', strtotime($j['tanggal']))
                . ' ' . substr($j['waktu_mulai'] ?? '', 0, 5)
                . '–' . substr($j['waktu_selesai'] ?? '', 0, 5);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah – Transaksi Setor Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    </head>
<body>


<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Logo ReWorth">
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link-custom">
                <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="transaksi_setor_sampah.php" class="nav-link-custom active">
                <i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="penarikan_saldo.php" class="nav-link-custom">
                <i class="bi bi-wallet2"></i><span>Penarikan Saldo</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="event_lingkungan.php" class="nav-link-custom">
                <i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="jadwal_ambil_sampah.php" class="nav-link-custom">
                <i class="bi bi-calendar2-week-fill"></i>                    <span>Jadwal Ambil Sampah</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="laporan_keuangan.php" class="nav-link-custom">
                <i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="data_nasabah.php" class="nav-link-custom">
                <i class="bi bi-people-fill"></i><span>Data Nasabah</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="data_sampah.php" class="nav-link-custom">
                <i class="bi bi-trash-fill"></i><span>Data Sampah</span>
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
            <h1 class="topbar-title">Transaksi Setor Sampah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fotoUrl) ?>"
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
                <input type="text" class="search-input" placeholder="Cari transaksi..." id="searchInput">
            </div>

            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()">
                    <i class="bi bi-sliders2"></i> Filter
                </button>
                <div class="filter-box" id="filterBox">
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus">
                            <option value="">Semua Status</option>
                            <option value="menunggu">Menunggu Konfirmasi</option>
                            <option value="diproses">Diproses</option>
                            <option value="selesai">Selesai</option>
                            <option value="ditolak">Ditolak</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Jadwal</label>
                        <select id="filterJadwal">
                            <option value="">Semua Jadwal</option>
                            <?php foreach ($jadwalList as $jid => $label): ?>
                                <option value="<?= htmlspecialchars($jid) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
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

            <button class="btn-tambah" onclick="window.location.href='setor_tambah.php'">
                <i class="bi bi-plus-lg"></i> Tambah Setor
            </button>
        </div>
    </div>

    <div class="content-area">
        <div class="card-custom">

            <div class="tab-header">
                <button class="tab-btn active" onclick="switchTab('verifikasi', this)">Verifikasi Pengajuan</button>
                <button class="tab-btn"        onclick="switchTab('semua', this)">Semua Transaksi</button>
            </div>

            
            <div id="tab-verifikasi">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table">
                        <colgroup>
                            <col style="width:55px;">
                            <col style="width:200px;">
                            <col style="width:220px;">
                            <col style="width:170px;">
                            <col style="width:130px;">
                            <col style="width:180px;">
                            <col style="width:120px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Penyetor</th>
                                <th>Alamat</th>
                                <th>Jadwal</th>
                                <th>Total Uang</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodyVerif">
                        <?php if (!empty($cur_verif)): ?>
                            <?php foreach ($cur_verif as $i => $t):
                                $st = formatStatus($t['status'] ?? 'menunggu');
                            ?>
                            <tr data-created="<?= htmlspecialchars($t['created_at'] ?? '') ?>"
                                data-jadwal="<?= htmlspecialchars($t['id_jadwal'] ?? '') ?>"
                                data-status="<?= htmlspecialchars($t['status'] ?? '') ?>">
                                <td class="td-no"><?= $verif_start + $i + 1 ?></td>
                                <td class="td-nama"><?= htmlspecialchars($t['pengguna']['nama_lengkap'] ?? '-') ?></td>
                                <td style="font-size:12px;"><?= htmlspecialchars($t['alamat'] ?? '-') ?></td>
                                <td class="td-jadwal"><?= formatJadwal($t['jadwal_ambil'] ?? null) ?></td>
                                <td style="font-weight:600;"><?= formatRupiah($t['total_uang'] ?? 0) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $st['class'] ?>">
                                        <?= $st['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="aksi-wrap">
                                        <button class="btn-aksi btn-lihat"
                                            onclick="window.location.href='setor_lihat.php?id=<?= $t['id_setor'] ?>'">
                                            <i class="bi bi-file-earmark-text"></i> Lihat
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4" style="color:#6B8A7E;">
                                    <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                    Tidak ada transaksi yang menunggu verifikasi
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="footer-verif">
                    <div class="showing-text">
                        Showing <b><?= $verif_from ?></b> to <b><?= $verif_to ?></b> of <b><?= $total_verif ?></b> entries
                    </div>
                    <div class="pagination-custom">
                        <?php if ($cur_verif_page > 1): ?>
                            <a href="?verif_page=<?= $cur_verif_page-1 ?>" class="page-btn page-btn-text">Prev</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $total_verif_pages; $p++): ?>
                            <a href="?verif_page=<?= $p ?>" class="page-btn <?= $p == $cur_verif_page ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <?php if ($cur_verif_page < $total_verif_pages): ?>
                            <a href="?verif_page=<?= $cur_verif_page+1 ?>" class="page-btn page-btn-text">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div id="tab-semua" style="display:none;">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table">
                        <colgroup>
                            <col style="width:55px;">
                            <col style="width:200px;">
                            <col style="width:220px;">
                            <col style="width:170px;">
                            <col style="width:130px;">
                            <col style="width:180px;">
                            <col style="width:120px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Penyetor</th>
                                <th>Alamat</th>
                                <th>Jadwal</th>
                                <th>Total Uang</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodySemua">
                        <?php if (!empty($cur_all)): ?>
                            <?php foreach ($cur_all as $i => $t):
                                $st = formatStatus($t['status'] ?? 'menunggu');
                            ?>
                            <tr data-created="<?= htmlspecialchars($t['created_at'] ?? '') ?>"
                                data-jadwal="<?= htmlspecialchars($t['id_jadwal'] ?? '') ?>"
                                data-status="<?= htmlspecialchars($t['status'] ?? '') ?>">
                                <td class="td-no"><?= $all_start + $i + 1 ?></td>
                                <td class="td-nama"><?= htmlspecialchars($t['pengguna']['nama_lengkap'] ?? '-') ?></td>
                                <td style="font-size:12px;"><?= htmlspecialchars($t['alamat'] ?? '-') ?></td>
                                <td class="td-jadwal"><?= formatJadwal($t['jadwal_ambil'] ?? null) ?></td>
                                <td style="font-weight:600;"><?= formatRupiah($t['total_uang'] ?? 0) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $st['class'] ?>">
                                        <?= $st['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="aksi-wrap">
                                        <button class="btn-aksi btn-lihat"
                                            onclick="window.location.href='setor_lihat.php?id=<?= $t['id_setor'] ?>'">
                                            <i class="bi bi-file-earmark-text"></i> Lihat
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4" style="color:#6B8A7E;">
                                    <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                    Belum ada transaksi setor sampah
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="footer-semua">
                    <div class="showing-text">
                        Showing <b><?= $all_from ?></b> to <b><?= $all_to ?></b> of <b><?= $total_all ?></b> entries
                    </div>
                    <div class="pagination-custom">
                        <?php if ($cur_all_page > 1): ?>
                            <a href="?all_page=<?= $cur_all_page-1 ?>&tab=semua" class="page-btn page-btn-text">Prev</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $total_all_pages; $p++): ?>
                            <a href="?all_page=<?= $p ?>&tab=semua" class="page-btn <?= $p == $cur_all_page ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <?php if ($cur_all_page < $total_all_pages): ?>
                            <a href="?all_page=<?= $cur_all_page+1 ?>&tab=semua" class="page-btn page-btn-text">Next</a>
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
let activeTab = 'verifikasi';

function switchTab(tab, el) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    activeTab = tab;

    document.getElementById('tab-verifikasi').style.display = tab === 'verifikasi' ? '' : 'none';
    document.getElementById('tab-semua').style.display      = tab === 'semua'      ? '' : 'none';
    document.getElementById('footer-verif').style.display  = tab === 'verifikasi' ? 'flex' : 'none';
    document.getElementById('footer-semua').style.display  = tab === 'semua'      ? 'flex' : 'none';

    document.getElementById('searchInput').value = '';
    document.querySelectorAll('#bodyVerif tr, #bodySemua tr').forEach(r => r.style.display = '');
}

document.getElementById('searchInput').addEventListener('input', function () {
    const q  = this.value.toLowerCase();
    const tb = activeTab === 'verifikasi' ? '#bodyVerif tr' : '#bodySemua tr';
    document.querySelectorAll(tb).forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    rebuildNumbers(activeTab === 'verifikasi' ? '#bodyVerif' : '#bodySemua');
});

function toggleFilter() {
    document.getElementById('filterBox').classList.toggle('show');
}

function applyFilter() {
    const status  = document.getElementById('filterStatus').value;
    const jadwal  = document.getElementById('filterJadwal').value;
    const sort    = document.getElementById('sortOrder').value;
    const tbodyId = activeTab === 'verifikasi' ? '#bodyVerif' : '#bodySemua';
    const tbody   = document.querySelector(tbodyId);
    let rows      = Array.from(tbody.querySelectorAll('tr'));

    rows.forEach(row => {
        const rs = row.dataset.status || '';
        const rj = row.dataset.jadwal || '';
        let show = true;
        if (status && rs !== status) show = false;
        if (jadwal && rj !== jadwal) show = false;
        row.style.display = show ? '' : 'none';
    });

    rows.sort((a, b) => {
        const dA = new Date(a.dataset.created || 0);
        const dB = new Date(b.dataset.created || 0);
        return sort === 'asc' ? dB - dA : dA - dB;
    });
    rows.forEach(r => tbody.appendChild(r));
    rebuildNumbers(tbodyId);
    document.getElementById('filterBox').classList.remove('show');
}

function resetFilter() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterJadwal').value = '';
    document.getElementById('sortOrder').value    = 'desc';
    applyFilter();
}

function rebuildNumbers(sel) {
    let no = 1;
    document.querySelectorAll(sel + ' tr').forEach(r => {
        if (r.style.display !== 'none' && r.children[0])
            r.children[0].textContent = no++;
    });
}

function showToast(msg, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
    const div   = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}

window.addEventListener('DOMContentLoaded', () => {
    const tab = new URLSearchParams(location.search).get('tab') || 'verifikasi';
    const btn = tab === 'semua'
        ? document.querySelectorAll('.tab-btn')[1]
        : document.querySelectorAll('.tab-btn')[0];
    switchTab(tab, btn);
});
</script>
</body>
</html>