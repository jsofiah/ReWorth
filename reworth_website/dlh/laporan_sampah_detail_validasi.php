<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        header("Location: laporan_sampah.php");
        exit;
    }

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }


    $url = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id)
         . "&select=*,pengguna!lapor_sampah_id_pengguna_fkey(nama_lengkap)";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data    = json_decode($res, true);
    $laporan = $data[0] ?? null;

    if (!$laporan) {
        header("Location: laporan_sampah.php");
        exit;
    }


    $urlPetugas = $supabaseUrl . "/rest/v1/petugas_lapangan?status_aktif=eq.true&select=id_petugas,nama_petugas&order=nama_petugas.asc";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $urlPetugas);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resPetugas  = curl_exec($ch2);
    curl_close($ch2);
    $listPetugas = json_decode($resPetugas, true) ?: [];

    $fotoSampahUrl = !empty($laporan['foto_sampah']) ? getSupabaseImageUrl($laporan['foto_sampah']) : null;
    $statusLaporan = $laporan['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Detail Validasi Laporan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang">
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item"><a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></div>
            <div class="nav-item"><a href="laporan_sampah.php" class="nav-link-custom active"><i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span></a></div>
            <div class="nav-item"><a href="apresiasi_rw.php" class="nav-link-custom"><i class="bi bi-award-fill"></i><span>Apresiasi RW</span></a></div>
            <div class="nav-item"><a href="event_lingkungan.php" class="nav-link-custom"><i class="bi bi-calendar-event-fill"></i><span>Event Lingkungan</span></a></div>
            <div class="nav-item"><a href="laporan_analitik.php" class="nav-link-custom"><i class="bi bi-bar-chart-line-fill"></i><span>Laporan dan Analitik</span></a></div>
            <div class="nav-item"><a href="data_petugas.php" class="nav-link-custom"><i class="bi bi-people-fill"></i><span>Data Petugas</span></a></div>
            <div class="nav-item"><a href="pengaturan_akun.php" class="nav-link-custom"><i class="bi bi-gear-fill"></i><span>Pengaturan Akun</span></a></div>
        </nav>
        <div class="sidebar-logout">
            <a class="logout-btn" href="../logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>

    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-inner">
                <h1 class="topbar-title">Laporan Sampah</h1>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>"
                                style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <i class="bi bi-person-fill" style="display:none;"></i>
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="detail-bar-wrap">
            <div class="detail-card">
                <div class="detail-card-inner">

                    
                    <div class="detail-left">
                        <div class="detail-accent"></div>
                        <div class="detail-section-title">Detail Validasi</div>
                        <div class="detail-nama"><?= htmlspecialchars($laporan['pengguna']['nama_lengkap'] ?? '-') ?></div>

                        <div class="detail-row">
                            <span class="detail-row-label">Lokasi</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value"><?= htmlspecialchars($laporan['lokasi'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Jenis Sampah</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value"><?= htmlspecialchars($laporan['jenis_sampah'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Deskripsi</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value"><?= htmlspecialchars($laporan['deskripsi'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Tanggal Lapor</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value">
                                <?= !empty($laporan['created_at']) ? date('d F Y', strtotime($laporan['created_at'])) : '-' ?>
                            </span>
                        </div>

                        <?php if ($statusLaporan === 'menunggu'): ?>
                        <div class="detail-actions">
                            <button class="btn-tolak" onclick="openModalTolak()">TOLAK</button>
                            <button class="btn-terima" onclick="openModalTerima()">TERIMA</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    
                    <div class="detail-right">
                        <?php if ($fotoSampahUrl): ?>
                            <img src="<?= htmlspecialchars($fotoSampahUrl) ?>"
                                 alt="Foto Sampah" class="detail-foto"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="detail-foto-placeholder" style="display:none;"><i class="bi bi-image"></i></div>
                        <?php else: ?>
                            <div class="detail-foto-placeholder"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    
    <div class="modal-overlay" id="modalTerima">
        <div class="modal-box" style="max-width:420px;padding:0;">
            <div class="modal-header-custom">
                <span class="modal-header-title">Pilih Petugas</span>
                <button class="modal-close-btn" onclick="closeModal('modalTerima')">✕</button>
            </div>
            <div class="modal-body-custom">
                <label class="modal-field-label">PILIH PETUGAS</label>
                <div class="modal-select-wrap">
                    <select class="modal-select" id="selectPetugas">
                        <option value="">Pilih petugas...</option>
                        <?php foreach ($listPetugas as $p): ?>
                            <option value="<?= htmlspecialchars($p['id_petugas']) ?>"><?= htmlspecialchars($p['nama_petugas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-simpan-modal" onclick="konfirmasiTerima()">SIMPAN DATA</button>
            </div>
        </div>
    </div>

    
    <div class="modal-overlay" id="modalTolak">
        <div class="modal-box" style="max-width:420px;padding:0;">
            <div class="modal-header-custom">
                <span class="modal-header-title">Form Penolakan</span>
                <button class="modal-close-btn" onclick="closeModal('modalTolak')">✕</button>
            </div>
            <div class="modal-body-custom">
                <label class="modal-field-label">ALASAN PENOLAKAN</label>
                <textarea class="modal-textarea" id="inputAlasan" placeholder="Masukkan alasan..."></textarea>
                <button class="btn-simpan-modal" onclick="konfirmasiTolak()">SIMPAN DATA</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const LAPORAN_ID = '<?= addslashes($laporan['id_laporan']) ?>';

        function openModal(id)  { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
        });
        function openModalTerima() { openModal('modalTerima'); }
        function openModalTolak()  { openModal('modalTolak'); }

        function konfirmasiTerima() {
        const idPetugas = document.getElementById('selectPetugas').value;

        if (!idPetugas) {
            showToast('Pilih petugas terlebih dahulu.', 'error');
            return;
        }
        showToast('Memproses data...', 'info');
        fetch('laporan_sampah_terima.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + encodeURIComponent(LAPORAN_ID)
                + '&id_petugas=' + encodeURIComponent(idPetugas)
        })
        .then(r => r.json())
        .then(data => {
            console.log(data);
            closeModal('modalTerima');
            if (data.success) {
                showToast('✅ Laporan berhasil diterima!', 'success');
                if (data.wa_link) {

                    window.open(data.wa_link, '_blank');
                    showToast(
                        'WhatsApp dibuka. Pesan sudah terisi, tinggal klik Kirim.',
                        'success'
                    );
                    setTimeout(() => {
                        window.location.href =
                            'laporan_sampah.php?tab=penanganan&msg=' +
                            encodeURIComponent('Laporan berhasil diterima') +
                            '&msg_type=success';
                    }, 3000);
                } else {
                    showToast(
                        'Nomor WhatsApp petugas tidak tersedia.',
                        'info'
                    );
                    setTimeout(() => {
                        window.location.href =
                            'laporan_sampah.php?tab=penanganan&msg=' +
                            encodeURIComponent('Laporan berhasil diterima') +
                            '&msg_type=success';
                    }, 1500);
                }
            } else {
                showToast(
                    data.message || 'Gagal memproses laporan.',
                    'error'
                );
            }
        })
        .catch(error => {
            console.error(error);
            showToast('Terjadi kesalahan server.', 'error');
        });

        }

        function konfirmasiTolak() {
            const alasan = document.getElementById('inputAlasan').value.trim();
            if (!alasan) { showToast('Masukkan alasan penolakan terlebih dahulu.', 'error'); return; }
            fetch('laporan_sampah_tolak.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(LAPORAN_ID) + '&alasan=' + encodeURIComponent(alasan)
            })
            .then(r => r.json())
            .then(data => {
                closeModal('modalTolak');
                if (data.success) {
                    showToast('Laporan berhasil ditolak.', 'success');
                    setTimeout(() => {
                        window.location.href = 'laporan_sampah.php?tab=validasi&msg=' + encodeURIComponent('Laporan berhasil ditolak.') + '&msg_type=success';
                    }, 1000);
                } else {
                    showToast(data.message || 'Gagal menolak laporan.', 'error');
                }
            })
            .catch(() => showToast('Terjadi kesalahan server.', 'error'));
        }

        function showToast(msg, type = 'success') {
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
            const div = document.createElement('div');
            div.className = `toast-item ${type}`;
            div.innerHTML = `<i class="bi ${icons[type] || icons.info} toast-icon"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(() => div.remove(), 3500);
        }
    </script>
</body>
</html>