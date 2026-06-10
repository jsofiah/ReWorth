<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$userName = $_SESSION['nama_admin'] ?? 'User';
$userEmail= $_SESSION['email']      ?? '';
$userRole = $_SESSION['role']       ?? '';
$userFoto = $_SESSION['foto_profil']?? '';

function getSupabaseImageUrl($p){return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');}
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tambah Jenis Sampah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head><body>
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
                    <div class="topbar-user-name"><?=htmlspecialchars($userName)?></div>
                    <div class="topbar-user-email"><?=htmlspecialchars($userEmail)?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if(!empty($userFoto)):$fu=getSupabaseImageUrl($userFoto);?>
                        <img src="<?=htmlspecialchars($fu)?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else:?><i class="bi bi-person-fill"></i><?php endif;?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-wrap">
        <div class="form-card form-card-padded">
            <div class="card-header-orange card-header-rounded"><h2>Tambah Jenis Sampah</h2></div>
            <div class="fields-wrap" style="padding:0;">

                <div class="hint-box">
                    <i class="bi bi-info-circle-fill"></i>
                    <p>Tambahkan jenis sampah baru beserta harga per kilogramnya. Data ini akan digunakan saat nasabah melakukan setor sampah.</p>
                </div>

                
                <div class="row-2">
                    <div>
                        <label class="field-label">Nama Jenis Sampah</label>
                        <input id="namaSampah" type="text" class="field-ul" placeholder="Contoh: Plastik, Kertas, Besi...">
                        <span class="field-err" id="errNama">Nama jenis sampah wajib diisi</span>
                    </div>
                    <div>
                        <label class="field-label">Harga per Kg (Rp)</label>
                        <input id="hargaPerKg" type="number" class="field-ul" placeholder="Contoh: 2000" min="0" oninput="previewHarga()">
                        <span class="field-err" id="errHarga">Harga wajib diisi</span>
                        <div class="harga-preview" id="hargaPreview"></div>
                    </div>
                </div>

            </div>

            <div class="form-actions">
                <button type="button" class="btn-batal" onclick="window.location.href='data_sampah.php'">BATAL</button>
                <button type="button" id="btnSimpan" class="btn-simpan" onclick="simpanData()">SIMPAN JENIS SAMPAH</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SB_URL = '<?=$supabaseUrl?>';
const SB_KEY = '<?=$supabaseKey?>';

function previewHarga(){
    const v = parseFloat(document.getElementById('hargaPerKg').value);
    const el = document.getElementById('hargaPreview');
    el.textContent = !isNaN(v) && v > 0
        ? 'Rp ' + v.toLocaleString('id-ID') + ' / kg'
        : '';
}

function validate(){
    let ok = true;
    const nama  = document.getElementById('namaSampah').value.trim();
    const harga = document.getElementById('hargaPerKg').value;
    document.getElementById('errNama').style.display  = nama  ? 'none' : 'block'; if(!nama)  ok=false;
    document.getElementById('errHarga').style.display = harga ? 'none' : 'block'; if(!harga) ok=false;
    return ok;
}

async function simpanData(){
    if(!validate()) return;
    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    const payload = {
        nama_sampah  : document.getElementById('namaSampah').value.trim(),
        harga_per_kg : parseFloat(document.getElementById('hargaPerKg').value)
    };

    const res = await fetch(SB_URL + '/rest/v1/jenis_sampah', {
        method : 'POST',
        headers: {
            'apikey'       : SB_KEY,
            'Authorization': 'Bearer ' + SB_KEY,
            'Content-Type' : 'application/json',
            'Prefer'       : 'return=representation'
        },
        body: JSON.stringify(payload)
    });

    if(res.ok){
        const inserted = await res.json();
        const NEW_ID = inserted[0]?.id_jenis || null;
        const ADMIN_ID = "<?= $_SESSION['id_admin'] ?? '' ?>";
        if (ADMIN_ID) fetch(SB_URL + '/rest/v1/log_admin', { method: 'POST', headers: { 'apikey': SB_KEY, 'Authorization': 'Bearer ' + SB_KEY, 'Content-Type': 'application/json' }, body: JSON.stringify({ id_admin: ADMIN_ID, aktivitas: "Menambahkan jenis sampah baru", tabel_terkait: 'jenis_sampah', id_data: NEW_ID, created_at: new Date().toISOString().split('.')[0] + 'Z' }) });
    
        showToast('Jenis sampah berhasil ditambahkan!', 'success');
        setTimeout(() => window.location.href = 'data_sampah.php', 900);
    } else {
        const err = await res.json().catch(()=>({}));
        showToast(err.message || 'Gagal menyimpan.', 'error');
        btn.disabled = false;
        btn.innerHTML = 'SIMPAN JENIS SAMPAH';
    }
}

function showToast(msg, type='success'){
    const icons = { success:'bi-check-circle-fill', error:'bi-x-circle-fill' };
    const div = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}
</script>
</body>
</html>