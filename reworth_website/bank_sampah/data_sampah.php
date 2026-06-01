<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$userName = $_SESSION['nama_admin'] ?? 'User';
$userEmail= $_SESSION['email']      ?? '';
$userRole = $_SESSION['role']       ?? '';
$userFoto = $_SESSION['foto_profil']?? '';

function getSupabaseImageUrl($p){return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');}
function sbGet($url,$key,$ep){$ch=curl_init($url.$ep);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey:$key","Authorization:Bearer $key","Content-Type:application/json"]]);$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $c===200?(json_decode($r,true)?:[]):[];}
function fmtRp($n){return 'Rp '.number_format((float)$n,0,',','.');}

$sampahList = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/jenis_sampah?select=id_jenis,nama_sampah,harga_per_kg,created_at&order=nama_sampah.asc");

$per_page    = 10;
$total       = count($sampahList);
$total_pages = max(1, ceil($total / $per_page));
$cur_page    = max(1, (int)($_GET['page'] ?? 1));
$start       = ($cur_page - 1) * $per_page;
$cur_data    = array_slice($sampahList, $start, $per_page);
$show_from   = $total > 0 ? $start + 1 : 0;
$show_to     = min($start + $per_page, $total);
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bank Sampah – Data Sampah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
<style>
.harga-val { color: var(--green); font-weight: 700; }
.aksi-wrap { display: flex; gap: 6px; flex-wrap: wrap; }

/* Modal form */
.modal-form-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 1000;
    align-items: center; justify-content: center;
}
.modal-form-overlay.show { display: flex; }
.modal-form-box {
    background: #fff; border-radius: 24px;
    padding: 0; width: 100%; max-width: 480px;
    box-shadow: 0 8px 40px rgba(0,0,0,.18); overflow: hidden;
}
.modal-form-header {
    background: #ED985A; padding: 20px 28px;
    text-align: center;
}
.modal-form-header h3 { margin: 0; color: #fff; font-size: 22px; font-weight: 700; }
.modal-form-body { padding: 28px; }
.field-label { display: block; font-size: 11px; font-weight: 700; color: #2C3E2F;
    letter-spacing: .5px; text-transform: uppercase; margin-bottom: 8px; }
.field-ul { width: 100%; border: none; border-bottom: 1.5px solid #D6DEDA;
    background: transparent; padding: 4px 2px 10px; font-size: 14px;
    font-family: inherit; color: #555; outline: none; transition: .2s; }
.field-ul:focus { border-bottom-color: var(--green); }
.field-ul::placeholder { color: #B0BFB8; }
.field-err { display: none; font-size: 11px; color: #D95D39; margin-top: 3px; font-weight: 500; }
.field-group { margin-bottom: 20px; }
.modal-form-actions {
    display: flex; justify-content: center; gap: 12px;
    padding: 16px 28px; border-top: 1.5px solid #E8F0EC;
}
.btn-batal-modal {
    padding: 10px 28px; border-radius: 12px; border: 1.5px solid #D2E0D8;
    background: #fff; font-size: 13px; font-weight: 700; color: #6B8A7E;
    cursor: pointer; font-family: inherit; transition: .2s;
}
.btn-batal-modal:hover { border-color: var(--green); color: var(--green); }
.btn-simpan-modal {
    padding: 10px 28px; border-radius: 12px; border: none;
    background: var(--green); color: #fff; font-size: 13px; font-weight: 700;
    cursor: pointer; font-family: inherit;
    box-shadow: 0 4px 14px rgba(0,145,110,.3); transition: .2s;
}
.btn-simpan-modal:disabled { opacity: .6; pointer-events: none; }
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
        <div class="nav-item"><a href="data_nasabah.php" class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
        <div class="nav-item"><a href="data_sampah.php" class="nav-link-custom active"><i class="bi bi-trash-fill"></i><span>Data Sampah</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
</aside>

<div class="main-wrap">
    <!-- Topbar -->
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

    <!-- Action Bar -->
    <div class="action-bar-wrap">
        <div class="action-bar">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="search-input" placeholder="Cari jenis sampah..." id="searchInput">
            </div>
            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()"><i class="bi bi-sliders2"></i> Filter</button>
                <div class="filter-box" id="filterBox">
                    <div class="filter-group">
                        <label>Urutkan</label>
                        <select id="sortOrder">
                            <option value="nama_asc">Nama A–Z</option>
                            <option value="nama_desc">Nama Z–A</option>
                            <option value="harga_desc">Harga Tertinggi</option>
                            <option value="harga_asc">Harga Terendah</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" onclick="resetFilter()">Reset</button>
                        <button type="button" onclick="applyFilter()">Terapkan</button>
                    </div>
                </div>
            </div>
            <?php if(in_array($userRole, ['bank sampah','admin','dlh'])):?>
            <button class="btn-tambah"
                    onclick="window.location.href='sampah_tambah.php'">
                <i class="bi bi-plus-lg"></i> Tambah Jenis Sampah
            </button>
            <?php endif;?>
        </div>
    </div>

    <!-- Table -->
    <div class="content-area">
        <div class="card-custom">
            <div class="table-scroll-wrapper">
                <table class="responsive-table">
                    <colgroup>
                        <col style="width:50px;">
                        <col style="width:240px;">
                        <col style="width:160px;">
                        <col style="width:160px;">
                        <col style="width:<?=in_array($userRole,['bank sampah','admin','dlh'])?'200px':'100px'?>;">
                    </colgroup>
                    <thead><tr>
                        <th>No</th>
                        <th>Nama Jenis Sampah</th>
                        <th>Harga per Kg</th>
                        <th>Ditambahkan</th>
                        <th>Aksi</th>
                    </tr></thead>
                    <tbody id="tableBody">
                    <?php if(!empty($cur_data)):?>
                        <?php foreach($cur_data as $i => $s):?>
                        <tr data-nama="<?=strtolower(htmlspecialchars($s['nama_sampah']??''))?>"
                            data-harga="<?=(float)($s['harga_per_kg']??0)?>">
                            <td class="td-no"><?=$start+$i+1?></td>
                            <td class="td-nama"><?=htmlspecialchars($s['nama_sampah']??'-')?></td>
                            <td class="harga-val"><?=fmtRp($s['harga_per_kg']??0)?></td>
                            <td style="font-size:12px;color:#6B8A7E;">
                                <?=!empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-'?>
                            </td>
                            <td>
                                <div class="aksi-wrap">
                                    <button class="btn-aksi btn-lihat"
                                            onclick="window.location.href='sampah_lihat.php?id=<?=$s['id_jenis']?>'">
                                        <i class="bi bi-file-earmark-text"></i> Lihat
                                    </button>
                                    <?php if(in_array($userRole,['bank sampah','admin','dlh'])):?>
                                    <button class="btn-aksi btn-edit"
                                            onclick="window.location.href='sampah_edit.php?id=<?=$s['id_jenis']?>'">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn-aksi btn-hapus"
                                            onclick="hapusSampah('<?=$s['id_jenis']?>','<?=htmlspecialchars($s['nama_sampah'])?>')">
                                        <i class="bi bi-trash3"></i> Hapus
                                    </button>
                                    <?php endif;?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    <?php else:?>
                        <tr><td colspan="5" class="text-center py-4" style="color:#6B8A7E;">
                            <i class="bi bi-trash" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                            Belum ada data jenis sampah
                        </td></tr>
                    <?php endif;?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="table-footer">
                <div class="showing-text">Showing <b><?=$show_from?></b> to <b><?=$show_to?></b> of <b><?=$total?></b> entries</div>
                <div class="pagination-custom">
                    <?php if($cur_page>1):?><a href="?page=<?=$cur_page-1?>" class="page-btn page-btn-text">Prev</a><?php endif;?>
                    <?php for($p=1;$p<=$total_pages;$p++):?><a href="?page=<?=$p?>" class="page-btn <?=$p==$cur_page?'active':''?>"><?=$p?></a><?php endfor;?>
                    <?php if($cur_page<$total_pages):?><a href="?page=<?=$cur_page+1?>" class="page-btn page-btn-text">Next</a><?php endif;?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Tambah ── -->
<div class="modal-form-overlay" id="modalTambah">
    <div class="modal-form-box">
        <div class="modal-form-header"><h3>Tambah Jenis Sampah</h3></div>
        <div class="modal-form-body">
            <div class="field-group">
                <label class="field-label">Nama Jenis Sampah</label>
                <input id="tambahNama" type="text" class="field-ul" placeholder="Contoh: Plastik, Kertas, Besi...">
                <span class="field-err" id="errTambahNama">Nama wajib diisi</span>
            </div>
            <div class="field-group">
                <label class="field-label">Harga per Kg (Rp)</label>
                <input id="tambahHarga" type="number" class="field-ul" placeholder="Contoh: 2000" min="0">
                <span class="field-err" id="errTambahHarga">Harga wajib diisi</span>
            </div>
        </div>
        <div class="modal-form-actions">
            <button class="btn-batal-modal" onclick="closeModal('modalTambah')">BATAL</button>
            <button class="btn-simpan-modal" id="btnTambahSimpan" onclick="simpanTambah()">SIMPAN</button>
        </div>
    </div>
</div>

<!-- ── Modal Edit ── -->
<div class="modal-form-overlay" id="modalEdit">
    <div class="modal-form-box">
        <div class="modal-form-header"><h3>Edit Jenis Sampah</h3></div>
        <div class="modal-form-body">
            <input type="hidden" id="editId">
            <div class="field-group">
                <label class="field-label">Nama Jenis Sampah</label>
                <input id="editNama" type="text" class="field-ul" placeholder="Nama jenis sampah">
                <span class="field-err" id="errEditNama">Nama wajib diisi</span>
            </div>
            <div class="field-group">
                <label class="field-label">Harga per Kg (Rp)</label>
                <input id="editHarga" type="number" class="field-ul" placeholder="Harga per kg" min="0">
                <span class="field-err" id="errEditHarga">Harga wajib diisi</span>
            </div>
        </div>
        <div class="modal-form-actions">
            <button class="btn-batal-modal" onclick="closeModal('modalEdit')">BATAL</button>
            <button class="btn-simpan-modal" id="btnEditSimpan" onclick="simpanEdit()">SIMPAN PERUBAHAN</button>
        </div>
    </div>
</div>

<!-- ── Modal Hapus ── -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
        <div class="confirm-text">
            <h3>Hapus Jenis Sampah?</h3>
            <p id="hapusNamaText">Data akan dihapus secara permanen.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
            <button id="btnConfirmHapus" class="btn-aksi btn-hapus" style="padding:10px 22px;font-size:14px;border-radius:12px;" onclick="confirmHapus()">
                <i class="bi bi-trash3"></i> Ya, Hapus
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SB_URL = '<?=$supabaseUrl?>';
const SB_KEY = '<?=$supabaseKey?>';

/* ── Search ── */
document.getElementById('searchInput').addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tableBody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    rebuildNo();
});

/* ── Filter ── */
function toggleFilter(){ document.getElementById('filterBox').classList.toggle('show'); }
function applyFilter(){
    const sort  = document.getElementById('sortOrder').value;
    const tbody = document.querySelector('#tableBody');
    let rows    = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a,b) => {
        if(sort==='nama_asc')   return a.dataset.nama.localeCompare(b.dataset.nama);
        if(sort==='nama_desc')  return b.dataset.nama.localeCompare(a.dataset.nama);
        if(sort==='harga_desc') return parseFloat(b.dataset.harga)-parseFloat(a.dataset.harga);
        if(sort==='harga_asc')  return parseFloat(a.dataset.harga)-parseFloat(b.dataset.harga);
        return 0;
    });
    rows.forEach(r => tbody.appendChild(r));
    rebuildNo();
    document.getElementById('filterBox').classList.remove('show');
}
function resetFilter(){ document.getElementById('sortOrder').value='nama_asc'; applyFilter(); }
function rebuildNo(){ let no=1; document.querySelectorAll('#tableBody tr').forEach(r=>{ if(r.style.display!=='none'&&r.children[0]) r.children[0].textContent=no++; }); }

/* ── Modal helpers ── */
function openModal(id){ document.getElementById(id).classList.add('show'); }
function closeModal(id){ document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-form-overlay,.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); });
});

/* ── Tambah ── */
function openTambah(){
    document.getElementById('tambahNama').value = '';
    document.getElementById('tambahHarga').value = '';
    document.getElementById('errTambahNama').style.display = 'none';
    document.getElementById('errTambahHarga').style.display = 'none';
    openModal('modalTambah');
}
async function simpanTambah(){
    const nama  = document.getElementById('tambahNama').value.trim();
    const harga = document.getElementById('tambahHarga').value;
    let ok = true;
    document.getElementById('errTambahNama').style.display  = nama  ? 'none' : 'block'; if(!nama)  ok=false;
    document.getElementById('errTambahHarga').style.display = harga ? 'none' : 'block'; if(!harga) ok=false;
    if(!ok) return;

    const btn = document.getElementById('btnTambahSimpan');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    const res = await fetch(SB_URL+'/rest/v1/jenis_sampah', {
        method: 'POST',
        headers: {
            'apikey': SB_KEY, 'Authorization': 'Bearer '+SB_KEY,
            'Content-Type': 'application/json', 'Prefer': 'return=representation'
        },
        body: JSON.stringify({ nama_sampah: nama, harga_per_kg: parseFloat(harga) })
    });

    if(res.ok){
        showToast('Jenis sampah berhasil ditambahkan!', 'success');
        closeModal('modalTambah');
        setTimeout(() => location.reload(), 800);
    } else {
        const err = await res.json().catch(()=>({}));
        showToast(err.message || 'Gagal menambahkan.', 'error');
        btn.disabled = false; btn.innerHTML = 'SIMPAN';
    }
}

/* ── Edit ── */
function openEdit(id, nama, harga){
    document.getElementById('editId').value    = id;
    document.getElementById('editNama').value  = nama;
    document.getElementById('editHarga').value = harga;
    document.getElementById('errEditNama').style.display  = 'none';
    document.getElementById('errEditHarga').style.display = 'none';
    openModal('modalEdit');
}
async function simpanEdit(){
    const id    = document.getElementById('editId').value;
    const nama  = document.getElementById('editNama').value.trim();
    const harga = document.getElementById('editHarga').value;
    let ok = true;
    document.getElementById('errEditNama').style.display  = nama  ? 'none' : 'block'; if(!nama)  ok=false;
    document.getElementById('errEditHarga').style.display = harga ? 'none' : 'block'; if(!harga) ok=false;
    if(!ok) return;

    const btn = document.getElementById('btnEditSimpan');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

    const res = await fetch(SB_URL+'/rest/v1/jenis_sampah?id_jenis=eq.'+id, {
        method: 'PATCH',
        headers: {
            'apikey': SB_KEY, 'Authorization': 'Bearer '+SB_KEY,
            'Content-Type': 'application/json', 'Prefer': 'return=representation'
        },
        body: JSON.stringify({ nama_sampah: nama, harga_per_kg: parseFloat(harga) })
    });

    if(res.ok){
        showToast('Data berhasil diperbarui!', 'success');
        closeModal('modalEdit');
        setTimeout(() => location.reload(), 800);
    } else {
        const err = await res.json().catch(()=>({}));
        showToast(err.message || 'Gagal memperbarui.', 'error');
        btn.disabled = false; btn.innerHTML = 'SIMPAN PERUBAHAN';
    }
}

/* ── Hapus ── */
let deletingId = null;
function hapusSampah(id, nama){
    deletingId = id;
    document.getElementById('hapusNamaText').textContent = `"${nama}" akan dihapus secara permanen.`;
    openModal('modalHapus');
}
async function confirmHapus(){
    if(!deletingId) return;
    const btn = document.getElementById('btnConfirmHapus');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    const res = await fetch(SB_URL+'/rest/v1/jenis_sampah?id_jenis=eq.'+deletingId, {
        method: 'DELETE',
        headers: { 'apikey': SB_KEY, 'Authorization': 'Bearer '+SB_KEY }
    });

    closeModal('modalHapus');
    if(res.ok){
        showToast('Jenis sampah berhasil dihapus.', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast('Gagal menghapus. Mungkin data masih digunakan.', 'error');
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-trash3"></i> Ya, Hapus';
    }
}

/* ── Toast ── */
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