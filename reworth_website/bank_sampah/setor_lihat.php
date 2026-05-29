<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? 'user@example.com';
$userRole  = $_SESSION['role']        ?? '';
$userFoto  = $_SESSION['foto_profil'] ?? '';
$userId    = $_SESSION['id_admin']    ?? '';

$id = trim($_GET['id'] ?? '');
if (empty($id)) { header("Location: transaksi_setor_sampah.php"); exit; }

function getSupabaseImageUrl($p) {
    return empty($p) ? null : "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/".ltrim($p,'/');
}
function sbGet($url, $key, $ep) {
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}
function formatRupiah($n) { return 'Rp' . number_format((float)($n ?? 0), 0, ',', '.'); }

/* ── fetch data ── */
$rows = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?id_setor=eq." . urlencode($id) .
    "&select=*,pengguna(id_pengguna,nama_lengkap,alamat_detail,saldo_tabungan),jadwal_ambil(id_jadwal,tanggal,waktu_mulai,waktu_selesai)&limit=1");
if (empty($rows)) { header("Location: transaksi_setor_sampah.php"); exit; }

$setor    = $rows[0];
$pengguna = $setor['pengguna']     ?? [];
$jadwal   = $setor['jadwal_ambil'] ?? [];
$details  = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/detail_setor?id_setor=eq." . urlencode($id) .
    "&select=*,jenis_sampah(nama_sampah)");

$status       = $setor['status'] ?? 'menunggu';
$namaPenyetor = $pengguna['nama_lengkap'] ?? '-';
$idPengguna   = $pengguna['id_pengguna']  ?? '';

/* ── jadwal label ── */
$jadwalLabel = '-';
if (!empty($jadwal['tanggal'])) {
    $jadwalLabel = date('d M Y', strtotime($jadwal['tanggal']))
        . ' ' . substr($jadwal['waktu_mulai'] ?? '', 0, 5)
        . ' - ' . substr($jadwal['waktu_selesai'] ?? '', 0, 5);
}

/* ── status map ── */
$statusMap = [
    'menunggu' => ['label' => 'Menunggu Konfirmasi', 'color' => '#D95D39'],
    'diproses' => ['label' => 'Diproses',            'color' => '#DBC729'],
    'selesai'  => ['label' => 'Selesai',             'color' => '#8EA604'],
    'ditolak'  => ['label' => 'Ditolak',             'color' => '#D95D39'],
];
$statusInfo = $statusMap[$status] ?? $statusMap['menunggu'];
$canEdit    = in_array($userRole, ['bank sampah', 'admin', 'dlh']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Detail Setor Sampah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <style>
        /* ── main card ── */
        .detail-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 6px 28px rgba(0,0,0,.07);
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 860px;
            margin: -56px auto 0;
            z-index: 10;
        }
        .card-accent-bar {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 7px;
            background: #ED985A;
            border-radius: 20px 0 0 20px;
        }
        .card-inner {
            padding: 32px 36px 32px 44px;
        }
        .card-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 28px;
        }

        /* ── read-only fields ── */
        .field-label {
            font-size: 12px;
            font-weight: 700;
            color: #2C3E2F;
            letter-spacing: .6px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .field-readonly {
            width: 100%;
            border: none;
            border-bottom: 2px solid #D6DEDA;
            background: transparent;
            padding: 4px 0 10px;
            font-size: 14px;
            font-family: inherit;
            color: #9AA7A2;
            outline: none;
            pointer-events: none;
        }
        .field-select-readonly {
            width: 100%;
            padding: 11px 40px 11px 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: #9AA7A2;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236B8A7E' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat right 14px center;
            appearance: none;
            pointer-events: none;
        }

        /* ── 2-col row ── */
        .row-2cols { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 24px; }
        @media(max-width:640px){ .row-2cols{ grid-template-columns:1fr; } }

        /* ── section label ── */
        .section-label {
            font-size: 12px; font-weight: 700; color: #2C3E2F;
            letter-spacing: .6px; text-transform: uppercase;
            margin-bottom: 14px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .total-label { font-size: 15px; font-weight: 700; color: var(--text-main); }
        .total-label span { color: var(--green); }

        /* ── detail table ── */
        .detail-wrap {
            border: 1.5px solid #D8EDE6;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 28px;
        }
        .detail-head {
            display: grid;
            grid-template-columns: 1fr 130px 150px 150px;
            background: var(--btn-lihat);
            padding: 12px 14px;
            gap: 10px;
        }
        .detail-head span {
            font-size: 12px; font-weight: 700; color: #fff;
            text-align: center; letter-spacing: .3px;
        }
        .detail-head span:first-child { text-align: left; }
        .detail-body { background: #fff; }
        .detail-row {
            display: grid;
            grid-template-columns: 1fr 130px 150px 150px;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #EEF5F1;
        }
        .detail-row:last-child { border-bottom: none; }

        /* ── cell readonly boxes ── */
        .cell-box {
            width: 100%; padding: 8px 10px;
            border: 1.5px solid #E2EDE8;
            border-radius: 10px;
            font-size: 13px; font-family: inherit;
            color: #9AA7A2; background: #fff;
            text-align: center;
        }
        .cell-select-box {
            width: 100%; padding: 8px 32px 8px 12px;
            border: 1.5px solid #E2EDE8;
            border-radius: 10px;
            font-size: 13px; font-family: inherit;
            color: #9AA7A2; background: #fff
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%236B8A7E' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E")
                no-repeat right 10px center;
            appearance: none;
            pointer-events: none;
        }

        /* ── actions ── */
        .card-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            padding-top: 4px;
        }
        .btn-valid {
            padding: 12px 52px;
            border: none; border-radius: 12px;
            background: var(--green);
            color: #fff;
            font-size: 13px; font-weight: 700;
            letter-spacing: .8px;
            cursor: pointer; font-family: inherit;
            box-shadow: 0 4px 16px rgba(0,145,110,.3);
            transition: all .2s;
        }
        .btn-valid:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(0,145,110,.4); }
        .btn-valid:disabled { opacity: .6; pointer-events: none; }
        .btn-selesai {
            padding: 12px 44px;
            border: none; border-radius: 12px;
            background: #8EA604;
            color: #fff;
            font-size: 13px; font-weight: 700;
            letter-spacing: .8px;
            cursor: pointer; font-family: inherit;
            box-shadow: 0 4px 14px rgba(142,166,4,.3);
            transition: all .2s;
        }
        .btn-selesai:hover { transform: translateY(-1px); }
        .btn-selesai:disabled { opacity: .6; pointer-events: none; }
        .btn-outline-red {
            padding: 12px 28px;
            border: 1.5px solid var(--btn-hapus);
            border-radius: 12px;
            background: #fff;
            color: var(--btn-hapus);
            font-size: 13px; font-weight: 700;
            letter-spacing: .5px;
            cursor: pointer; font-family: inherit;
            transition: all .2s;
        }
        .btn-outline-red:hover { background: var(--btn-hapus); color: #fff; }

        /* status info line */
        .status-line {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; font-weight: 600;
            padding: 6px 16px; border-radius: 30px;
            border: 1.5px solid;
        }

        /* form wrap */
        .form-wrap { display: flex; justify-content: center; padding: 0 40px 40px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="img/logo.png" alt="Logo ReWorth">
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link-custom">
                <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="transaksi_setor_sampah.php" class="nav-link-custom active">
                <i class="bi bi-recycle"></i><span>Transaksi Setor Sampah</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="penarikan_saldo.php" class="nav-link-custom">
                <i class="bi bi-wallet2"></i><span>Penarikan Saldo</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="event_lingkungan.php" class="nav-link-custom">
                <i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="laporan_keuangan.php" class="nav-link-custom">
                <i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Keuangan</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="data_nasabah.php" class="nav-link-custom">
                <i class="bi bi-people-fill"></i><span>Data Nasabah</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="data_sampah.php" class="nav-link-custom">
                <i class="bi bi-trash-fill"></i><span>Data Sampah</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="pengaturan_akun.php" class="nav-link-custom">
                <i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-logout">
        <a class="logout-btn" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </div>
</aside>

<div class="main-wrap">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-inner">
            <h1 class="topbar-title">Transaksi Setor Sampah</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if(!empty($userFoto)): $fu = getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fu) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="form-wrap">
        <div class="detail-card">

            <!-- accent bar -->
            <div class="card-accent-bar"></div>

            <div class="card-inner">
                <div class="card-title">Detail Setor Sampah</div>

                <!-- Row 1: Nama + Jadwal -->
                <div class="row-2cols">
                    <div>
                        <label class="field-label">Nama Penyetor</label>
                        <input type="text" class="field-readonly"
                            value="<?= htmlspecialchars($namaPenyetor) ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label">Jadwal Ambil</label>
                        <div class="field-select-readonly">
                            <?= htmlspecialchars($jadwalLabel) ?>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Alamat -->
                <div style="margin-bottom:28px;">
                    <label class="field-label">Alamat Setor</label>
                    <input type="text" class="field-readonly"
                        value="<?= htmlspecialchars($setor['alamat'] ?? '-') ?>" readonly>
                </div>

                <!-- Detail Setor Sampah -->
                <div class="section-label">
                    <span>Detail Setor Sampah</span>
                    <span class="total-label">
                        Total Uang: <span><?= formatRupiah($setor['total_uang'] ?? 0) ?></span>
                    </span>
                </div>

                <div class="detail-wrap">
                    <div class="detail-head">
                        <span>Jenis Sampah</span>
                        <span>Berat (kg)</span>
                        <span>Harga / kg (Rp)</span>
                        <span>Subtotal</span>
                    </div>
                    <div class="detail-body">
                        <?php if (!empty($details)): ?>
                            <?php foreach ($details as $d): ?>
                            <div class="detail-row">
                                <!-- Jenis Sampah -->
                                <div class="cell-select-box">
                                    <?= htmlspecialchars($d['jenis_sampah']['nama_sampah'] ?? '-') ?>
                                </div>
                                <!-- Berat -->
                                <div class="cell-box">
                                    <?= number_format((float)($d['berat'] ?? 0), 1, ',', '.') ?>
                                </div>
                                <!-- Harga/kg -->
                                <div class="cell-box">
                                    <?= number_format((float)($d['harga_per_kg'] ?? 0), 0, ',', '.') ?>
                                </div>
                                <!-- Subtotal -->
                                <div class="cell-box">
                                    <?= number_format((float)($d['subtotal'] ?? 0), 0, ',', '.') ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center;padding:24px;color:#9AA7A2;font-size:13px;">
                                <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:6px;"></i>
                                Belum ada detail sampah
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <?php if ($canEdit): ?>
                <div class="card-actions">

                    <?php if ($status === 'menunggu'): ?>
                        <button class="btn-outline-red" onclick="openModal('modalTolak')">
                            TOLAK
                        </button>
                        <button id="btnValid" class="btn-valid" onclick="openModal('modalValid')">
                            DATA VALID
                        </button>

                    <?php elseif ($status === 'diproses'): ?>
                        <button class="btn-outline-red" onclick="openModal('modalTolak')">
                            TOLAK
                        </button>
                        <button id="btnSelesai" class="btn-selesai" onclick="openModal('modalSelesai')">
                            TANDAI SELESAI
                        </button>

                    <?php else: ?>
                        <span class="status-line"
                            style="color:<?= $statusInfo['color'] ?>;border-color:<?= $statusInfo['color'] ?>;background:<?= $statusInfo['color'] ?>18;">
                            <i class="bi <?= $status==='selesai' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                            <?= $statusInfo['label'] ?>
                        </span>
                    <?php endif; ?>

                </div>
                <?php endif; ?>

            </div><!-- /card-inner -->
        </div><!-- /detail-card -->
    </div><!-- /form-wrap -->
</div><!-- /main-wrap -->

<!-- ══ MODAL: DATA VALID (konfirmasi → diproses) ══ -->
<div class="modal-overlay" id="modalValid">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon" style="background:rgba(0,145,110,.1);">
            <i class="bi bi-patch-check-fill" style="color:var(--green);font-size:28px;"></i>
        </div>
        <div class="confirm-text">
            <h3>Data Valid?</h3>
            <p>Transaksi setor sampah atas nama <strong><?= htmlspecialchars($namaPenyetor) ?></strong>
               akan dikonfirmasi dan statusnya menjadi <strong>Diproses</strong>.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalValid')">Batal</button>
            <button id="btnConfirmValid" class="btn-valid" style="padding:10px 28px;"
                onclick="submitStatus('diproses','btnConfirmValid')">
                <i class="bi bi-check-lg me-1"></i> Ya, Konfirmasi
            </button>
        </div>
    </div>
</div>

<!-- ══ MODAL: TANDAI SELESAI ══ -->
<div class="modal-overlay" id="modalSelesai">
    <div class="modal-box" style="max-width:420px;">
        <div class="confirm-icon" style="background:rgba(142,166,4,.1);">
            <i class="bi bi-check-all" style="color:#8EA604;font-size:28px;"></i>
        </div>
        <div class="confirm-text">
            <h3>Tandai Selesai?</h3>
            <p>Saldo <strong><?= formatRupiah($setor['total_uang'] ?? 0) ?></strong> akan
               otomatis ditambahkan ke tabungan <strong><?= htmlspecialchars($namaPenyetor) ?></strong>.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalSelesai')">Batal</button>
            <button id="btnConfirmSelesai" class="btn-selesai" style="padding:10px 28px;"
                onclick="submitStatus('selesai','btnConfirmSelesai')">
                <i class="bi bi-check-all me-1"></i> Ya, Selesaikan
            </button>
        </div>
    </div>
</div>

<!-- ══ MODAL: TOLAK ══ -->
<div class="modal-overlay" id="modalTolak">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-title">
            Tolak Transaksi
            <button class="modal-close" onclick="closeModal('modalTolak')"><i class="bi bi-x-lg"></i></button>
        </div>
        <p style="font-size:13px;color:#6B8A7E;margin-bottom:16px;">
            Transaksi atas nama <strong><?= htmlspecialchars($namaPenyetor) ?></strong> akan ditolak.
            Nasabah akan mendapat notifikasi.
        </p>
        <div class="form-group">
            <label class="form-label">Alasan Penolakan <span style="color:#D95D39;">*</span></label>
            <textarea id="alasanTolak" class="form-control-custom" rows="3"
                placeholder="Masukkan alasan penolakan..." style="resize:vertical;"></textarea>
            <small id="errAlasan" style="color:#D95D39;font-size:12px;display:none;">Alasan wajib diisi.</small>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('modalTolak')">Batal</button>
            <button id="btnConfirmTolak" class="btn-aksi btn-hapus"
                style="padding:10px 22px;font-size:14px;border-radius:12px;"
                onclick="submitTolak()">
                <i class="bi bi-x-circle"></i> Ya, Tolak
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SETOR_ID    = <?= json_encode($id) ?>;
const NAMA        = <?= json_encode($namaPenyetor) ?>;
const ID_PENGGUNA = <?= json_encode($idPengguna) ?>;
const TOTAL_UANG  = <?= json_encode((string)($setor['total_uang'] ?? '0')) ?>;

/* ── modals ── */
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); })
);

/* ── update status (diproses / selesai) ── */
function submitStatus(status, btnId) {
    const btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

    fetch('setor_update.php', {
    method : 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body   : new URLSearchParams({
        id: SETOR_ID,
        status,
        nama: NAMA,
        id_pengguna: ID_PENGGUNA,
        total_uang: TOTAL_UANG
    })
})
.then(r => r.text())
.then(data => {
    console.log(data);
    alert(data);
})
.catch(err => {
    console.log(err);
    alert(err);
})
    .catch(() => {
        showToast('Terjadi kesalahan server.', 'error');
        btn.disabled = false;
    });
}

function submitTolak() {
    const alasan = document.getElementById('alasanTolak').value.trim();
    const errEl  = document.getElementById('errAlasan');
    if (!alasan) { errEl.style.display = 'block'; return; }
    errEl.style.display = 'none';

    const btn = document.getElementById('btnConfirmTolak');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

    fetch('setor_update.php', {
    method : 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body   : new URLSearchParams({
        id: SETOR_ID,
        status,
        nama: NAMA,
        id_pengguna: ID_PENGGUNA,
        total_uang: TOTAL_UANG
    })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Gagal update status.', 'error');
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan server.', 'error');
        btn.disabled = false;
    });
}

function showToast(msg, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
    const div   = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}
</script>
</body>
</html>