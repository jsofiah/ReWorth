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
    "&select=*,jenis_sampah(id_jenis,nama_sampah,harga_per_kg)");

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
$canEdit    = in_array($userRole, ['bank sampah', 'admin', 'dlh']) && $status !== 'selesai' && $status !== 'ditolak';
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
    <link rel="stylesheet" href="style/form.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
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
            <a href="jadwal_ambil_sampah.php" class="nav-link-custom">
                <i class="bi bi-calendar2-week-fill"></i><span>Jadwal Ambil Sampah</span>
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
            <h1 class="topbar-title">Detail Setor Sampah</h1>
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
                    <span class="total-label" id="totalLabel">
                        Total Uang: <span id="totalUangDisplay"><?= formatRupiah($setor['total_uang'] ?? 0) ?></span>
                    </span>
                </div>

                <div class="detail-wrap">
                    <div class="detail-head">
                        <span>Jenis Sampah</span>
                        <span>Berat (kg)</span>
                        <span>Harga / kg (Rp)</span>
                        <span>Subtotal</span>
                        <?php if ($canEdit): ?>
                            <span>Aksi</span>
                        <?php endif; ?>
                    </div>
                    <div class="detail-body" id="detailBody">
                        <?php if (!empty($details)): ?>
                            <?php foreach ($details as $index => $d): 
                                $detailId = $d['id_detail'];
                                $hargaDefault = $d['harga_per_kg'] ?? $d['jenis_sampah']['harga_per_kg'] ?? 0;
                            ?>
                            <div class="detail-row" data-id="<?= $detailId ?>" data-index="<?= $index ?>">
                                <div class="cell-select-box">
                                    <?= htmlspecialchars($d['jenis_sampah']['nama_sampah'] ?? '-') ?>
                                    <input type="hidden" class="jenis-sampah-id" value="<?= $d['jenis_sampah']['id_jenis_sampah'] ?? '' ?>">
                                </div>
                                <div class="cell-box berat-cell">
                                    <span class="berat-text"><?= number_format((float)($d['berat'] ?? 0), 1, ',', '.') ?></span>
                                </div>
                                <div class="cell-box harga-cell">
                                    <span class="harga-text"><?= number_format((float)($hargaDefault), 0, ',', '.') ?></span>
                                </div>
                                <div class="cell-box subtotal-cell">
                                    <span class="subtotal-text"><?= number_format((float)($d['subtotal'] ?? 0), 0, ',', '.') ?></span>
                                </div>
                                <?php if ($canEdit): ?>
                                    <div class="cell-box aksi-cell">
                                        <button class="btn-edit-row" onclick="editRow(this, '<?= $detailId ?>', <?= $index ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
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
                        <button id="btnValid" class="btn-valid" onclick="validateAndProceed()">
                            DATA VALID
                        </button>
                    <?php elseif ($status === 'diproses'): ?>
                        <button class="btn-outline-red" onclick="openModal('modalTolak')">
                            TOLAK
                        </button>
                        <button id="btnSelesai" class="btn-selesai" onclick="openModal('modalSelesai')">
                            TANDAI SELESAI
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div><!-- /card-inner -->
        </div><!-- /detail-card -->
    </div><!-- /form-wrap -->
</div><!-- /main-wrap -->

<!-- MODALS -->
<div class="modal-overlay" id="modalValid">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon" style="background:rgba(0,145,110,.1);">
            <i class="bi bi-patch-check-fill" style="color:var(--green);font-size:28px;"></i>
        </div>
        <div class="confirm-text">
            <h3>Validasi Data?</h3>
            <p>Transaksi setor sampah atas nama <strong><?= htmlspecialchars($namaPenyetor) ?></strong>
               akan langsung ditandai sebagai <strong>SELESAI</strong> dan saldo akan ditambahkan.</p>
            <p style="font-size:12px;color:#D95D39;margin-top:8px;">
                <i class="bi bi-info-circle"></i> Pastikan semua data detail sampah sudah benar!
            </p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalValid')">Batal</button>
            <button id="btnConfirmValid" class="btn-valid" onclick="submitStatus('selesai')">
                <i class="bi bi-check-lg me-1"></i> Ya, Validasi & Selesaikan
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalSelesai">
    <div class="modal-box" style="max-width:420px;">
        <div class="confirm-icon" style="background:rgba(142,166,4,.1);">
            <i class="bi bi-check-all" style="color:#8EA604;font-size:28px;"></i>
        </div>
        <div class="confirm-text">
            <h3>Tandai Selesai?</h3>
            <p>Saldo <strong id="modalTotalUang"><?= formatRupiah($setor['total_uang'] ?? 0) ?></strong> akan
               otomatis ditambahkan ke tabungan <strong><?= htmlspecialchars($namaPenyetor) ?></strong>.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalSelesai')">Batal</button>
            <button id="btnConfirmSelesai" class="btn-selesai" onclick="submitStatus('selesai')">
                <i class="bi bi-check-all me-1"></i> Ya, Selesaikan
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalTolak">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-title">
            Tolak Transaksi
            <button class="modal-close" onclick="closeModal('modalTolak')"><i class="bi bi-x-lg"></i></button>
        </div>
        <p style="font-size:13px;color:#6B8A7E;margin-bottom:16px;">
            Transaksi atas nama <strong><?= htmlspecialchars($namaPenyetor) ?></strong> akan ditolak.
        </p>
        <div class="form-group">
            <label class="form-label">Alasan Penolakan <span style="color:#D95D39;">*</span></label>
            <textarea id="alasanTolak" class="form-control-custom" rows="3"
                placeholder="Masukkan alasan penolakan..." style="resize:vertical;"></textarea>
            <small id="errAlasan" style="color:#D95D39;font-size:12px;display:none;">Alasan wajib diisi.</small>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('modalTolak')">Batal</button>
            <button id="btnConfirmTolak" class="btn-aksi btn-hapus" onclick="submitTolak()">
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

let currentTotalUang = <?= json_encode((float)($setor['total_uang'] ?? 0)) ?>;

function showToast(msg, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill' };
    const div = document.createElement('div');
    div.className = `toast-item ${type}`;
    div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(div);
    setTimeout(() => div.remove(), 3500);
}

function openModal(id) { 
    document.getElementById(id).classList.add('show'); 
}

function closeModal(id) { 
    document.getElementById(id).classList.remove('show'); 
}

document.querySelectorAll('.modal-overlay').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); })
);


let editingRows = {};


function editRow(btn, detailId, index) {
    const row = btn.closest('.detail-row');
    if (row.classList.contains('editing')) return;
    
    const beratSpan = row.querySelector('.berat-text');
    const hargaSpan = row.querySelector('.harga-text'); 
    
    const currentBerat = parseFloat(beratSpan.innerText.replace(/\./g, '').replace(',', '.'));
    const currentHarga = parseFloat(hargaSpan.innerText.replace(/\./g, ''));
    
    editingRows[detailId] = {
        berat: currentBerat,
        harga: currentHarga,
        beratSpan: beratSpan.innerHTML,
        hargaSpan: hargaSpan.innerHTML,
    };
    
    const beratCell = row.querySelector('.berat-cell');
    const hargaCell = row.querySelector('.harga-cell');
    const aksiCell = row.querySelector('.aksi-cell');
    
    
    beratCell.innerHTML = `<input type="number" step="0.1" class="edit-input berat-input" value="${currentBerat}" style="text-align:right;">`;
    
    aksiCell.innerHTML = `
        <button class="btn-save-row" onclick="saveRow(this, '${detailId}')">Simpan</button>
        <button class="btn-cancel-row" onclick="cancelEdit(this, '${detailId}')">Batal</button>
    `;
    
    row.classList.add('editing');
}

function cancelEdit(btn, detailId) {
    const row = btn.closest('.detail-row');
    const original = editingRows[detailId];
    if (!original) return;
    
    const beratCell = row.querySelector('.berat-cell');
    const aksiCell = row.querySelector('.aksi-cell');
    
    beratCell.innerHTML = `<span class="berat-text">${original.beratSpan}</span>`;
    aksiCell.innerHTML = `<button class="btn-edit-row" onclick="editRow(this, '${detailId}', ${row.dataset.index})">
                            <i class="bi bi-pencil"></i>
                          </button>`;
    
    row.classList.remove('editing');
    delete editingRows[detailId];
}

async function saveRow(btn, detailId) {
    const row = btn.closest('.detail-row');
    const beratInput = row.querySelector('.berat-input');
    const hargaText = row.querySelector('.harga-text').innerText; 
    
    const newBerat = parseFloat(beratInput.value);
    const currentHarga = parseFloat(hargaText.replace(/\./g, ''));
    const newSubtotal = newBerat * currentHarga;
    
    
    if (isNaN(newBerat) || newBerat <= 0) {
        showToast('Berat harus lebih dari 0', 'error');
        return;
    }
    
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
    
    try {
        const response = await fetch('setor_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'detail',
                id_detail: detailId,
                berat: newBerat,
                id_setor: SETOR_ID
            })
        });
        
        const result = await response.json();
        
        console.log('Result:', result);
        
        if (result.success) {
            const beratCell = row.querySelector('.berat-cell');
            const subtotalCell = row.querySelector('.subtotal-cell');
            const aksiCell = row.querySelector('.aksi-cell');
            
            
            beratCell.innerHTML = `<span class="berat-text">${newBerat.toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1})}</span>`;
            subtotalCell.innerHTML = `<span class="subtotal-text">${newSubtotal.toLocaleString('id-ID')}</span>`;
            aksiCell.innerHTML = `<button class="btn-edit-row" onclick="editRow(this, '${detailId}', ${row.dataset.index})">
                                    <i class="bi bi-pencil"></i>
                                  </button>`;
            
            row.classList.remove('editing');
            delete editingRows[detailId];
            
            
            if (result.new_total_uang !== undefined) {
                currentTotalUang = result.new_total_uang;
                document.getElementById('totalUangDisplay').innerHTML = formatRupiah(currentTotalUang);
                document.getElementById('modalTotalUang').innerHTML = formatRupiah(currentTotalUang);
            }
            
            showToast('Berat berhasil diperbarui', 'success');
        } else {
            showToast(result.message || 'Gagal menyimpan perubahan', 'error');
            cancelEdit(btn, detailId);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Terjadi kesalahan server', 'error');
        cancelEdit(btn, detailId);
    } finally {
        btn.disabled = false;
    }
}

function formatRupiah(amount) {
    return 'Rp' + amount.toLocaleString('id-ID');
}

function validateAndProceed() {
    if (Object.keys(editingRows).length > 0) {
        showToast('Harap simpan atau batalkan perubahan terlebih dahulu', 'warning');
        return;
    }
    openModal('modalValid');
}

async function submitStatus(status) {
    const btnId = status === 'selesai' ? 'btnConfirmValid' : 'btnConfirmSelesai';
    const btn = document.getElementById(btnId);
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
    
    try {
        const response = await fetch('setor_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'status',  
                id: SETOR_ID,
                status: status,
                nama: NAMA,
                id_pengguna: ID_PENGGUNA,
                total_uang: currentTotalUang
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'transaksi_setor_sampah.php';
            }, 1500);
        } else {
            showToast(data.message || 'Gagal update status.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Terjadi kesalahan server.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function submitTolak() {
    const alasan = document.getElementById('alasanTolak').value.trim();
    const errEl = document.getElementById('errAlasan');
    
    if (!alasan) {
        errEl.style.display = 'block';
        return;
    }
    errEl.style.display = 'none';
    
    const btn = document.getElementById('btnConfirmTolak');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
    
    try {
        const response = await fetch('setor_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'status',  
                id: SETOR_ID,
                status: 'ditolak',
                alasan: alasan,
                nama: NAMA,
                id_pengguna: ID_PENGGUNA,
                total_uang: currentTotalUang
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'transaksi_setor_sampah.php';
            }, 1500);
        } else {
            showToast(data.message || 'Gagal update status.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Terjadi kesalahan server.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>
</body>
</html>