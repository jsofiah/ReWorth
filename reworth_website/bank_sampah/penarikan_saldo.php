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
$userId    = $_SESSION['id_admin']    ?? '';

function supabaseGet($url, $key) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json",
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $res) ? (json_decode($res, true) ?: []) : [];
}

function getSupabaseImageUrl($path) {
    if (empty($path)) return null;
    return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
}

$penarikanUrl  = $supabaseUrl . "/rest/v1/penarikan_saldo?select=*,pengguna(nama_lengkap)&order=created_at.desc";
$penarikanList = supabaseGet($penarikanUrl, $supabaseKey);

$per_page    = 10;
$total       = count($penarikanList);
$total_pages = max(1, ceil($total / $per_page));
$cur_page    = max(1, min((int)($_GET['page'] ?? 1), $total_pages));
$offset      = ($cur_page - 1) * $per_page;
$current     = array_slice($penarikanList, $offset, $per_page);
$show_from   = $total > 0 ? $offset + 1 : 0;
$show_to     = min($offset + $per_page, $total);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah – Penarikan Saldo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Logo ReWorth">
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="dashboard.php"               class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="transaksi_setor_sampah.php"  class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
        <div class="nav-item"><a href="penarikan_saldo.php"         class="nav-link-custom active"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php"        class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="laporan_keuangan.php"        class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
        <div class="nav-item"><a href="data_nasabah.php"            class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
        <div class="nav-item"><a href="data_sampah.php"             class="nav-link-custom"><i class="bi bi-trash-fill"></i><span>Data Sampah</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php"         class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
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
            <h1 class="topbar-title">Penarikan Saldo</h1>
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
                <input type="text" class="search-input" placeholder="Cari penarikan..." id="searchInput">
            </div>

            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()">
                    <i class="bi bi-sliders2"></i> Filter
                </button>
                <div class="filter-box" id="filterBox">
                    <div class="filter-group">
                        <label>Urutkan Tanggal</label>
                        <select id="sortTanggal">
                            <option value="desc">Terbaru</option>
                            <option value="asc">Terlama</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Jumlah Minimum (Rp)</label>
                        <input type="number" id="filterMinJumlah" class="form-control-custom"
                               placeholder="0" min="0"
                               style="border-radius:12px;padding:10px 12px;border:1px solid #D8E6DE;">
                    </div>
                    <div class="filter-actions">
                        <button type="button" onclick="resetFilter()">Reset</button>
                        <button type="button" onclick="applyFilter()">Terapkan</button>
                    </div>
                </div>
            </div>

            <?php if (in_array($userRole, ['bank sampah', 'dlh', 'admin'])): ?>
            <!-- ✅ PERUBAHAN 1: redirect ke halaman tambah, bukan buka modal -->
            <button class="btn-tambah" onclick="window.location.href='penarikan_tambah.php'">
                <i class="bi bi-plus-lg"></i> Tambah Penarikan
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="card-custom">
            <div class="table-wrap">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table" id="penarikanTable">
                        <colgroup>
                            <col style="width:60px;">
                            <col style="width:280px;">
                            <col style="width:200px;">
                            <col style="width:200px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>Pengguna</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-center">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        <?php if (!empty($current)): ?>
                            <?php foreach ($current as $idx => $p): ?>
                            <tr data-id="<?= $p['id_penarikan'] ?>"
                                data-jumlah="<?= $p['jumlah'] ?>"
                                data-date="<?= $p['created_at'] ?>">
                                <td class="text-center"><?= $offset + $idx + 1 ?></td>
                                <td class="td-nama"><?= htmlspecialchars($p['pengguna']['nama_lengkap'] ?? '-') ?></td>
                                <td class="text-center">Rp <?= number_format($p['jumlah'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= date('d-m-Y', strtotime($p['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4" style="color:#6B8A7E;">
                                    <i class="bi bi-wallet2" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                    Belum ada data penarikan saldo
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div class="showing-text">
                        Showing <b><?= $show_from ?></b> to <b><?= $show_to ?></b> of <b><?= $total ?></b> entries
                    </div>
                    <div class="pagination-custom">

                        <?php
                        $start_p = max(1, $cur_page - 2);
                        $end_p   = min($total_pages, $cur_page + 2);
                        if ($start_p > 1) echo '<span class="page-dots">...</span>';
                        for ($i = $start_p; $i <= $end_p; $i++):
                        ?>
                            <a href="?page=<?= $i ?>" class="page-btn <?= $i == $cur_page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor;
                        if ($end_p < $total_pages) echo '<span class="page-dots">...</span>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ PERUBAHAN 2: modal tambah dihapus sepenuhnya -->

<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── toast ── */
function showToast(msg, type = 'success') {
    const icons = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', info:'bi-info-circle-fill' };
    const div = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}

/* ── search ── */
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    updateRowNumbers();
});

/* ── filter ── */
function toggleFilter() { document.getElementById('filterBox').classList.toggle('show'); }

function applyFilter() {
    const sort   = document.getElementById('sortTanggal').value;
    const minJml = parseFloat(document.getElementById('filterMinJumlah').value) || 0;
    const tbody  = document.getElementById('tableBody');
    let rows     = Array.from(tbody.querySelectorAll('tr'));

    rows.forEach(row => {
        const jumlah = parseFloat(row.dataset.jumlah || 0);
        row.style.display = jumlah >= minJml ? '' : 'none';
    });

    rows.sort((a, b) => {
        const da = new Date(a.dataset.date);
        const db = new Date(b.dataset.date);
        return sort === 'asc' ? da - db : db - da;
    });

    rows.forEach(row => tbody.appendChild(row));
    updateRowNumbers();
    document.getElementById('filterBox').classList.remove('show');
}

function resetFilter() {
    document.getElementById('sortTanggal').value     = 'desc';
    document.getElementById('filterMinJumlah').value = '';
    document.querySelectorAll('#tableBody tr').forEach(r => r.style.display = '');
    updateRowNumbers();
    document.getElementById('filterBox').classList.remove('show');
}

function updateRowNumbers() {
    let no = 1;
    document.querySelectorAll('#tableBody tr').forEach(r => {
        if (r.style.display !== 'none') r.children[0].textContent = no++;
    });
}

</script>
</body>
</html>
