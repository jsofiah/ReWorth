<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? '';
$userRole  = $_SESSION['role']        ?? '';
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
$sisa    = max(0,$kuota-$peserta);
$pct     = $kuota>0?min(100,round($peserta/$kuota*100)):0;

// Status
function stJadwal($tgl){
    $t=date('Y-m-d');
    if($tgl<$t) return 'selesai';
    if($tgl===$t) return 'hari_ini';
    return 'mendatang';
}
$st = stJadwal($jadwal['tanggal']);
$stLabel=['mendatang'=>'Mendatang','hari_ini'=>'Hari Ini','selesai'=>'Selesai'];
$stColor=['mendatang'=>'#2980B9','hari_ini'=>'#856404','selesai'=>'#388E3C'];
$stBg   =['mendatang'=>'#EAF4FF', 'hari_ini'=>'#FFF3CD','selesai'=>'#E8F5E9'];

// Format tanggal
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
<title>Detail Jadwal Ambil Sampah</title>
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

    <!-- CARD ATAS – info singkat, gaya sama nasabah_lihat -->
    <div class="setting-bar-wrap">
        <div class="settings-card">
            <div class="card-accent"></div>
            <div class="card-body-inner" style="display:flex;align-items:center;gap:28px;flex-wrap:wrap;">

                <!-- Ikon kalender -->
                <div style="width:80px;height:80px;border-radius:20px;background:#E8F5E9;display:flex;align-items:center;justify-content:center;font-size:38px;color:#1D9E75;flex-shrink:0;">
                    <i class="bi bi-calendar2-week-fill"></i>
                </div>

                <div style="flex:1;min-width:200px;">
                    <div style="font-size:22px;font-weight:800;color:#1A3C34;line-height:1.2;">
                        <?=$hariLabel?>, <?=$tglLabel?>
                    </div>
                    <div style="font-size:14px;color:#6B8A7E;margin-top:4px;">
                        <i class="bi bi-clock me-1" style="color:#1D9E75;"></i><?=$wMul?> – <?=$wSel?>
                    </div>
                    <div style="margin-top:8px;">
                        <span class="badge-det"
                              style="background:<?=$stBg[$st]?>;color:<?=$stColor[$st]?>;">
                            <?=$stLabel[$st]?>
                        </span>
                    </div>
                </div>

                <!-- Summary slot -->
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <div style="text-align:center;">
                        <div style="font-size:28px;font-weight:800;color:#1A3C34;"><?=$kuota?></div>
                        <div style="font-size:12px;color:#6B8A7E;">Total Slot</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:28px;font-weight:800;color:#1D9E75;"><?=$peserta?></div>
                        <div style="font-size:12px;color:#6B8A7E;">Terdaftar</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:28px;font-weight:800;color:<?=$sisa===0?'#D95D39':'#1A3C34'?>;">
                            <?=$sisa?>
                        </div>
                        <div style="font-size:12px;color:#6B8A7E;">Sisa Slot</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD DETAIL -->
    <div class="setting-content-area">
        <div class="settings-card password-card">
            <div class="card-accent"></div>
            <div class="card-body-inner password-section">
                <h2 class="section-title">Detail Jadwal</h2>

                <div class="detail-grid">
                    <div class="field-group">
                        <label class="field-label">Tanggal</label>
                        <input type="text" class="field-input" value="<?=$hariLabel?>, <?=$tglLabel?>" readonly disabled>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Status</label>
                        <input type="text" class="field-input" value="<?=$stLabel[$st]?>" readonly disabled>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Waktu Mulai</label>
                        <input type="text" class="field-input" value="<?=$wMul?>" readonly disabled>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Waktu Selesai</label>
                        <input type="text" class="field-input" value="<?=$wSel?>" readonly disabled>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Kuota Slot</label>
                        <input type="text" class="field-input" value="<?=$kuota?> slot" readonly disabled>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Peserta Terdaftar</label>
                        <input type="text" class="field-input" value="<?=$peserta?> pengguna" readonly disabled>
                    </div>
                </div>

                <!-- Kuota bar -->
                <div class="field-group" style="margin-top:8px;">
                    <label class="field-label">Kapasitas Terpakai (<?=$pct?>%)</label>
                    <div class="kuota-bar-big">
                        <?php $barCls=$pct>=100?'full':($pct>=70?'warn':''); ?>
                        <div class="kuota-fill-big <?=$barCls?>" style="width:<?=$pct?>%;"></div>
                    </div>
                    <div style="font-size:12px;color:#6B8A7E;margin-top:6px;">
                        <?=$peserta?> dari <?=$kuota?> slot terisi
                        <?php if($sisa===0):?> — <span style="color:#D95D39;font-weight:700;">PENUH</span><?php endif;?>
                    </div>
                </div>

                <!-- Tombol aksi -->
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
                    <a href="jadwal_ambil_sampah.php" class="btn-submit"
                       style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;background:#6B8A7E;box-shadow:none;">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <?php if(in_array($userRole,['bank sampah','admin','dlh'])&&$st!=='selesai'):?>
                    <a href="edit_jadwal.php?id=<?=urlencode($idJadwal)?>" class="btn-submit"
                       style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                        <i class="bi bi-pencil-square"></i> Edit Jadwal
                    </a>
                    <?php endif;?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>