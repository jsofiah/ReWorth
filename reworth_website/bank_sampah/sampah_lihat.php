<?php
session_start();
if (!isset($_SESSION['role'])) { header("Location: ../login.php"); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userName  = $_SESSION['nama_admin']  ?? 'User';
$userEmail = $_SESSION['email']       ?? '';
$userRole  = $_SESSION['role']        ?? '';
$userFoto  = $_SESSION['foto_profil'] ?? '';

$id = trim($_GET['id'] ?? '');
if (empty($id)) { header("Location: data_sampah.php"); exit; }

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
function formatRupiah($n) { return 'Rp ' . number_format((float)($n ?? 0), 0, ',', '.'); }

/* ── fetch jenis sampah ── */
$rows = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/jenis_sampah?id_jenis=eq." . urlencode($id) . "&select=*&limit=1");
if (empty($rows)) { header("Location: data_sampah.php"); exit; }
$s = $rows[0];

/* ── fetch riwayat pemakaian dari detail_setor ── */
$riwayat = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/detail_setor?id_jenis=eq." . urlencode($id) .
    "&select=berat_kg,subtotal,setor_sampah(tanggal_setor,pengguna(nama_lengkap))&order=setor_sampah(tanggal_setor).desc&limit=5");

$canEdit = in_array($userRole, ['bank sampah', 'admin', 'dlh']);

$tanggalDibuat  = !empty($s['created_at'])  ? date('d M Y', strtotime($s['created_at']))  : '-';
$tanggalDiubah  = !empty($s['updated_at'])  ? date('d M Y', strtotime($s['updated_at']))  : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Detail Jenis Sampah</title>
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
            margin-bottom: 6px;
        }
        .card-subtitle {
            font-size: 13px;
            color: #6B8A7E;
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
        .field-readonly.highlight {
            color: var(--green);
            font-weight: 700;
            font-size: 16px;
        }

        /* ── 2-col row ── */
        .row-2cols { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 24px; }
        @media(max-width:640px){ .row-2cols{ grid-template-columns:1fr; } }

        /* ── section divider ── */
        .section-divider {
            border: none;
            border-top: 1.5px solid #E8F0EC;
            margin: 8px 0 24px;
        }
        .section-label {
            font-size: 12px; font-weight: 700; color: #2C3E2F;
            letter-spacing: .6px; text-transform: uppercase;
            margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-label i { color: var(--green); font-size: 14px; }

        /* ── harga badge ── */
        .harga-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #EEF5F1;
            border-radius: 12px;
            padding: 10px 18px;
            font-size: 22px;
            font-weight: 800;
            color: var(--green);
            margin-bottom: 28px;
        }
        .harga-badge small {
            font-size: 13px;
            font-weight: 500;
            color: #6B8A7E;
        }

        /* ── riwayat table ── */
        .riwayat-wrap {
            border: 1.5px solid #D8EDE6;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 28px;
        }
        .riwayat-head {
            display: grid;
            grid-template-columns: 1fr 120px 150px;
            background: var(--btn-lihat);
            padding: 12px 14px;
            gap: 10px;
        }
        .riwayat-head span {
            font-size: 12px; font-weight: 700; color: #fff;
            text-align: center; letter-spacing: .3px;
        }
        .riwayat-head span:first-child { text-align: left; }
        .riwayat-body { background: #fff; }
        .riwayat-row {
            display: grid;
            grid-template-columns: 1fr 120px 150px;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #EEF5F1;
            font-size: 13px;
        }
        .riwayat-row:last-child { border-bottom: none; }
        .riwayat-row .col-center { text-align: center; color: #9AA7A2; }
        .riwayat-row .col-money  { text-align: center; color: var(--green); font-weight: 700; }
        .empty-state {
            padding: 24px;
            text-align: center;
            color: #9AA7A2;
            font-size: 13px;
        }

        /* ── actions ── */
        .card-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 24px;
            border-top: 1.5px solid #E8F0EC;
            margin-top: 8px;
        }
        .btn-back {
            padding: 11px 28px;
            border-radius: 12px;
            border: 1.5px solid #D2E0D8;
            background: #fff;
            font-size: 13px; font-weight: 700;
            color: #6B8A7E; cursor: pointer;
            font-family: inherit; transition: .2s;
        }
        .btn-back:hover { border-color: var(--green); color: var(--green); }
        .btn-edit-sampah {
            padding: 11px 28px;
            border-radius: 12px;
            border: none;
            background: var(--green);
            color: #fff; font-size: 13px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            box-shadow: 0 4px 14px rgba(0,145,110,.3);
            transition: .2s; display: flex; align-items: center; gap: 6px;
        }
        .btn-hapus-sampah {
            padding: 11px 28px;
            border-radius: 12px;
            border: 1.5px solid #D95D39;
            background: #fff;
            color: #D95D39; font-size: 13px; font-weight: 700;
            cursor: pointer; font-family: inherit; transition: .2s;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-hapus-sampah:hover { background: #D95D39; color: #fff; }

        /* ── form-wrap (from sampah_tambah pattern) ── */
        .form-wrap {
            display: flex;
            justify-content: center;
            padding: 0 40px 40px;
        }
    </style>
</head>
<body>
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
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): $fu = getSupabaseImageUrl($userFoto); ?>
                        <img src="<?= htmlspecialchars($fu) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-fill" style="display:none;"></i>
                    <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-wrap">
        <div class="detail-card">
            <div class="card-accent-bar"></div>
            <div class="card-inner">

                <!-- Judul -->
                <div class="card-title"><?= htmlspecialchars($s['nama_sampah'] ?? '-') ?></div>
                <div class="card-subtitle">
                    <i class="bi bi-trash-fill me-1"></i> Detail Jenis Sampah
                </div>

                <!-- Harga badge -->
                <div class="harga-badge">
                    <i class="bi bi-tag-fill"></i>
                    <?= formatRupiah($s['harga_per_kg'] ?? 0) ?>
                    <small>/ kg</small>
                </div>

                <!-- Info dasar -->
                <div class="section-label"><i class="bi bi-info-circle-fill"></i> Informasi Sampah</div>
                <div class="row-2cols">
                    <div>
                        <label class="field-label">Nama Jenis Sampah</label>
                        <input class="field-readonly" value="<?= htmlspecialchars($s['nama_sampah'] ?? '-') ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label">Harga per Kg (Rp)</label>
                        <input class="field-readonly highlight" value="<?= formatRupiah($s['harga_per_kg'] ?? 0) ?>" readonly>
                    </div>
                </div>

                <div class="row-2cols">
                    <div>
                        <label class="field-label">Tanggal Ditambahkan</label>
                        <input class="field-readonly" value="<?= $tanggalDibuat ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label">Terakhir Diperbarui</label>
                        <input class="field-readonly" value="<?= $tanggalDiubah ?>" readonly>
                    </div>
                </div>

                <hr class="section-divider">

                <!-- Riwayat pemakaian -->
                <div class="section-label"><i class="bi bi-clock-history"></i> Riwayat Pemakaian (5 Terakhir)</div>
                <div class="riwayat-wrap">
                    <div class="riwayat-head">
                        <span>Nasabah</span>
                        <span>Berat (kg)</span>
                        <span>Subtotal</span>
                    </div>
                    <div class="riwayat-body">
                        <?php if (!empty($riwayat)): ?>
                            <?php foreach ($riwayat as $r): ?>
                                <?php
                                    $namaLengkap = $r['setor_sampah']['pengguna']['nama_lengkap'] ?? '-';
                                    $tglSetor    = !empty($r['setor_sampah']['tanggal_setor'])
                                        ? date('d M Y', strtotime($r['setor_sampah']['tanggal_setor'])) : '-';
                                ?>
                                <div class="riwayat-row">
                                    <div>
                                        <div style="font-weight:600;color:#2C3E2F;"><?= htmlspecialchars($namaLengkap) ?></div>
                                        <div style="font-size:11px;color:#9AA7A2;"><?= $tglSetor ?></div>
                                    </div>
                                    <div class="col-center"><?= number_format((float)($r['berat_kg'] ?? 0), 2, ',', '.') ?> kg</div>
                                    <div class="col-money"><?= formatRupiah($r['subtotal'] ?? 0) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                Belum ada riwayat pemakaian
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card-actions">
                    <button class="btn-back" onclick="window.location.href='data_sampah.php'">
                        <i class="bi bi-arrow-left me-1"></i> KEMBALI
                    </button>
                </div>

            </div><!-- /card-inner -->
        </div><!-- /detail-card -->
    </div><!-- /form-wrap -->
</div><!-- /main-wrap -->

<!-- ── Modal Hapus ── -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
        <div class="confirm-text">
            <h3>Hapus Jenis Sampah?</h3>
            <p><strong>"<?= htmlspecialchars($s['nama_sampah'] ?? '') ?>"</strong> akan dihapus secara permanen dan tidak bisa dikembalikan.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
            <button id="btnConfirmHapus" class="btn-aksi btn-hapus"
                    style="padding:10px 22px;font-size:14px;border-radius:12px;"
                    onclick="confirmHapus()">
                <i class="bi bi-trash3"></i> Ya, Hapus
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SB_URL   = <?= json_encode($supabaseUrl) ?>;
const SB_KEY   = <?= json_encode($supabaseKey) ?>;
const JENIS_ID = <?= json_encode($id) ?>;

/* ── modals ── */
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); })
);

/* ── hapus ── */
async function confirmHapus() {
    const btn = document.getElementById('btnConfirmHapus');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menghapus...';

    const res = await fetch(SB_URL + '/rest/v1/jenis_sampah?id_jenis=eq.' + JENIS_ID, {
        method : 'DELETE',
        headers: {
            'apikey'       : SB_KEY,
            'Authorization': 'Bearer ' + SB_KEY
        }
    });

    if (res.ok) {
        showToast('Jenis sampah berhasil dihapus.', 'success');
        setTimeout(() => window.location.href = 'data_sampah.php', 900);
    } else {
        showToast('Gagal menghapus. Mungkin data masih digunakan.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash3"></i> Ya, Hapus';
        closeModal('modalHapus');
    }
}

/* ── toast ── */
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