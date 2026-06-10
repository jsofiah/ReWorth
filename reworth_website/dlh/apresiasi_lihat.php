<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $userName  = $_SESSION['nama_admin']  ?? 'User';
    $userEmail = $_SESSION['email']       ?? 'user@example.com';
    $userRole  = $_SESSION['role']        ?? '';
    $userFoto  = $_SESSION['foto_profil'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        header("Location: apresiasi_rw.php");
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/apresiasi?id_apresiasi=eq." . urlencode($id) . "&select=*,wilayah(rw,kelurahan,kecamatan,kota)");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = ($code === 200 && $res) ? json_decode($res, true) : [];
    $a = $data[0] ?? null;

    if (!$a) {
        header("Location: apresiasi_rw.php");
        exit;
    }

    $w = $a['wilayah'] ?? null;
    $rwLabel = $w ? ('RW ' . str_pad($w['rw'] ?? '?', 2, '0', STR_PAD_LEFT) . ' — ' . ($w['kelurahan'] ?? '')) : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLH – Detail Apresiasi RW</title>
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
        <div class="nav-item"><a href="laporan_sampah.php" class="nav-link-custom"><i class="bi bi-exclamation-diamond-fill"></i><span>Laporan Sampah</span></a></div>
        <div class="nav-item"><a href="apresiasi_rw.php" class="nav-link-custom active"><i class="bi bi-award-fill"></i><span>Apresiasi RW</span></a></div>
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
            <h1 class="topbar-title">Apresiasi RW</h1>
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="topbar-user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="topbar-avatar">
                    <?php if (!empty($userFoto)): ?>
                        <img src="<?= htmlspecialchars(getSupabaseImageUrl($userFoto)) ?>"
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

    <div class="content-area">
        <div class="form-container">
            <div class="form-section">
                <div class="inside-header">
                    <h2>Detail Apresiasi RW</h2>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Judul Apresiasi</div>
                    <div class="detail-value"><?= htmlspecialchars($a['judul_apresiasi'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Wilayah</div>
                    <div class="detail-value"><?= htmlspecialchars($rwLabel) ?></div>
                </div>
                <?php if ($w): ?>
                <div class="detail-row">
                    <div class="detail-label">Kecamatan</div>
                    <div class="detail-value"><?= htmlspecialchars($w['kecamatan'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Kota</div>
                    <div class="detail-value"><?= htmlspecialchars($w['kota'] ?? '-') ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <div class="detail-label">Periode</div>
                    <div class="detail-value">
                        <span class="apresiasi-badge"><?= htmlspecialchars($a['periode'] ?? '-') ?></span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Tanggal Dibuat</div>
                    <div class="detail-value">
                        <?= !empty($a['created_at']) ? date('d F Y, H:i', strtotime($a['created_at'])) : '-' ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Deskripsi</div>
                    <div class="detail-value" style="white-space:pre-line;"><?= htmlspecialchars($a['deskripsi'] ?? '-') ?></div>
                </div>

                <div class="form-actions" style="margin-top:24px;">
                    <button type="button" class="btn-cancel"
                        onclick="window.location.href='apresiasi_rw.php?tab=riwayat'">
                        Kembali
                    </button>
                    <button type="button" class="btn-hapus btn-aksi"
                        onclick="openHapus('<?= $a['id_apresiasi'] ?>')">
                        <i class="bi bi-trash3"></i> Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal-overlay" id="modalHapus">
    <div class="modal-box" style="max-width:400px;">
        <div class="confirm-icon"><i class="bi bi-trash3-fill"></i></div>
        <div class="confirm-text">
            <h3>Hapus Apresiasi?</h3>
            <p>Tindakan ini tidak dapat dibatalkan. Data apresiasi akan dihapus secara permanen.</p>
        </div>
        <div class="modal-actions" style="justify-content:center;margin-top:20px;">
            <button class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
            <button class="btn-aksi btn-hapus" style="padding:10px 22px;font-size:14px;border-radius:12px;"
                onclick="confirmHapus()">
                <i class="bi bi-trash3"></i> Ya, Hapus
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let deletingId = null;

    function openHapus(id) {
        deletingId = id;
        document.getElementById('modalHapus').classList.add('show');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    function confirmHapus() {
        if (!deletingId) return;
        fetch('apresiasi_hapus.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(deletingId)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Apresiasi berhasil dihapus.', 'success');
                setTimeout(() => {
                    window.location.href = 'apresiasi_rw.php?tab=riwayat&toast=hapus';
                }, 1000);
            } else {
                showToast(data.message || 'Gagal menghapus.', 'error');
                closeModal('modalHapus');
            }
        })
        .catch(() => { showToast('Terjadi kesalahan server.', 'error'); });
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