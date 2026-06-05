<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? '';
$userFoto  = $_SESSION['foto_profil'] ?? '';

function getSupabaseImageUrl($p){
    return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');
}
function sbGet($url,$key,$ep){
    $ch=curl_init($url.$ep);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey: $key","Authorization: Bearer $key","Content-Type: application/json"]]);
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return $c===200?(json_decode($r,true)?:[]):[];
}

// Ambil id dari query string
$idJadwal = $_GET['id'] ?? '';
if(empty($idJadwal)){
    header("Location: jadwal_ambil_sampah.php");
    exit;
}

// Fetch data jadwal
$dataList = sbGet($supabaseUrl,$supabaseKey,"/rest/v1/jadwal_ambil?id_jadwal=eq.".urlencode($idJadwal)."&select=*");
if(empty($dataList)){
    header("Location: jadwal_ambil_sampah.php?error=notfound");
    exit;
}
$jadwal = $dataList[0];

// Format nilai awal
$valTanggal = htmlspecialchars($jadwal['tanggal'] ?? '');
$valMulai   = htmlspecialchars(substr($jadwal['waktu_mulai']   ?? '08:00:00', 0, 5));
$valSelesai = htmlspecialchars(substr($jadwal['waktu_selesai'] ?? '10:00:00', 0, 5));
$valKuota   = (int)($jadwal['kuota'] ?? 0);

// Format label tanggal
$hariArr  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$ts       = strtotime($jadwal['tanggal']);
$hariLabel= $hariArr[date('w',$ts)];
$tglLabel = date('d',$ts).' '.$bulanArr[(int)date('m',$ts)].' '.date('Y',$ts);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Jadwal Ambil Sampah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
</head>
<body>

<!-- SIDEBAR -->
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
        <div class="form-card form-card-padded">
            <div class="card-header-orange card-header-rounded">
                <h2>Edit Jadwal Ambil Sampah</h2>
                <p><i class="bi bi-calendar2 me-1"></i><?=$hariLabel?>, <?=$tglLabel?></p>
            </div>
            <div class="fields-wrap" style="padding:0;">

                <div class="info-box">
                    <i class="bi bi-pencil-square"></i>
                    Ubah data jadwal di bawah, lalu klik Simpan Perubahan.
                </div>

                <!-- Tanggal -->
                <div class="row-1">
                    <label class="field-label">Tanggal <span style="color:#D95D39;">*</span></label>
                    <input type="date" id="tanggal" class="field-ul" value="<?=$valTanggal?>">
                    <span class="field-err" id="errTanggal">Tanggal wajib diisi</span>
                </div>

                <!-- Waktu Mulai & Selesai -->
                <div class="row-2">
                    <div>
                        <label class="field-label">Waktu Mulai <span style="color:#D95D39;">*</span></label>
                        <input type="time" id="waktuMulai" class="field-ul" value="<?=$valMulai?>">
                        <span class="field-err" id="errMulai">Waktu mulai wajib diisi</span>
                    </div>
                    <div>
                        <label class="field-label">Waktu Selesai <span style="color:#D95D39;">*</span></label>
                        <input type="time" id="waktuSelesai" class="field-ul" value="<?=$valSelesai?>">
                        <span class="field-err" id="errSelesai">Waktu selesai wajib diisi</span>
                    </div>
                </div>

                <!-- Kuota -->
                <div class="row-1">
                    <label class="field-label">Kuota Slot <span style="color:#D95D39;">*</span></label>
                    <input type="number" id="kuota" class="field-ul" value="<?=$valKuota?>" min="1" max="200">
                    <span class="field-err" id="errKuota">Kuota wajib diisi (minimal 1)</span>
                </div>

            </div><!-- /fields-wrap -->

            <div class="form-actions">
                <button type="button" class="btn-batal"
                        onclick="window.location.href='jadwal_ambil_sampah.php'">BATAL</button>
                <button type="button" id="btnSimpan" class="btn-simpan" onclick="simpanData()">
                    SIMPAN PERUBAHAN
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

function validate(){
    let ok=true;
    const setErr=(errId,cond)=>{
        document.getElementById(errId).style.display=cond?'none':'block';
        if(!cond) ok=false;
    };
    const mul = document.getElementById('waktuMulai').value;
    const sel = document.getElementById('waktuSelesai').value;
    const kuo = parseInt(document.getElementById('kuota').value);
    setErr('errTanggal', !!document.getElementById('tanggal').value);
    setErr('errMulai',   !!mul);
    setErr('errSelesai', !!sel);
    setErr('errKuota',   kuo>=1);
    if(ok && mul>=sel){
        showToast('Waktu mulai harus sebelum waktu selesai.','error');
        ok=false;
    }
    return ok;
}

async function simpanData(){
    if(!validate()) return;
    const btn=document.getElementById('btnSimpan');
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    try{
        const res=await fetch(`${SB_URL}/rest/v1/jadwal_ambil?id_jadwal=eq.${JADWAL_ID}`,{
            method:'PATCH',
            headers:{
                'apikey':SB_KEY,
                'Authorization':'Bearer '+SB_KEY,
                'Content-Type':'application/json',
                'Prefer':'return=representation'
            },
            body:JSON.stringify({
                tanggal      : document.getElementById('tanggal').value,
                waktu_mulai  : document.getElementById('waktuMulai').value+':00',
                waktu_selesai: document.getElementById('waktuSelesai').value+':00',
                kuota        : parseInt(document.getElementById('kuota').value)
            })
        });
        if(res.ok){
        const ADMIN_ID = "<?= $_SESSION['id_admin'] ?? '' ?>";
        if (ADMIN_ID) fetch(SB_URL + '/rest/v1/log_admin', { method: 'POST', headers: { 'apikey': SB_KEY, 'Authorization': 'Bearer ' + SB_KEY, 'Content-Type': 'application/json' }, body: JSON.stringify({ id_admin: ADMIN_ID, aktivitas: "Mengedit jadwal ambil sampah", tabel_terkait: 'jadwal_ambil', id_data: JADWAL_ID, created_at: new Date().toISOString().split('.')[0] + 'Z' }) });
    
            showToast('Jadwal berhasil diperbarui!','success');
            setTimeout(()=>window.location.href='jadwal_ambil_sampah.php',900);
        } else {
            const e=await res.json();
            showToast(e.message||'Gagal menyimpan perubahan.','error');
            btn.disabled=false;
            btn.innerHTML='SIMPAN PERUBAHAN';
        }
    } catch{
        showToast('Kesalahan jaringan.','error');
        btn.disabled=false;
        btn.innerHTML='SIMPAN PERUBAHAN';
    }
}
</script>
</body>
</html>