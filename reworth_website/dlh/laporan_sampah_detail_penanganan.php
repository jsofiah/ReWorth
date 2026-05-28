<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin'] ?? 'User';
    $userEmail = $_SESSION['email'] ?? 'user@example.com';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    $id = $_GET['id'] ?? '';
    if (empty($id)) { header("Location: laporan_sampah.php?tab=penanganan"); exit; }

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    $url = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id)
         . "&select=*,pengguna!lapor_sampah_id_pengguna_fkey(nama_lengkap),petugas_lapangan!lapor_sampah_id_petugas_fkey(nama_petugas)";
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]);
    $res     = curl_exec($ch); curl_close($ch);
    $data    = json_decode($res, true);
    $laporan = $data[0] ?? null;
    if (!$laporan) { header("Location: laporan_sampah.php?tab=penanganan"); exit; }

    $st      = $laporan['status'] ?? '';
    $stClass = match($st) { 'diproses' => 'status-diproses', 'selesai' => 'status-selesai', default => 'status-menunggu' };
    $stLabel = match($st) { 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak', default => ucfirst($st) };

    $fotoSampahUrl = !empty($laporan['foto_sampah'])      ? getSupabaseImageUrl($laporan['foto_sampah'])      : null;
    $buktiUrl      = !empty($laporan['bukti_penanganan']) ? getSupabaseImageUrl($laporan['bukti_penanganan']) : null;

    // Tombol update hanya muncul saat status diproses
    $showUpdate = ($st === 'diproses');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Detail Penanganan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/root.css">
    <link rel="stylesheet" href="style/form.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo"><img src="img/logo.png" alt="Logo ReWorth" title="DLH Kota Malang"></div>
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
                <div>
                    <h1 class="topbar-title">Laporan Sampah</h1>
                    <a href="laporan_sampah.php?tab=penanganan" class="back-btn-topbar">
                        <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Laporan Sampah
                    </a>
                </div>
                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                    </div>
                    <div class="topbar-avatar">
                        <?php if (!empty($userFoto)): $fotoUrl = getSupabaseImageUrl($userFoto); ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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
                <div class="detail-card-inner <?= $buktiUrl ? 'has-bukti' : '' ?>">

                    <!-- KIRI: Info -->
                    <div class="detail-left">
                        <div class="detail-accent"></div>
                        <div class="detail-section-title">Detail Penanganan</div>
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
                            <span class="detail-row-value"><?= !empty($laporan['created_at']) ? date('d F Y', strtotime($laporan['created_at'])) : '-' ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Nama Petugas</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value"><?= htmlspecialchars($laporan['petugas_lapangan']['nama_petugas'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-row-label">Status</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value">
                                <span class="status-badge <?= $stClass ?>"><?= $stLabel ?></span>
                            </span>
                        </div>
                        <?php if (!empty($laporan['alasan_penolakan'])): ?>
                        <div class="detail-row">
                            <span class="detail-row-label">Alasan Tolak</span>
                            <span class="detail-row-colon">:</span>
                            <span class="detail-row-value"><?= htmlspecialchars($laporan['alasan_penolakan']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($showUpdate): ?>
                        <div class="btn-update-wrap">
                            <button class="btn-update" onclick="openModal('modalUpdate')">UPDATE PENANGANAN</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- KANAN: Foto -->
                    <div class="detail-right">
                        <?php if ($buktiUrl): ?>
                            <!-- Status selesai: 2 foto (Bukti Lapor + Bukti Penanganan) -->
                            <div class="detail-right-bukti">
                                <div class="foto-box">
                                    <div class="foto-box-label">Bukti Lapor</div>
                                    <?php if ($fotoSampahUrl): ?>
                                        <img src="<?= htmlspecialchars($fotoSampahUrl) ?>" alt="Bukti Lapor" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <div class="foto-box-placeholder" style="display:none;"><i class="bi bi-image"></i></div>
                                    <?php else: ?>
                                        <div class="foto-box-placeholder"><i class="bi bi-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="foto-box">
                                    <div class="foto-box-label">Bukti Penanganan</div>
                                    <img src="<?= htmlspecialchars($buktiUrl) ?>" alt="Bukti Penanganan" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div class="foto-box-placeholder" style="display:none;"><i class="bi bi-image"></i></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Status diproses: 1 foto besar -->
                            <?php if ($fotoSampahUrl): ?>
                                <img src="<?= htmlspecialchars($fotoSampahUrl) ?>" alt="Foto Sampah" class="detail-foto-single" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="detail-foto-placeholder" style="display:none;"><i class="bi bi-image"></i></div>
                            <?php else: ?>
                                <div class="detail-foto-placeholder"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal Update Penanganan -->
    <div class="modal-overlay" id="modalUpdate">
        <div class="modal-box" style="max-width:460px;padding:0;">
            <div class="modal-header-custom">
                <span class="modal-header-title">Update Penanganan</span>
                <button class="modal-close-btn" onclick="closeModal('modalUpdate')">✕</button>
            </div>
            <div class="modal-body-custom">
                <label class="modal-field-label">BUKTI PENANGANAN</label>
                <div class="file-input-wrap">
                    <label class="file-input-label" for="inputBukti">
                        Pilih file
                    </label>
                    <span class="file-input-name" id="namaFile">No file chosen</span>
                    <input type="file" id="inputBukti" accept="image/*" style="display:none;" onchange="updateFileName(this)">
                </div>
                <button class="btn-simpan-modal" id="btnSimpanUpdate" onclick="simpanUpdate()">SIMPAN DATA</button>
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

        function updateFileName(input) {
            document.getElementById('namaFile').textContent = input.files[0]?.name || 'No file chosen';
        }

        function simpanUpdate() {
            const file = document.getElementById('inputBukti').files[0];
            if (!file) { showToast('Pilih file bukti penanganan terlebih dahulu.', 'error'); return; }

            const btn = document.getElementById('btnSimpanUpdate');
            btn.disabled = true;
            btn.textContent = 'MENYIMPAN...';

            const formData = new FormData();
            formData.append('id', LAPORAN_ID);
            formData.append('bukti_penanganan', file);

            fetch('laporan_sampah_update_penanganan.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                closeModal('modalUpdate');
                btn.disabled = false;
                btn.textContent = 'SIMPAN DATA';
                if (data.success) {
                    showToast('Bukti penanganan berhasil disimpan.', 'success');
                    setTimeout(() => {
                        window.location.href = 'laporan_sampah.php?tab=penanganan&msg=' + encodeURIComponent('Penanganan berhasil diperbarui.') + '&msg_type=success';
                    }, 1000);
                } else {
                    showToast(data.message || 'Gagal menyimpan data.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'SIMPAN DATA';
                showToast('Terjadi kesalahan server.', 'error');
            });
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