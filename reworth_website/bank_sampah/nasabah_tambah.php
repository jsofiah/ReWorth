<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$userName = $_SESSION['nama_admin'] ?? 'User';
$userEmail= $_SESSION['email']      ?? '';
$userRole = $_SESSION['role']       ?? '';
$userFoto = $_SESSION['foto_profil']?? '';
$idAdmin  = $_SESSION['id_admin']   ?? '';

function getSupabaseImageUrl($p){return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');}
function sbGet($url,$key,$ep){$ch=curl_init($url.$ep);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey:$key","Authorization:Bearer $key","Content-Type:application/json"]]);$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $c===200?(json_decode($r,true)?:[]):[];}

// Ambil semua wilayah untuk dropdown
$wilayahList = sbGet($supabaseUrl, $supabaseKey, "/rest/v1/wilayah?select=id_wilayah,rw,kelurahan,kecamatan&order=kecamatan.asc");
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tambah Nasabah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
<style>
.form-wrap{display:flex;justify-content:center;padding:0 40px 40px;}
.form-card{background:#fff;border-radius:24px;padding:0 0 36px;box-shadow:0 4px 24px rgba(0,0,0,.07);position:relative;margin-top:-60px;z-index:10;width:100%;max-width:900px;overflow:hidden;}
.card-header-orange{background:#ED985A;padding:22px 40px;text-align:center;margin-bottom:32px;}
.card-header-orange h2{margin:0;color:#fff;font-size:28px;font-weight:700;}
.fields-wrap{padding:0 40px;}
.field-label{display:block;font-size:12px;font-weight:700;color:#2C3E2F;letter-spacing:.5px;text-transform:uppercase;margin-bottom:8px;}
.field-ul{width:100%;border:none;border-bottom:1.5px solid #D6DEDA;background:transparent;padding:4px 2px 10px;font-size:14px;font-family:inherit;color:#555;outline:none;transition:.2s;appearance:none;}
.field-ul:focus{border-bottom-color:var(--green);}
.field-ul::placeholder{color:#B0BFB8;}
.field-err{display:none;font-size:11px;color:#D95D39;margin-top:3px;font-weight:500;}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-bottom:24px;}
.row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:24px;}
.row-1{margin-bottom:24px;}
.form-actions{display:flex;justify-content:center;gap:14px;padding:24px 40px 0;border-top:1.5px solid #E8F0EC;margin-top:8px;}
.btn-batal{padding:12px 36px;border-radius:12px;border:1.5px solid #D2E0D8;background:#fff;font-size:13px;font-weight:700;letter-spacing:.6px;color:#6B8A7E;cursor:pointer;font-family:inherit;transition:.2s;}
.btn-batal:hover{border-color:var(--green);color:var(--green);}
.btn-simpan{padding:12px 32px;border-radius:12px;border:none;background:var(--green);color:#fff;font-size:13px;font-weight:700;letter-spacing:.5px;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(0,145,110,.3);transition:.2s;}
.btn-simpan:disabled{opacity:.6;pointer-events:none;}
.file-btn{display:inline-block;padding:6px 14px;background:#EEF5F1;border-radius:8px;font-size:12px;font-weight:600;color:#6B8A7E;cursor:pointer;border:1.5px solid #D6DEDA;transition:.2s;margin-right:8px;}
.file-btn:hover{background:#D6EDE4;color:var(--green);}
.file-name{font-size:12px;color:#9AA7A2;}
</style>
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
        <div class="nav-item"><a href="laporan_keuangan.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
        <div class="nav-item"><a href="data_nasabah.php" class="nav-link-custom active"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
        <div class="nav-item"><a href="data_sampah.php" class="nav-link-custom"><i class="bi bi-trash-fill"></i><span>Data Sampah</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
</aside>

<div class="main-wrap">
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Data Nasabah</h1>
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
        <div class="form-card">
            <div class="card-header-orange"><h2>Tambah Data Nasabah</h2></div>
            <div class="fields-wrap">

                <!-- Baris 1: Nama & Email -->
                <div class="row-2">
                    <div>
                        <label class="field-label">Nama Lengkap</label>
                        <input id="namaLengkap" type="text" class="field-ul" placeholder="Masukkan nama">
                        <span class="field-err" id="errNama">Nama wajib diisi</span>
                    </div>
                    <div>
                        <label class="field-label">Email</label>
                        <input id="email" type="email" class="field-ul" placeholder="1264323@gmail.com">
                        <span class="field-err" id="errEmail">Email wajib diisi</span>
                    </div>
                </div>

                <!-- Baris 2: No Telp & Password -->
                <div class="row-2">
                    <div>
                        <label class="field-label">Nomer Telp</label>
                        <input id="noTelp" type="text" class="field-ul" placeholder="629988776655443">
                    </div>
                    <div>
                        <label class="field-label">Password</label>
                        <input id="password" type="password" class="field-ul" placeholder="Password">
                        <span class="field-err" id="errPassword">Password wajib diisi</span>
                    </div>
                </div>

                <!-- Baris 3: Alamat (full width) -->
                <div class="row-1">
                    <label class="field-label">Alamat Tempat Tinggal</label>
                    <input id="alamat" type="text" class="field-ul" placeholder="Nama jalan, kota, dan kode pos...">
                </div>

                <!-- Baris 4: Kecamatan, Kelurahan, RW -->
                <div class="row-3">
                    <div>
                        <label class="field-label">Kecamatan</label>
                        <select id="kecamatan" class="field-ul" onchange="onKecChange()">
                            <option value="">-- Pilih Kecamatan --</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Kelurahan</label>
                        <select id="kelurahan" class="field-ul" onchange="onKelChange()">
                            <option value="">-- Pilih Kelurahan --</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">RW</label>
                        <select id="rw" class="field-ul" onchange="onRwChange()">
                            <option value="">-- Pilih RW --</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" id="idWilayah" value="">

                <!-- Baris 5: Foto, Saldo, Poin -->
                <div class="row-3">
                    <div>
                        <label class="field-label">Foto Profil</label>
                        <div style="padding-bottom:10px;border-bottom:1.5px solid #D6DEDA;">
                            <label for="fotoProfil" class="file-btn">Pilih file</label>
                            <span class="file-name" id="fileNameDisplay">No file chosen</span>
                            <input type="file" id="fotoProfil" accept="image/*" style="display:none;"
                                   onchange="document.getElementById('fileNameDisplay').textContent=this.files[0]?.name||'No file chosen'">
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Saldo Tabungan</label>
                        <input id="saldoTabungan" type="number" class="field-ul" placeholder="Masukkan saldo tabungan" value="0" min="0">
                    </div>
                    <div>
                        <label class="field-label">Poin</label>
                        <input id="poin" type="number" class="field-ul" placeholder="Masukkan poin" value="0" min="0">
                    </div>
                </div>

            </div><!-- /fields-wrap -->

            <div class="form-actions">
                <button type="button" class="btn-batal" onclick="window.location.href='data_nasabah.php'">BATAL</button>
                <button type="button" id="btnSimpan" class="btn-simpan" onclick="simpanData()">SIMPAN DATA NASABAH</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const WILAYAH = <?=json_encode($wilayahList)?>;
const ADMIN_ID = <?=json_encode($idAdmin)?>;

// Build kecamatan dropdown
const kecSet = [...new Set(WILAYAH.map(w => w.kecamatan))].sort();
const kecEl  = document.getElementById('kecamatan');
kecSet.forEach(k => {
    const o = document.createElement('option');
    o.value = k; o.textContent = k;
    kecEl.appendChild(o);
});

function buildKelurahan(kec) {
    const kelEl = document.getElementById('kelurahan');
    kelEl.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
    document.getElementById('rw').innerHTML = '<option value="">-- Pilih RW --</option>';
    document.getElementById('idWilayah').value = '';
    const kelSet = [...new Set(WILAYAH.filter(w => w.kecamatan === kec).map(w => w.kelurahan))].sort();
    kelSet.forEach(k => {
        const o = document.createElement('option');
        o.value = k; o.textContent = k;
        kelEl.appendChild(o);
    });
}
function buildRw(kec, kel) {
    const rwEl = document.getElementById('rw');
    rwEl.innerHTML = '<option value="">-- Pilih RW --</option>';
    document.getElementById('idWilayah').value = '';
    WILAYAH.filter(w => w.kecamatan === kec && w.kelurahan === kel)
           .sort((a,b) => a.rw - b.rw)
           .forEach(w => {
               const o = document.createElement('option');
               o.value = w.id_wilayah; o.textContent = 'RW ' + w.rw;
               rwEl.appendChild(o);
           });
}

function onKecChange() {
    const kec = document.getElementById('kecamatan').value;
    if (kec) buildKelurahan(kec); else {
        document.getElementById('kelurahan').innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        document.getElementById('rw').innerHTML = '<option value="">-- Pilih RW --</option>';
        document.getElementById('idWilayah').value = '';
    }
}
function onKelChange() {
    const kec = document.getElementById('kecamatan').value;
    const kel = document.getElementById('kelurahan').value;
    if (kel) buildRw(kec, kel); else {
        document.getElementById('rw').innerHTML = '<option value="">-- Pilih RW --</option>';
        document.getElementById('idWilayah').value = '';
    }
}
function onRwChange() {
    document.getElementById('idWilayah').value = document.getElementById('rw').value;
}

function validate() {
    let ok = true;
    const req = (id, errId) => {
        const v = document.getElementById(id).value.trim();
        document.getElementById(errId).style.display = v ? 'none' : 'block';
        if (!v) ok = false;
    };
    req('namaLengkap', 'errNama');
    req('email',       'errEmail');
    req('password',    'errPassword');
    return ok;
}

async function simpanData() {
    if (!validate()) return;
    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    const fd = new FormData();
    fd.append('id_admin',      ADMIN_ID);
    fd.append('nama_lengkap',  document.getElementById('namaLengkap').value.trim());
    fd.append('email',         document.getElementById('email').value.trim());
    fd.append('no_telepon',    document.getElementById('noTelp').value.trim());
    fd.append('password',      document.getElementById('password').value);
    fd.append('alamat_detail', document.getElementById('alamat').value.trim());
    fd.append('id_wilayah',    document.getElementById('idWilayah').value);
    fd.append('saldo_tabungan',document.getElementById('saldoTabungan').value || 0);
    fd.append('poin',          document.getElementById('poin').value || 0);
    const foto = document.getElementById('fotoProfil').files[0];
    if (foto) fd.append('foto_profil', foto);

    fetch('nasabah_simpan.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Nasabah berhasil ditambahkan!', 'success');
                setTimeout(() => window.location.href = 'data_nasabah.php', 900);
            } else {
                showToast(data.message || 'Gagal menyimpan.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'SIMPAN DATA NASABAH';
            }
        })
        .catch(() => {
            showToast('Kesalahan server.', 'error');
            btn.disabled = false;
            btn.innerHTML = 'SIMPAN DATA NASABAH';
        });
}

function showToast(msg, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
    const div = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}
</script>
</body>
</html>