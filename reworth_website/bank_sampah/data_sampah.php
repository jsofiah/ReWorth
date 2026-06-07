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
function sbGet($url,$key,$ep){$ch=curl_init($url.$ep);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey:$key","Authorization:Bearer $key","Content-Type:application/json"]]);$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $c===200?(json_decode($r,true)?:[]):[]; }
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
$canEdit     = in_array($userRole, ['bank sampah','admin','dlh']);
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bank Sampah – Data Sampah</title>
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
        <div class="nav-item"><a href="jadwal_ambil_sampah.php" class="nav-link-custom"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
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
            <?php if($canEdit):?>
            <button class="btn-tambah" onclick="window.location.href='sampah_tambah.php'">
                <i class="bi bi-plus-lg"></i> Tambah Jenis Sampah
            </button>
            <?php endif;?>
        </div>
    </div>

    <div class="content-area">
        <div class="card-custom">
            <div class="table-scroll-wrapper">
                <table class="responsive-table">
                    <colgroup>
                        <col style="width:50px;">
                        <col style="width:240px;">
                        <col style="width:160px;">
                        <col style="width:160px;">
                        <col style="width:<?=$canEdit?'200px':'100px'?>;">
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
                        <tr>
                            <td><?=$start+$i+1?></td>
                            <td><?=htmlspecialchars($s['nama_sampah']??'-')?></td>
                            <td class="harga-val"><?=fmtRp($s['harga_per_kg']??0)?></td>
                            <td style="font-size:12px;color:#6B8A7E;">
                                <?=!empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-'?>
                            </td>
                            <td>
                                <div class="aksi-wrap">
                                    <button class="btn-aksi btn-lihat" onclick="window.location.href='sampah_lihat.php?id=<?=$s['id_jenis']?>'">
                                        <i class="bi bi-file-earmark-text"></i> Lihat
                                    </button>
                                    <?php if($canEdit):?>
                                    <button class="btn-aksi btn-edit" onclick="window.location.href='sampah_edit.php?id=<?=$s['id_jenis']?>'">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn-aksi btn-hapus" onclick="hapusSampah('<?=$s['id_jenis']?>','<?=htmlspecialchars($s['nama_sampah']??'', ENT_QUOTES)?>')">
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

<!-- Modal Hapus -->
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
const SB_URL  = '<?=$supabaseUrl?>';
const SB_KEY  = '<?=$supabaseKey?>';
const canEdit = <?=$canEdit?'true':'false'?>;

// Semua data untuk live search
const allSampahData = <?= json_encode(array_map(function($s) {
    return [
        'id'      => $s['id_jenis'] ?? '',
        'nama'    => $s['nama_sampah'] ?? '-',
        'harga'   => (float)($s['harga_per_kg'] ?? 0),
        'tanggal' => !empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-',
    ];
}, $sampahList)) ?>;

function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtRp(n){ return 'Rp ' + Number(n).toLocaleString('id-ID'); }

function renderTable(data) {
    const tbody = document.getElementById('tableBody');
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4" style="color:#6B8A7E;">
            <i class="bi bi-trash" style="font-size:32px;display:block;margin-bottom:8px;"></i>
            Tidak ada data ditemukan</td></tr>`;
        return;
    }
    tbody.innerHTML = data.map((s, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${escHtml(s.nama)}</td>
            <td class="harga-val">${fmtRp(s.harga)}</td>
            <td style="font-size:12px;color:#6B8A7E;">${escHtml(s.tanggal)}</td>
            <td>
                <div class="aksi-wrap">
                    <button class="btn-aksi btn-lihat" onclick="window.location.href='sampah_lihat.php?id=${escHtml(s.id)}'">
                        <i class="bi bi-file-earmark-text"></i> Lihat
                    </button>
                    ${canEdit ? `
                    <button class="btn-aksi btn-edit" onclick="window.location.href='sampah_edit.php?id=${escHtml(s.id)}'">
                        <i class="bi bi-pencil-square"></i> Edit
                    </button>
                    <button class="btn-aksi btn-hapus" onclick="hapusSampah('${escHtml(s.id)}','${s.nama.replace(/'/g,"\\'")}')">
                        <i class="bi bi-trash3"></i> Hapus
                    </button>` : ''}
                </div>
            </td>
        </tr>`).join('');
}

/* ── Search live ── */
let searchTimeout = null;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim().toLowerCase();
    searchTimeout = setTimeout(() => {
        if (!q) { renderTable(allSampahData); return; }
        const filtered = allSampahData.filter(s =>
            s.nama.toLowerCase().includes(q) ||
            s.tanggal.toLowerCase().includes(q)
        );
        renderTable(filtered);
    }, 200);
});

/* ── Filter ── */
function toggleFilter(){ document.getElementById('filterBox').classList.toggle('show'); }
function applyFilter(){
    const sort = document.getElementById('sortOrder').value;
    const q    = document.getElementById('searchInput').value.trim().toLowerCase();
    let data   = q ? allSampahData.filter(s => s.nama.toLowerCase().includes(q) || s.tanggal.toLowerCase().includes(q))
                   : [...allSampahData];
    data.sort((a,b) => {
        if(sort==='nama_asc')   return a.nama.localeCompare(b.nama);
        if(sort==='nama_desc')  return b.nama.localeCompare(a.nama);
        if(sort==='harga_desc') return b.harga - a.harga;
        if(sort==='harga_asc')  return a.harga - b.harga;
        return 0;
    });
    renderTable(data);
    document.getElementById('filterBox').classList.remove('show');
}
function resetFilter(){ document.getElementById('sortOrder').value='nama_asc'; applyFilter(); }

/* ── Modal helpers ── */
function openModal(id){ document.getElementById(id).classList.add('show'); }
function closeModal(id){ document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); });
});

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