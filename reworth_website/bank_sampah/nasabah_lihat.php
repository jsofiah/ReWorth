<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$userName=$_SESSION['nama_admin']??'User'; $userEmail=$_SESSION['email']??''; $userRole=$_SESSION['role']??''; $userFoto=$_SESSION['foto_profil']??'';

$id=trim($_GET['id']??'');
if(empty($id)){header("Location: data_nasabah.php");exit;}

function getSupabaseImageUrl($p){return empty($p)?null:"https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');}
function sbGet($url,$key,$ep){$ch=curl_init($url.$ep);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["apikey:$key","Authorization:Bearer $key","Content-Type:application/json"]]);$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $c===200?(json_decode($r,true)?:[]):[];}
function fmtRp($n){return 'Rp'.number_format((float)$n,0,',','.');}

$rows=sbGet($supabaseUrl,$supabaseKey,"/rest/v1/pengguna?id_pengguna=eq.".urlencode($id)."&select=*,wilayah(rw,kelurahan,kecamatan,kota)&limit=1");
if(empty($rows)){header("Location: data_nasabah.php");exit;}
$n=$rows[0]; $wil=$n['wilayah']??[];
$fotoUrl = !empty($n['foto_profil']) ? getSupabaseImageUrl($n['foto_profil']) : null;
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Detail Nasabah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/root.css">
<style>
.detail-wrap{display:flex;justify-content:center;padding:0 40px 40px;}
.detail-content{width:100%;max-width:900px;margin-top:-60px;z-index:10;position:relative;}

/* profile card */
.profile-card{background:#fff;border-radius:20px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,.07);display:flex;gap:28px;align-items:flex-start;margin-bottom:16px;}
.profile-photo{width:180px;height:180px;border-radius:16px;object-fit:cover;flex-shrink:0;background:#EEF5F1;display:flex;align-items:center;justify-content:center;font-size:64px;color:#9AA7A2;overflow:hidden;}
.profile-photo img{width:100%;height:100%;object-fit:cover;border-radius:16px;}
.profile-info{flex:1;}
.profile-name{font-size:32px;font-weight:800;color:#1A2E24;margin-bottom:4px;line-height:1.2;}
.profile-email{font-size:14px;color:#6B8A7E;margin-bottom:20px;}
.info-boxes{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.info-box{border-radius:14px;padding:16px 20px;position:relative;overflow:hidden;}
.info-box-saldo{background:linear-gradient(135deg,#D4EDDA 0%,#B8E0C2 100%);}
.info-box-poin{background:linear-gradient(135deg,#F5DEB3 0%,#E8B96A 100%);}
.info-box-label{font-size:12px;font-weight:600;color:rgba(0,0,0,.55);margin-bottom:6px;}
.info-box-value{font-size:26px;font-weight:800;color:#1A2E24;}
.info-box-icon{position:absolute;right:14px;bottom:10px;font-size:32px;opacity:.35;}

/* profil detail card */
.profil-card{background:#fff;border-radius:20px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,.07);}
.profil-title{font-size:22px;font-weight:800;margin-bottom:24px;}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-bottom:20px;}
.row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;}
.field-label{font-size:11px;font-weight:700;color:#6B8A7E;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;display:block;}
.field-val{font-size:14px;color:#9AA7A2;padding-bottom:10px;border-bottom:1.5px solid #E8F0EC;}
.action-bar-lihat{background:#fff;border-radius:20px;box-shadow:0 8px 32px rgba(0,145,110,.12);padding:14px 24px;display:flex;gap:14px;align-items:center;}
.btn-back{display:flex;align-items:center;gap:8px;padding:10px 20px;border:1.5px solid var(--border);border-radius:12px;background:#fff;color:#6B8A7E;font-size:14px;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;}
.btn-back:hover{border-color:var(--green);color:var(--green);}
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
            <h1 class="topbar-title">Detail Nasabah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info"><div class="topbar-user-name"><?=htmlspecialchars($userName)?></div><div class="topbar-user-email"><?=htmlspecialchars($userEmail)?></div></div>
                <div class="topbar-avatar">
                    <?php if(!empty($userFoto)):$fu=getSupabaseImageUrl($userFoto);?><img src="<?=htmlspecialchars($fu)?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><i class="bi bi-person-fill" style="display:none;"></i><?php else:?><i class="bi bi-person-fill"></i><?php endif;?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action bar back -->
    <div class="action-bar-wrap">
        <div class="action-bar-lihat">
            <a href="data_nasabah.php" class="btn-back"><i class="bi bi-arrow-left"></i> Kembali</a>
            <?php if(in_array($userRole,['bank sampah','admin','dlh'])):?>
            <button class="btn-aksi btn-edit" onclick="window.location.href='nasabah_edit.php?id=<?=$id?>'">
                <i class="bi bi-pencil-square"></i> Edit Data
            </button>
            <?php endif;?>
        </div>
    </div>

    <div class="detail-wrap">
        <div class="detail-content">

            <!-- Profile card -->
            <div class="profile-card">
                <div class="profile-photo">
                    <?php if($fotoUrl):?><img src="<?=htmlspecialchars($fotoUrl)?>" alt="Foto"><?php else:?><i class="bi bi-person-fill"></i><?php endif;?>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?=htmlspecialchars($n['nama_lengkap']??'-')?></div>
                    <div class="profile-email"><?=htmlspecialchars($n['email']??'-')?></div>
                    <div class="info-boxes">
                        <div class="info-box info-box-saldo">
                            <div class="info-box-label">Saldo Tabungan</div>
                            <div class="info-box-value"><?=fmtRp($n['saldo_tabungan']??0)?></div>
                            <i class="bi bi-piggy-bank-fill info-box-icon"></i>
                        </div>
                        <div class="info-box info-box-poin">
                            <div class="info-box-label">Poin Terkumpul</div>
                            <div class="info-box-value"><?=number_format((int)($n['poin']??0),0,',','.')?></div>
                            <i class="bi bi-coin info-box-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profil detail card -->
            <div class="profil-card">
                <div class="profil-title">Profil Detail</div>
                <div class="row-2">
                    <div><label class="field-label">Nama Lengkap</label><div class="field-val"><?=htmlspecialchars($n['nama_lengkap']??'-')?></div></div>
                    <div><label class="field-label">Email</label><div class="field-val"><?=htmlspecialchars($n['email']??'-')?></div></div>
                </div>
                <div class="row-2">
                    <div><label class="field-label">Nomer Telp</label><div class="field-val"><?=htmlspecialchars($n['no_telepon']??'-')?></div></div>
                    <div><label class="field-label">Alamat Tempat Tinggal</label><div class="field-val"><?=htmlspecialchars($n['alamat_detail']??'-')?></div></div>
                </div>
                <div class="row-3">
                    <div><label class="field-label">Kecamatan</label><div class="field-val"><?=htmlspecialchars($wil['kecamatan']??'-')?></div></div>
                    <div><label class="field-label">Kelurahan</label><div class="field-val"><?=htmlspecialchars($wil['kelurahan']??'-')?></div></div>
                    <div><label class="field-label">RW</label><div class="field-val"><?=htmlspecialchars($wil['rw']??'-')?></div></div>
                </div>
            </div>

        </div>
    </div>
</div>
</body></html>