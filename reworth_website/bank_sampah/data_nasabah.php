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

$nasabahList = sbGet($supabaseUrl,$supabaseKey,
    "/rest/v1/pengguna?select=id_pengguna,nama_lengkap,email,no_telepon,poin,saldo_tabungan,id_wilayah,wilayah(rw,kelurahan,kecamatan)&order=nama_lengkap.asc");

$per_page   = 10;
$total      = count($nasabahList);
$total_pages= max(1,ceil($total/$per_page));
$cur_page   = max(1,(int)($_GET['page']??1));
$start      = ($cur_page-1)*$per_page;
$cur_data   = array_slice($nasabahList,$start,$per_page);
$show_from  = $total>0?$start+1:0;
$show_to    = min($start+$per_page,$total);
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bank Sampah – Data Nasabah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
<style>
.poin-val  { color:#DBC729; font-weight:700; }
.saldo-val { color:#D95D39; font-weight:700; }
.aksi-wrap { display:flex; gap:6px; flex-wrap:wrap; }
</style>
</head><body>
<aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="Bank Sampah Kota Malang">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link-custom">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="transaksi_setor_sampah.php" class="nav-link-custom">
                    <i class="bi bi-recycle"></i>
                    <span>Transaksi Setor Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="penarikan_saldo.php" class="nav-link-custom">
                    <i class="bi bi-wallet2"></i>
                    <span>Penarikan Saldo</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="event_lingkungan.php" class="nav-link-custom">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>Event Lingkungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan_keuangan.php" class="nav-link-custom">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan dan Keuangan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_nasabah.php" class="nav-link-custom active">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Nasabah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="data_sampah.php" class="nav-link-custom">
                    <i class="bi bi-trash-fill"></i>
                    <span>Data Sampah</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="pengaturan_akun.php" class="nav-link-custom">
                    <i class="bi bi-gear-fill"></i>
                    <span>Pengaturan Akun</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
<div class="main-wrap">
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Daftar Nasabah</h1>
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
                <input type="text" class="search-input" placeholder="Cari nasabah..." id="searchInput">
            </div>
            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()"><i class="bi bi-sliders2"></i> Filter</button>
                <div class="filter-box" id="filterBox">
                    <div class="filter-group">
                        <label>Urutkan</label>
                        <select id="sortOrder">
                            <option value="nama_asc">Nama A–Z</option>
                            <option value="nama_desc">Nama Z–A</option>
                            <option value="poin_desc">Poin Tertinggi</option>
                            <option value="saldo_desc">Saldo Tertinggi</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" onclick="resetFilter()">Reset</button>
                        <button type="button" onclick="applyFilter()">Terapkan</button>
                    </div>
                </div>
            </div>
            <?php if(in_array($userRole,['bank sampah','admin','dlh'])):?>
            <button class="btn-tambah" onclick="window.location.href='nasabah_tambah.php'">
                <i class="bi bi-plus-lg"></i> Tambah Nasabah
            </button>
            <?php endif;?>
        </div>
    </div>

    <div class="content-area">
        <div class="card-custom">
            <div class="table-scroll-wrapper">
                <table class="responsive-table">
                    <colgroup>
                        <col style="width:50px;"><col style="width:200px;"><col style="width:200px;">
                        <col style="width:140px;"><col style="width:160px;">
                        <col style="width:100px;"><col style="width:120px;"><col style="width:160px;">
                    </colgroup>
                    <thead><tr>
                        <th>No</th><th>Nama Lengkap</th><th>Email</th>
                        <th>No HP</th><th>Wilayah</th>
                        <th>Poin</th><th>Saldo</th><th>Aksi</th>
                    </tr></thead>
                    <tbody id="tableBody">
                    <?php if(!empty($cur_data)):?>
                        <?php foreach($cur_data as $i=>$n):
                            $wil = $n['wilayah'] ?? null;
                            $wilLabel = $wil ? htmlspecialchars($wil['kelurahan'].' / RW '.$wil['rw']) : '-';
                        ?>
                        <tr data-nama="<?=strtolower(htmlspecialchars($n['nama_lengkap']??''))?>"
                            data-poin="<?=(int)($n['poin']??0)?>"
                            data-saldo="<?=(float)($n['saldo_tabungan']??0)?>">
                            <td class="td-no"><?=$start+$i+1?></td>
                            <td class="td-nama"><?=htmlspecialchars($n['nama_lengkap']??'-')?></td>
                            <td style="font-size:12px;"><?=htmlspecialchars($n['email']??'-')?></td>
                            <td style="font-size:12px;"><?=htmlspecialchars($n['no_telepon']??'-')?></td>
                            <td style="font-size:12px;"><?=$wilLabel?></td>
                            <td class="poin-val"><?=(int)($n['poin']??0)?></td>
                            <td class="saldo-val" style="font-size:12px;"><?=fmtRp($n['saldo_tabungan']??0)?></td>
                            <td>
                                <div class="aksi-wrap">
                                    <button class="btn-aksi btn-lihat" onclick="window.location.href='nasabah_lihat.php?id=<?=$n['id_pengguna']?>'">
                                        <i class="bi bi-eye"></i> Lihat
                                    </button>
                                    <?php if(in_array($userRole,['bank sampah','admin','dlh'])):?>
                                    <button class="btn-aksi btn-edit" onclick="window.location.href='nasabah_edit.php?id=<?=$n['id_pengguna']?>'">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn-aksi btn-hapus" onclick="hapusNasabah('<?=$n['id_pengguna']?>','<?=htmlspecialchars($n['nama_lengkap']??'')?>',this)">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                    <?php endif;?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    <?php else:?>
                        <tr><td colspan="8" class="text-center py-4" style="color:#6B8A7E;">
                            <i class="bi bi-people" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                            Belum ada data nasabah
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
            <h3>Hapus Nasabah?</h3>
            <p>Data nasabah akan dihapus secara permanen.</p>
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
let deletingId=null,deletingNama=null;
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));

document.getElementById('searchInput').addEventListener('input',function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('#tableBody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});
    rebuildNo();
});
function toggleFilter(){document.getElementById('filterBox').classList.toggle('show');}
function applyFilter(){
    const sort=document.getElementById('sortOrder').value;
    const tbody=document.querySelector('#tableBody');
    let rows=Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a,b)=>{
        if(sort==='nama_asc') return a.dataset.nama.localeCompare(b.dataset.nama);
        if(sort==='nama_desc') return b.dataset.nama.localeCompare(a.dataset.nama);
        if(sort==='poin_desc') return parseFloat(b.dataset.poin)-parseFloat(a.dataset.poin);
        if(sort==='saldo_desc') return parseFloat(b.dataset.saldo)-parseFloat(a.dataset.saldo);
        return 0;
    });
    rows.forEach(r=>tbody.appendChild(r));
    rebuildNo();
    document.getElementById('filterBox').classList.remove('show');
}
function resetFilter(){document.getElementById('sortOrder').value='nama_asc';applyFilter();}
function rebuildNo(){let no=1;document.querySelectorAll('#tableBody tr').forEach(r=>{if(r.style.display!=='none'&&r.children[0])r.children[0].textContent=no++;});}

function hapusNasabah(id,nama){deletingId=id;deletingNama=nama;openModal('modalHapus');}
function confirmHapus(){
    if(!deletingId)return;
    const btn=document.getElementById('btnConfirmHapus');
    btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>';
    fetch('nasabah_hapus.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:deletingId,nama:deletingNama})})
    .then(r=>r.json()).then(data=>{
        closeModal('modalHapus');
        if(data.success){showToast('Nasabah berhasil dihapus.','success');setTimeout(()=>location.reload(),800);}
        else{showToast(data.message||'Gagal menghapus.','error');btn.disabled=false;btn.innerHTML='<i class="bi bi-trash3"></i> Ya, Hapus';}
    }).catch(()=>showToast('Kesalahan server.','error'));
}
function showToast(msg,type='success'){
    const icons={success:'bi-check-circle-fill',error:'bi-x-circle-fill'};
    const div=document.createElement('div');div.className=`toast-item ${type}`;
    div.innerHTML=`<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);setTimeout(()=>div.remove(),3500);
}
</script>
</body>
</html>