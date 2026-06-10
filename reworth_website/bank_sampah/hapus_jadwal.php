<?php
require_once 'role_check.php';

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? '';
$userRole  = $_SESSION['role']        ?? '';
$userFoto  = $_SESSION['foto_profil'] ?? '';


if(!in_array($userRole,['bank sampah','admin','dlh'])){
    header("Location: jadwal_ambil_sampah.php");
    exit;
}

function getSupabaseImageUrl($p){
    return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');
}
function sbGet($url,$key,$ep){
    $ch=curl_init($url.$ep);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey: $key","Authorization: Bearer $key","Content-Type: application/json"]]);
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return $c===200?(json_decode($r,true)?:[]):[];
}

$idJadwal = $_GET['id'] ?? '';
if(empty($idJadwal)){
    header("Location: jadwal_ambil_sampah.php");
    exit;
}

$dataList = sbGet($supabaseUrl,$supabaseKey,
    "/rest/v1/jadwal_ambil?id_jadwal=eq.".urlencode($idJadwal)."&select=*,setor_sampah(count)");
if(empty($dataList)){
    header("Location: jadwal_ambil_sampah.php?error=notfound");
    exit;
}
$jadwal  = $dataList[0];
$kuota   = (int)($jadwal['kuota']??0);
$peserta = isset($jadwal['setor_sampah'])?count($jadwal['setor_sampah']):0;


function stJadwal($tgl){$t=date('Y-m-d');if($tgl<$t)return 'selesai';if($tgl===$t)return 'hari_ini';return 'mendatang';}
$st = stJadwal($jadwal['tanggal']);
if($st==='selesai'){
    header("Location: jadwal_ambil_sampah.php?error=cannot_delete");
    exit;
}

$hariArr =['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulanArr=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$ts       = strtotime($jadwal['tanggal']);
$hariLabel= $hariArr[date('w',$ts)];
$tglLabel = date('d',$ts).' '.$bulanArr[(int)date('m',$ts)].' '.date('Y',$ts);
$wMul     = substr($jadwal['waktu_mulai']??'',0,5);
$wSel     = substr($jadwal['waktu_selesai']??'',0,5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hapus Jadwal Ambil Sampah</title>
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

    <div class="form-wrap">
        <div class="form-card">
            <div class="card-header-red"><h2><i class="bi bi-trash3-fill me-2"></i>Hapus Jadwal</h2></div>
            <div class="fields-wrap">

                
                <div class="confirm-icon-big"><i class="bi bi-trash3-fill"></i></div>
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="font-size:17px;font-weight:700;color:#1A3C34;">Yakin ingin menghapus jadwal ini?</div>
                    <div style="font-size:13px;color:#6B8A7E;margin-top:4px;">Tindakan ini tidak dapat dibatalkan.</div>
                </div>

                
                <div class="jadwal-info-card">
                    <div class="jadwal-info-row">
                        <div class="ji-icon"><i class="bi bi-calendar3"></i></div>
                        <span class="ji-label">Tanggal</span>
                        <span class="ji-val"><?=$hariLabel?>, <?=$tglLabel?></span>
                    </div>
                    <div class="jadwal-info-row">
                        <div class="ji-icon"><i class="bi bi-clock"></i></div>
                        <span class="ji-label">Waktu</span>
                        <span class="ji-val"><?=$wMul?> – <?=$wSel?></span>
                    </div>
                    <div class="jadwal-info-row">
                        <div class="ji-icon"><i class="bi bi-people"></i></div>
                        <span class="ji-label">Kuota</span>
                        <span class="ji-val"><?=$kuota?> slot</span>
                    </div>
                    <div class="jadwal-info-row">
                        <div class="ji-icon"><i class="bi bi-person-check"></i></div>
                        <span class="ji-label">Peserta</span>
                        <span class="ji-val"><?=$peserta?> pengguna terdaftar</span>
                    </div>
                </div>

                <?php if($peserta>0):?>
                
                <div class="warn-peserta">
                    <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;margin-top:1px;"></i>
                    <span>Jadwal ini memiliki <strong><?=$peserta?> peserta terdaftar</strong>.
                    Menghapus jadwal akan mempengaruhi data setor sampah yang terkait.</span>
                </div>
                <?php endif;?>

                <div class="warn-text">
                    <i class="bi bi-x-circle-fill" style="flex-shrink:0;margin-top:1px;"></i>
                    <span>Data jadwal yang dihapus <strong>tidak dapat dipulihkan</strong> kembali.</span>
                </div>

            </div>

            <div class="form-actions">
                <button type="button" class="btn-batal"
                        onclick="window.location.href='jadwal_ambil_sampah.php'">BATAL</button>
                <button type="button" id="btnHapus" class="btn-hapus-form" onclick="hapusData()">
                    <i class="bi bi-trash3 me-1"></i>YA, HAPUS JADWAL
                </button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SB_URL  = '<?=$supabaseUrl?>';
const SB_KEY  = '<?=$supabaseKey?>';
const JADWAL_ID = '<?=htmlspecialchars($idJadwal)?>';

function showToast(msg, type='success'){
    const ico={success:'bi-check-circle-fill',error:'bi-x-circle-fill'};
    const d=document.createElement('div');
    d.className=`toast-item ${type}`;
    d.innerHTML=`<i class="bi ${ico[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(d);
    setTimeout(()=>d.remove(),3500);
}

async function hapusData(){
    const btn=document.getElementById('btnHapus');
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Menghapus...';

    try{
        const res=await fetch(`${SB_URL}/rest/v1/jadwal_ambil?id_jadwal=eq.${JADWAL_ID}`,{
            method:'DELETE',
            headers:{
                'apikey':SB_KEY,
                'Authorization':'Bearer '+SB_KEY,
                'Content-Type':'application/json'
            }
        });
        if(res.ok){
        const ADMIN_ID = "<?= $_SESSION['id_admin'] ?? '' ?>";
        if (ADMIN_ID) fetch(SB_URL + '/rest/v1/log_admin', { method: 'POST', headers: { 'apikey': SB_KEY, 'Authorization': 'Bearer ' + SB_KEY, 'Content-Type': 'application/json' }, body: JSON.stringify({ id_admin: ADMIN_ID, aktivitas: "Menghapus jadwal ambil sampah", tabel_terkait: 'jadwal_ambil', id_data: JADWAL_ID, created_at: new Date().toISOString().split('.')[0] + 'Z' }) });
    
            showToast('Jadwal berhasil dihapus.','success');
            setTimeout(()=>window.location.href='jadwal_ambil_sampah.php',900);
        } else {
            showToast('Gagal menghapus jadwal.','error');
            btn.disabled=false;
            btn.innerHTML='<i class="bi bi-trash3 me-1"></i>YA, HAPUS JADWAL';
        }
    } catch{
        showToast('Kesalahan jaringan.','error');
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-trash3 me-1"></i>YA, HAPUS JADWAL';
    }
}
</script>
</body>
</html>