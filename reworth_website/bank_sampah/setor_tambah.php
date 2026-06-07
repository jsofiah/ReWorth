<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? 'user@example.com';
$userFoto  = $_SESSION['foto_profil'] ?? '';

function getSupabaseImageUrl($p) {
    return empty($p) ? null : "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');
}
function sbGet($url,$key,$ep){
    $ch=curl_init($url.$ep);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey:$key","Authorization:Bearer $key","Content-Type:application/json"]]);
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return $c===200?(json_decode($r,true)?:[]):[];
}

$pengguna   = sbGet($supabaseUrl,$supabaseKey,"/rest/v1/pengguna?select=id_pengguna,nama_lengkap,alamat_detail&order=nama_lengkap.asc");
$jadwals    = sbGet($supabaseUrl,$supabaseKey,"/rest/v1/jadwal_ambil?select=id_jadwal,tanggal,waktu_mulai,waktu_selesai,kuota&order=tanggal.asc");
$jenisList  = sbGet($supabaseUrl,$supabaseKey,"/rest/v1/jenis_sampah?select=id_jenis,nama_sampah,harga_per_kg&order=nama_sampah.asc");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tambah Transaksi Setor Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    </head>
<body>

<!-- SIDEBAR -->
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
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Tambah Transaksi Setor Sampah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if(!empty($userFoto)): $fu=getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fu) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM -->
    <div class="form-wrap">
        <div class="form-section-inner">
            <div class="inside-header">
                <h2>Tambah Transaksi Setor Sampah</h2>
            </div>

            <!-- Row 1: Nama Penyetor + Jadwal -->
            <div class="row-2cols">
                <div>
                    <div class="form-label">NAMA PENYETOR</div>
                    <select id="idPengguna" class="field-underline" onchange="onPenggunaChange(this)">
                        <option value="">Nama Lengkap...</option>
                        <?php foreach($pengguna as $p): ?>
                        <option value="<?= htmlspecialchars($p['id_pengguna']) ?>"
                                data-alamat="<?= htmlspecialchars($p['alamat_detail'] ?? '') ?>">
                            <?= htmlspecialchars($p['nama_lengkap']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="errPengguna" class="err-msg" style="display:none;color:#D95D39;font-size:12px;margin-top:4px;">Nama penyetor wajib dipilih.</div>
                </div>
                <div>
                    <div class="form-label">JADWAL AMBIL</div>
                    <select id="idJadwal" class="jadwal-select">
                        <option value="">Pilih jadwal...</option>
                        <?php foreach($jadwals as $j): ?>
                        <option value="<?= htmlspecialchars($j['id_jadwal']) ?>">
                            <?= date('d M Y', strtotime($j['tanggal'])) ?>
                            · <?= substr($j['waktu_mulai'],0,5) ?> – <?= substr($j['waktu_selesai'],0,5) ?>
                            (<?= (int)$j['kuota'] ?> slot)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="errJadwal" class="err-msg" style="display:none;color:#D95D39;font-size:12px;margin-top:4px;">Jadwal wajib dipilih.</div>
                </div>
            </div>

            <!-- Row 2: Alamat Setor -->
            <div style="margin-bottom:26px;">
                <div class="form-label">ALAMAT SETOR</div>
                <input type="text" id="alamatSetor" class="field-underline"
                    placeholder="Jl.Mawar Melati No.04, Kauman...">
                <div id="errAlamat" class="err-msg" style="display:none;color:#D95D39;font-size:12px;margin-top:4px;">Alamat setor wajib diisi.</div>
            </div>

            <!-- Detail Setor -->
            <div class="section-label">
                <span>DETAIL SETOR SAMPAH</span>
                <span class="total-label">Total Uang: <span id="totalUang">Rp0</span></span>
            </div>

            <div class="detail-wrap">
                <div class="detail-head">
                    <span>Jenis Sampah</span>
                    <span>Berat (kg)</span>
                    <span>Harga / kg (Rp)</span>
                    <span>Subtotal</span>
                    <span></span>
                </div>
                <div class="detail-body" id="detailBody">
                    <!-- rows injected by JS -->
                </div>
            </div>

            <button type="button" class="btn-add-row" onclick="addRow()">
                <i class="bi bi-plus-lg"></i> Tambah Item
            </button>

            <!-- Actions -->
            <div class="form-actions">
                <button type="button" class="btn-batal" onclick="window.location.href='transaksi_setor_sampah.php'">
                    BATAL
                </button>
                <button type="button" id="btnSimpan" class="btn-simpan" onclick="simpanData()">
                    SIMPAN DATA
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script>
const JENIS_LIST = <?= json_encode($jenisList) ?>;

/* ── jenis map: id → {nama, harga} ── */
const jenisMap = {};
JENIS_LIST.forEach(j => {
    jenisMap[j.id_jenis] = { nama: j.nama_sampah, harga: parseFloat(j.harga_per_kg) };
});

/* ── auto-fill alamat from pengguna ── */
function onPenggunaChange(sel) {
    const opt    = sel.options[sel.selectedIndex];
    const alamat = opt.dataset.alamat || '';
    document.getElementById('alamatSetor').value = alamat;
}

/* ── add row ── */
let rowIdx = 0;
function addRow(jenisId='', berat='', hargaPerKg='', subtotal='') {
    rowIdx++;
    const ri = rowIdx;

    const div = document.createElement('div');
    div.className = 'detail-row';
    div.id = `row-${ri}`;

    // options HTML
    let opts = '<option value="">Pilih jenis sampah...</option>';
    JENIS_LIST.forEach(j => {
        const sel = j.id_jenis === jenisId ? ' selected' : '';
        opts += `<option value="${j.id_jenis}"${sel}>${j.nama_sampah} – Rp${fmt(j.harga_per_kg)}/kg</option>`;
    });

    const h  = hargaPerKg ? fmt(parseFloat(hargaPerKg)) : '0';
    const st = subtotal   ? fmt(parseFloat(subtotal))   : '0';
    const b  = berat      ? parseFloat(berat).toFixed(1) : '0,0';

    div.innerHTML = `
        <select class="cell-select" onchange="onJenisChange(this,${ri})">${opts}</select>
        <input  class="cell-input"  type="number" min="0" step="0.1"
                value="${berat || ''}" placeholder="0,0"
                onchange="onBeratChange(this,${ri})" oninput="onBeratChange(this,${ri})">
        <input  class="cell-input"  type="text" id="harga-${ri}"
                value="${h}" placeholder="0.000" readonly>
        <input  class="cell-input"  type="text" id="sub-${ri}"
                value="${st}" placeholder="0.000" readonly>
        <button type="button" class="btn-row-del" onclick="removeRow(${ri})">
            <i class="bi bi-trash3"></i>
        </button>`;

    document.getElementById('detailBody').appendChild(div);

    // if pre-filling, set harga immediately
    if (jenisId && jenisMap[jenisId]) {
        document.getElementById(`harga-${ri}`).value = fmt(jenisMap[jenisId].harga);
    }
}

function removeRow(ri) {
    const el = document.getElementById(`row-${ri}`);
    if (el) el.remove();
    calcTotal();
}

function onJenisChange(sel, ri) {
    const jId = sel.value;
    const hEl = document.getElementById(`harga-${ri}`);
    const sEl = document.getElementById(`sub-${ri}`);
    if (jId && jenisMap[jId]) {
        hEl.value = fmt(jenisMap[jId].harga);
        const bEl = sel.closest('.detail-row').querySelector('input[type="number"]');
        const sub = parseFloat(bEl.value || 0) * jenisMap[jId].harga;
        sEl.value = fmt(sub);
    } else {
        hEl.value = '0'; sEl.value = '0';
    }
    calcTotal();
}

function onBeratChange(inp, ri) {
    const row  = document.getElementById(`row-${ri}`);
    const sel  = row.querySelector('.cell-select');
    const jId  = sel.value;
    const hEl  = document.getElementById(`harga-${ri}`);
    const sEl  = document.getElementById(`sub-${ri}`);
    const berat= parseFloat(inp.value || 0);
    if (jId && jenisMap[jId]) {
        const sub = berat * jenisMap[jId].harga;
        hEl.value = fmt(jenisMap[jId].harga);
        sEl.value = fmt(sub);
    } else {
        sEl.value = '0';
    }
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('[id^="sub-"]').forEach(el => {
        total += unFmt(el.value);
    });
    document.getElementById('totalUang').textContent = 'Rp' + fmt(total);
}

function fmt(n)   { return parseFloat(n||0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:3}); }
function unFmt(s) { return parseFloat((s||'0').replace(/\./g,'').replace(',','.')) || 0; }

/* ── validate ── */
function validate() {
    let ok = true;
    const idP = document.getElementById('idPengguna').value;
    const idJ = document.getElementById('idJadwal').value;
    const al  = document.getElementById('alamatSetor').value.trim();

    document.getElementById('errPengguna').style.display = idP ? 'none' : 'block';
    document.getElementById('errJadwal').style.display   = idJ ? 'none' : 'block';
    document.getElementById('errAlamat').style.display   = al  ? 'none' : 'block';
    if (!idP || !idJ || !al) ok = false;

    const rows = document.querySelectorAll('.detail-row');
    if (!rows.length) { showToast('Tambahkan minimal 1 item sampah.','error'); ok = false; }

    return ok;
}

/* ── collect detail rows ── */
function collectDetails() {
    const rows = document.querySelectorAll('.detail-row');
    const details = [];
    rows.forEach((row, i) => {
        const jEl  = row.querySelector('.cell-select');
        const bEl  = row.querySelector('input[type="number"]');
        const hEl  = row.querySelectorAll('input[type="text"]')[0];
        const sEl  = row.querySelectorAll('input[type="text"]')[1];
        if (!jEl.value) return;
        details.push({
            id_jenis    : jEl.value,
            berat       : parseFloat(bEl.value || 0),
            harga_per_kg: unFmt(hEl.value),
            subtotal    : unFmt(sEl.value)
        });
    });
    return details;
}

/* ── SIMPAN ── */
function simpanData() {
    if (!validate()) return;

    const details   = collectDetails();
    if (!details.length) { showToast('Pilih jenis sampah pada setiap baris.','error'); return; }

    const totalUang = details.reduce((s,d) => s + d.subtotal, 0);
    const payload   = {
        id_pengguna : document.getElementById('idPengguna').value,
        id_jadwal   : document.getElementById('idJadwal').value,
        alamat      : document.getElementById('alamatSetor').value.trim(),
        total_uang  : totalUang,
        details
    };

    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    fetch('setor_simpan.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Transaksi berhasil disimpan!', 'success');
            setTimeout(() => window.location.href = 'transaksi_setor_sampah.php', 900);
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

function showToast(msg, type='success') {
    const icons = {success:'bi-check-circle-fill', error:'bi-x-circle-fill'};
    const div   = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}

// init 1 baris kosong
addRow();
</script>
</body>
</html>