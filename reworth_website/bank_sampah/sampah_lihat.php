<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? '';
$userRole  = $_SESSION['role']        ?? '';
$userFoto  = $_SESSION['foto_profil'] ?? '';

$id = trim($_GET['id'] ?? '');
if (empty($id)) { header("Location: data_sampah.php"); exit; }

function getSupabaseImageUrl($p) {
    return empty($p) ? null : "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');
}
function sbGet($url, $key, $ep) {
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}
function formatRupiah($n) { return 'Rp ' . number_format((float)($n ?? 0), 0, ',', '.'); }


$rows = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/jenis_sampah?id_jenis=eq." . urlencode($id) . "&select=*&limit=1");
if (empty($rows)) { header("Location: data_sampah.php"); exit; }
$s = $rows[0];

$canEdit = in_array($userRole, ['bank sampah', 'admin', 'dlh']);

$tanggalDibuat  = !empty($s['created_at'])  ? date('d M Y', strtotime($s['created_at']))  : '-';
$tanggalDiubah  = !empty($s['updated_at'])  ? date('d M Y', strtotime($s['updated_at']))  : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Detail Jenis Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Logo ReWorth" title="Bank Sampah Kota Malang">
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="transaksi_setor_sampah.php" class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
        <div class="nav-item"><a href="penarikan_saldo.php" class="nav-link-custom"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
         <div class="nav-item"><a href="jadwal_ambil_sampah.php"class="nav-link-custom"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
        <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
        <div class="nav-item"><a href="data_nasabah.php" class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
        <div class="nav-item"><a href="data_sampah.php" class="nav-link-custom active"><i class="bi bi-trash-fill"></i><span>Data Sampah</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
</aside>

<div class="main-wrap">
    
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Data Sampah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): $fu = getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fu) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-wrap">
        <div class="detail-card">
            <div class="card-accent-bar"></div>
            <div class="card-inner">

                
                <div class="card-title"><?= htmlspecialchars($s['nama_sampah'] ?? '-') ?></div>
                <div class="card-subtitle">
                    <i class="bi bi-trash-fill me-1"></i> Detail Jenis Sampah
                </div>

                
                <div class="harga-badge">
                    <i class="bi bi-tag-fill"></i>
                    <?= formatRupiah($s['harga_per_kg'] ?? 0) ?>
                    <small>/ kg</small>
                </div>

                
                <div class="section-label"><i class="bi bi-info-circle-fill"></i> Informasi Sampah</div>
                <div class="row-2cols">
                    <div>
                        <label class="field-label">Nama Jenis Sampah</label>
                        <input class="field-readonly" value="<?= htmlspecialchars($s['nama_sampah'] ?? '-') ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label">Harga per Kg (Rp)</label>
                        <input class="field-readonly highlight" value="<?= formatRupiah($s['harga_per_kg'] ?? 0) ?>" readonly>
                    </div>
                </div>

                <div class="row-2cols">
                    <div>
                        <label class="field-label">Tanggal Ditambahkan</label>
                        <input class="field-readonly" value="<?= $tanggalDibuat ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label">Terakhir Diperbarui</label>
                        <input class="field-readonly" value="<?= $tanggalDiubah ?>" readonly>
                    </div>
                </div>

                
                <div class="card-actions">
                    <button class="btn-back" onclick="window.location.href='data_sampah.php'">
                        <i class="bi bi-arrow-left me-1"></i> KEMBALI
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>


<div class="modal-overlay" id="modalHapus">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
        <div class="confirm-text">
            <h3>Hapus Jenis Sampah?</h3>
            <p><strong>"<?= htmlspecialchars($s['nama_sampah'] ?? '') ?>"</strong> akan dihapus secara permanen dan tidak bisa dikembalikan.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
            <button id="btnConfirmHapus" class="btn-aksi btn-hapus"
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
const SB_URL   = <?= json_encode($supabaseUrl) ?>;
const SB_KEY   = <?= json_encode($supabaseKey) ?>;
const JENIS_ID = <?= json_encode($id) ?>;


function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); })
);


async function confirmHapus() {
    const btn = document.getElementById('btnConfirmHapus');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menghapus...';

    const res = await fetch(SB_URL + '/rest/v1/jenis_sampah?id_jenis=eq.' + JENIS_ID, {
        method : 'DELETE',
        headers: {
            'apikey'       : SB_KEY,
            'Authorization': 'Bearer ' + SB_KEY
        }
    });

    if (res.ok) {
        const ADMIN_ID = "<?= $_SESSION['id_admin'] ?? '' ?>";
        if (ADMIN_ID) fetch(SB_URL + '/rest/v1/log_admin', { method: 'POST', headers: { 'apikey': SB_KEY, 'Authorization': 'Bearer ' + SB_KEY, 'Content-Type': 'application/json' }, body: JSON.stringify({ id_admin: ADMIN_ID, aktivitas: "Menghapus jenis sampah", tabel_terkait: 'jenis_sampah', id_data: JENIS_ID, created_at: new Date().toISOString().split('.')[0] + 'Z' }) });
    
        showToast('Jenis sampah berhasil dihapus.', 'success');
        setTimeout(() => window.location.href = 'data_sampah.php', 900);
    } else {
        showToast('Gagal menghapus. Mungkin data masih digunakan.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash3"></i> Ya, Hapus';
        closeModal('modalHapus');
    }
}


function showToast(msg, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
    const div   = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}
</script>
</body>
</html>