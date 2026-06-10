<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? '';
$userRole  = $_SESSION['role']        ?? '';
$userFoto  = $_SESSION['foto_profil'] ?? '';

function getSupabaseImageUrl($p){return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');}
function sbGet($url,$key,$ep){$ch=curl_init($url.$ep);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey: $key","Authorization: Bearer $key","Content-Type: application/json"]]);$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $c===200?(json_decode($r,true)?:[]):[];}

function stJadwal($tgl){$t=date('Y-m-d');if($tgl<$t)return 'selesai';if($tgl===$t)return 'hari_ini';return 'mendatang';}

$page     = max(1,(int)($_GET['page']??1));
$per_page = 10;

$allJadwal = sbGet($supabaseUrl,$supabaseKey,
    "/rest/v1/jadwal_ambil?select=*,setor_sampah(count)&order=tanggal.asc,waktu_mulai.asc");

$totJadwal=$totHari=$totMendatang=$totSelesai=$totKuota=0;
foreach($allJadwal as $j){
    $s=stJadwal($j['tanggal']);
    if($s==='hari_ini') $totHari++;
    if($s==='mendatang') $totMendatang++;
    if($s==='selesai') $totSelesai++;
    $totKuota+=(int)($j['kuota']??0);
    $totJadwal++;
}

$hariArr =['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulanArr=['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];


$f_st   = $_GET['status'] ?? '';
$f_sesi = $_GET['sesi'] ?? '';
$f_sort = $_GET['sort'] ?? 'tanggal_asc';
$f_q    = strtolower($_GET['q'] ?? '');

$filtered = [];
foreach($allJadwal as $j){
    $st = stJadwal($j['tanggal']);
    $sesi = (int)substr($j['waktu_mulai'],0,2)<12?'pagi':'sore';
    
    $ts = strtotime($j['tanggal']);
    $hari = $hariArr[date('w',$ts)];
    $tglStr = date('d',$ts).' '.$bulanArr[(int)date('m',$ts)].' '.date('Y',$ts);
    $searchString = strtolower($hari . ' ' . $tglStr . ' ' . $j['waktu_mulai'] . ' ' . $j['waktu_selesai']);
    
    $match = true;
    if($f_st && $st !== $f_st) $match = false;
    if($f_sesi && $sesi !== $f_sesi) $match = false;
    if($f_q && strpos($searchString, $f_q) === false) $match = false;
    
    if($match) $filtered[] = $j;
}

usort($filtered, function($a, $b) use ($f_sort) {
    if($f_sort === 'tanggal_desc') return strcmp($b['tanggal'].$b['waktu_mulai'], $a['tanggal'].$a['waktu_mulai']);
    if($f_sort === 'kuota_desc') return (int)$b['kuota'] - (int)$a['kuota'];
    return strcmp($a['tanggal'].$a['waktu_mulai'], $b['tanggal'].$b['waktu_mulai']);
});

$total      = count($filtered);
$start      = ($page-1)*$per_page;
$paginated  = array_slice($filtered,$start,$per_page);
$totalPages = $total>0?ceil($total/$per_page):1;
$startNum   = $total>0?$start+1:0;
$endNum     = min($start+$per_page,$total);

function buildUrl($p){
    $params = $_GET;
    $params['page'] = $p;
    return '?'.http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jadwal Ambil Sampah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>


<aside class="sidebar">
    <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth"></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="dashboard.php"              class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="transaksi_setor_sampah.php" class="nav-link-custom"><i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span></a></div>
        <div class="nav-item"><a href="penarikan_saldo.php"        class="nav-link-custom"><i class="bi bi-wallet2"></i><span>Penarikan Saldo</span></a></div>
        <div class="nav-item"><a href="event_lingkungan.php"       class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
        <div class="nav-item"><a href="jadwal_ambil_sampah.php"    class="nav-link-custom active"><i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span></a></div>
        <div class="nav-item"><a href="laporan_keuangan.php"       class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span></a></div>
        <div class="nav-item"><a href="data_nasabah.php"           class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Nasabah</span></a></div>
        <div class="nav-item"><a href="data_sampah.php"            class="nav-link-custom"><i class="bi bi-trash-fill"></i><span>Data Sampah</span></a></div>
        <div class="nav-item"><a href="pengaturan_akun.php"        class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
    </nav>
    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
</aside>

<div class="main-wrap">

    
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Jadwal Ambil Sampah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?=htmlspecialchars($userName)?></div>
                    <div class="topbar-user-email"><?=htmlspecialchars($userEmail)?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if(!empty($userFoto)):$fu=getSupabaseImageUrl($userFoto);?>
                        <img src="<?=htmlspecialchars($fu)?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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
                <input type="text" class="search-input" id="searchInput" placeholder="Cari tanggal atau waktu (Tekan Enter)..." value="<?=htmlspecialchars($_GET['q']??'')?>">
            </div>
            <div class="filter-dropdown">
                <button class="btn-filter" onclick="toggleFilter()"><i class="bi bi-sliders2"></i> Filter</button>
                <div class="filter-box" id="filterBox">
                    <div class="filter-group">
                        <label class="filter-label">Status Jadwal</label>
                        <select class="filter-select" id="filterStatus">
                            <option value="">Semua</option>
                            <option value="mendatang" <?=$f_st==='mendatang'?'selected':''?>>Mendatang</option>
                            <option value="hari_ini" <?=$f_st==='hari_ini'?'selected':''?>>Hari Ini</option>
                            <option value="selesai" <?=$f_st==='selesai'?'selected':''?>>Selesai</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Sesi Waktu</label>
                        <select class="filter-select" id="filterSesi">
                            <option value="">Semua</option>
                            <option value="pagi" <?=$f_sesi==='pagi'?'selected':''?>>Pagi (08:00)</option>
                            <option value="sore" <?=$f_sesi==='sore'?'selected':''?>>Sore (15:30)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Urutan</label>
                        <select class="filter-select" id="sortOrder">
                            <option value="tanggal_asc" <?=$f_sort==='tanggal_asc'?'selected':''?>>Tanggal Terlama</option>
                            <option value="tanggal_desc" <?=$f_sort==='tanggal_desc'?'selected':''?>>Tanggal Terbaru</option>
                            <option value="kuota_desc" <?=$f_sort==='kuota_desc'?'selected':''?>>Kuota Terbanyak</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-reset-filter" onclick="resetFilter()">Reset</button>
                        <button class="btn-apply-filter" onclick="applyFilter()">Terapkan</button>
                    </div>
                </div>
            </div>
            <?php if(in_array($userRole,['bank sampah','admin','dlh'])):?>
            <a href="tambah_jadwal.php" class="btn-tambah" style="text-decoration:none;">
                <i class="bi bi-plus-lg"></i> Tambah Jadwal
            </a>
            <?php endif;?>
        </div>
    </div>

    
    <div class="content-area" style="padding-top:0;padding-bottom:0;">
        <div class="summary-row">
            <div class="summary-card">
                <div class="sum-icon blue"><i class="bi bi-calendar2-week-fill"></i></div>
                <div class="sum-info"><span>Total Jadwal</span><strong><?=$totJadwal?></strong></div>
            </div>
            <div class="summary-card">
                <div class="sum-icon yellow"><i class="bi bi-calendar2-day-fill"></i></div>
                <div class="sum-info"><span>Hari Ini</span><strong><?=$totHari?></strong></div>
            </div>
            <div class="summary-card">
                <div class="sum-icon orange"><i class="bi bi-clock-fill"></i></div>
                <div class="sum-info"><span>Mendatang</span><strong><?=$totMendatang?></strong></div>
            </div>
            <div class="summary-card">
                <div class="sum-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="sum-info"><span>Selesai</span><strong><?=$totSelesai?></strong></div>
            </div>
            <div class="summary-card">
                <div class="sum-icon green"><i class="bi bi-people-fill"></i></div>
                <div class="sum-info"><span>Total Kuota</span><strong><?=$totKuota?></strong></div>
            </div>
        </div>
    </div>

    
    <div class="content-area" style="padding-top:0;">
        <div class="card-custom">
            <div class="table-wrap">
                <div class="table-scroll-wrapper">
                    <table class="responsive-table" id="dynamicTable">
                        <colgroup>
                            <col style="width:48px;">
                            <col style="width:150px;">
                            <col style="width:165px;">
                            <col style="width:145px;">
                            <col style="width:145px;">
                            <col style="width:115px;">
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Kuota</th>
                                <th>Peserta Terdaftar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        <?php if(!empty($paginated)): foreach($paginated as $idx=>$j):
                            $st      = stJadwal($j['tanggal']);
                            $kuota   = (int)($j['kuota']??0);
                            $peserta = isset($j['setor_sampah'])?count($j['setor_sampah']):0;
                            $sisa    = max(0,$kuota-$peserta);
                            $pct     = $kuota>0?min(100,round($peserta/$kuota*100)):0;
                            $barCls  = $pct>=100?'kuota-full':($pct>=70?'kuota-warn':'kuota-ok');
                            $ts      = strtotime($j['tanggal']);
                            $hari    = $hariArr[date('w',$ts)];
                            $tgl     = date('d',$ts).' '.$bulanArr[(int)date('m',$ts)].' '.date('Y',$ts);
                            $wMul    = substr($j['waktu_mulai'],0,5);
                            $wSel    = substr($j['waktu_selesai'],0,5);
                            $sesi    = (int)substr($j['waktu_mulai'],0,2)<12?'pagi':'sore';
                            $stLbl   = ['mendatang'=>'Mendatang','hari_ini'=>'Hari Ini','selesai'=>'Selesai'];
                            $stIco   = ['mendatang'=>'bi-clock','hari_ini'=>'bi-calendar2-check','selesai'=>'bi-check-circle-fill'];
                            $idEnc   = urlencode($j['id_jadwal']);





                            $bolehHapus = in_array($userRole,['bank sampah','admin','dlh']);
                        ?>
                        <tr data-tanggal="<?=$j['tanggal']?>"
                            data-status="<?=$st?>"
                            data-sesi="<?=$sesi?>"
                            data-kuota="<?=$kuota?>">
                            <td><?=$startNum+$idx?></td>
                            <td>
                                <span class="tanggal-day"><?=$hari?></span>
                                <span class="tanggal-date"><?=$tgl?></span>
                            </td>
                            <td>
                                <span class="waktu-val">
                                    <i class="bi bi-clock me-1" style="color:#1D9E75;"></i><?=$wMul?> – <?=$wSel?>
                                </span>
                            </td>
                            <td>
                                <div class="kuota-wrap <?=$barCls?>">
                                    <span class="kuota-text">
                                        <?=$peserta?> / <?=$kuota?> slot
                                        <?php if($sisa===0):?><span style="color:#D95D39;font-weight:700;"> (Penuh)</span><?php endif;?>
                                    </span>
                                    <div class="kuota-bar"><div class="kuota-fill" style="width:<?=$pct?>%;"></div></div>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight:700;color:#1A3C34;"><?=$peserta?></span>
                                <span style="color:#6B8A7E;font-size:12px;"> pengguna</span>
                            </td>
                            <td>
                                <span class="badge-status badge-<?=$st?>">
                                    <i class="bi <?=$stIco[$st]?>"></i> <?=$stLbl[$st]?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    
                                    <a href="lihat_jadwal.php?id=<?=$idEnc?>"
                                       class="btn-aksi btn-lihat" style="text-decoration:none;">
                                        <i class="bi bi-file-earmark-text"></i> Lihat
                                    </a>
                                    <?php if($bolehHapus):?>
                                    
                                    <a href="edit_jadwal.php?id=<?=$idEnc?>"
                                       class="btn-aksi btn-edit" style="text-decoration:none;">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    
                                    <a href="hapus_jadwal.php?id=<?=$idEnc?>"
                                       class="btn-aksi btn-hapus" style="text-decoration:none;">
                                        <i class="bi bi-trash3"></i> Hapus
                                    </a>
                                    <?php endif;?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else:?>
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color:#6B8A7E;">
                                <i class="bi bi-calendar2-x" style="font-size:36px;display:block;margin-bottom:10px;"></i>
                                Belum ada data jadwal
                            </td>
                        </tr>
                        <?php endif;?>
                        </tbody>
                    </table>
                </div>

                <?php if($totalPages>1):?>
                <div class="table-footer">
                    <div class="showing-text">
                        Showing <b><?=$startNum?></b> to <b><?=$endNum?></b> of <b><?=$total?></b> entries
                    </div>
                    <div class="pagination-custom">
                        <?php if($page>1):?>
                            <a href="<?=buildUrl($page-1)?>" class="page-btn page-btn-text">Prev</a>
                        <?php endif;?>
                        <?php for($i=1;$i<=$totalPages;$i++):?>
                            <a href="<?=buildUrl($i)?>" class="page-btn <?=$i==$page?'active':''?>"><?=$i?></a>
                        <?php endfor;?>
                        <?php if($page<$totalPages):?>
                            <a href="<?=buildUrl($page+1)?>" class="page-btn page-btn-text">Next</a>
                        <?php endif;?>
                    </div>
                </div>
                <?php endif;?>

            </div>
        </div>
    </div>

</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg, type='success'){
    const ico={success:'bi-check-circle-fill',error:'bi-x-circle-fill'};
    const d=document.createElement('div');
    d.className=`toast-item ${type}`;
    d.innerHTML=`<i class="bi ${ico[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(d);
    setTimeout(()=>d.remove(),3500);
}


const urlP=new URLSearchParams(location.search);
if(urlP.get('success')==='1')         showToast('Jadwal berhasil disimpan!','success');
if(urlP.get('deleted')==='1')         showToast('Jadwal berhasil dihapus.','success');
if(urlP.get('error')==='cannot_delete') showToast('Jadwal selesai tidak bisa dihapus.','error');


document.getElementById('searchInput').addEventListener('keypress', function(e){
    if(e.key === 'Enter') applyFilter();
});

function toggleFilter(){ document.getElementById('filterBox').classList.toggle('show'); }
function applyFilter(){
    const st   = document.getElementById('filterStatus').value;
    const sesi = document.getElementById('filterSesi').value;
    const sort = document.getElementById('sortOrder').value;
    const q    = document.getElementById('searchInput').value;
    
    let url = new URL(window.location.href);
    url.searchParams.set('page', '1');
    
    if(st) url.searchParams.set('status', st); else url.searchParams.delete('status');
    if(sesi) url.searchParams.set('sesi', sesi); else url.searchParams.delete('sesi');
    if(sort) url.searchParams.set('sort', sort); else url.searchParams.delete('sort');
    if(q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    
    window.location.href = url.toString();
}
function resetFilter(){
    window.location.href = 'jadwal_ambil_sampah.php';
}
</script>
</body>
</html>