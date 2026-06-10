<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? 'user@example.com';
$userFoto  = $_SESSION['foto_profil'] ?? '';

function getSupabaseImageUrl($p) {
    return empty($p) ? null : "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($p, '/');
}
function sbGet($url, $key, $ep) {
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}


$penggunaList = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/pengguna?select=id_pengguna,nama_lengkap,saldo_tabungan&saldo_tabungan=gt.0&order=nama_lengkap.asc");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tambah Penarikan Saldo</title>
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
        <div class="nav-item"><a href="dashboard.php"               class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="transaksi_setor_sampah.php"  class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
        <div class="nav-item"><a href="penarikan_saldo.php"         class="nav-link-custom active"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php"        class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="jadwal_ambil_sampah.php"     class="nav-link-custom"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
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
        <div class="form-card form-card-padded">

            <div class="card-header-orange card-header-rounded">
                <h2>Tambah Penarikan</h2>
            </div>

            <div class="fields-wrap" style="padding:0;">

                
                <div class="field-group">
                    <label class="field-label">Nama Nasabah</label>
                    <div class="select-wrap">
                        <select id="idPengguna" class="field-underline" onchange="onNasabahChange(this)">
                            <option value="" data-saldo="0">Nama nasabah...</option>
                            <?php foreach ($penggunaList as $p): ?>
                            <option value="<?= htmlspecialchars($p['id_pengguna']) ?>"
                                    data-saldo="<?= (float)($p['saldo_tabungan'] ?? 0) ?>"
                                    data-nama="<?= htmlspecialchars($p['nama_lengkap']) ?>">
                                <?= htmlspecialchars($p['nama_lengkap']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="errNasabah" class="field-err">Nama nasabah wajib dipilih.</div>
                </div>

                
                <div class="field-group">
                    <label class="field-label">Jumlah Tabungan</label>
                    <input type="text" id="saldoTabungan" class="field-underline"
                           value="" placeholder="Rp0" readonly>
                    <div id="saldoBadge" class="saldo-badge">
                        <i class="bi bi-info-circle me-1"></i>Saldo tersedia untuk ditarik
                    </div>
                </div>

                
                <div class="field-group">
                    <label class="field-label">Jumlah Penarikan</label>
                    <input type="number" id="jumlahPenarikan" class="field-underline"
                           placeholder="Rp0" min="0" step="1000"
                           oninput="onJumlahChange(this)">
                    <div id="errJumlah" class="field-err">Jumlah penarikan wajib diisi.</div>
                    <div id="warnSaldo" class="warn-saldo">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Jumlah penarikan melebihi saldo tabungan.
                    </div>
                </div>

            </div>

            <div class="form-actions">
                <button type="button" class="btn-batal"
                    onclick="window.location.href='penarikan_saldo.php'">
                    BATAL
                </button>
                <button type="button" id="btnSimpan" class="btn-simpan" onclick="simpanData()">
                    SIMPAN DATA
                </button>
            </div>

        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const NASABAH_LIST = <?= json_encode($penggunaList) ?>;
let currentSaldo = 0;

function fmt(n) {
    return 'Rp' + parseFloat(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}


function onNasabahChange(sel) {
    const opt   = sel.options[sel.selectedIndex];
    const saldo = parseFloat(opt.dataset.saldo || 0);
    currentSaldo = saldo;

    const saldoEl = document.getElementById('saldoTabungan');
    const badge   = document.getElementById('saldoBadge');

    if (sel.value) {
        saldoEl.value = fmt(saldo);
        badge.style.display = 'block';
    } else {
        saldoEl.value = '';
        badge.style.display = 'none';
        currentSaldo = 0;
    }


    document.getElementById('jumlahPenarikan').value = '';
    document.getElementById('warnSaldo').style.display = 'none';
    document.getElementById('errNasabah').style.display = 'none';
}


function onJumlahChange(inp) {
    const val  = parseFloat(inp.value || 0);
    const warn = document.getElementById('warnSaldo');
    const err  = document.getElementById('errJumlah');

    err.style.display  = 'none';
    warn.style.display = (currentSaldo > 0 && val > currentSaldo) ? 'block' : 'none';
}


function validate() {
    let ok = true;
    const idP    = document.getElementById('idPengguna').value;
    const jumlah = parseFloat(document.getElementById('jumlahPenarikan').value || 0);

    document.getElementById('errNasabah').style.display = idP    ? 'none' : 'block';
    document.getElementById('errJumlah').style.display  = jumlah > 0 ? 'none' : 'block';
    if (!idP || jumlah <= 0) ok = false;

    if (jumlah > currentSaldo) {
        document.getElementById('warnSaldo').style.display = 'block';
        ok = false;
    }
    return ok;
}


function simpanData() {
    if (!validate()) return;

    const sel    = document.getElementById('idPengguna');
    const opt    = sel.options[sel.selectedIndex];
    const jumlah = parseFloat(document.getElementById('jumlahPenarikan').value);

    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    fetch('penarikan_simpan.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({
            id_pengguna  : sel.value,
            nama_pengguna: opt.dataset.nama || '',
            jumlah       : jumlah,
            saldo_lama   : currentSaldo
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Penarikan berhasil disimpan!', 'success');
            setTimeout(() => window.location.href = 'penarikan_saldo.php', 900);
        } else {
            showToast(data.message || 'Gagal menyimpan data.', 'error');
            btn.disabled = false;
            btn.innerHTML = 'SIMPAN DATA';
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan server.', 'error');
        btn.disabled = false;
        btn.innerHTML = 'SIMPAN DATA';
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
</script>
</body>
</html>