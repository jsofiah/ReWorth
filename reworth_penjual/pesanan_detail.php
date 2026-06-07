<?php
    session_start();

    if (!isset($_SESSION['id_penjual'])) {
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    require_once 'subscription_check.php';

    $subscription = requirePremium($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

    $isPremium = $subscription['is_premium'];
    $remainingDays = getRemainingDays($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);

    $userId = $_SESSION['id_penjual'] ?? '';
    $pesananId = $_GET['id'] ?? '';

    function getSupabaseImageUrl($path) {
        if (empty($path)) return null;
        return "https://rxzrbyqqhkxemdjbcntc.supabase.co/storage/v1/object/public/media/" . ltrim($path, '/');
    }

    function curlRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }


    $getPesanan = curlRequest(
        $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$pesananId&select=*,produk(*),pengguna(*)",
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );

    $data = $getPesanan[0] ?? null;

    if (!$data) {
        echo "<p class='text-muted'>Data tidak ditemukan</p>";
        exit;
    }

    $status = $data['status'];
    $jasaKirimList = ['JNE', 'SiCepat', 'J&T', 'Pos Indonesia', 'Ninja Express', 'Grab Express', 'GoSend'];

    function getStatusBadge($status) {
        $status = strtolower($status);
        
        if ($status == 'selesai') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'diproses') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'dikirim') {
            return '<span class="status-badge status-dikirim">Dikirim</span>';
        } elseif ($status == 'menunggu') {
            return '<span class="status-badge status-akan_datang">Menunggu Konfirmasi</span>';
        } elseif ($status == 'ditolak') {
            return '<span class="status-badge status-akan_datang">Ditolak</span>';
        } else {
            return '<span class="status-badge status-akan_datang">' . ucfirst($status) . '</span>';
        }
    }
?>

<div class="table-responsive">
    <table class="table table-borderless">
        <tr><th width="40%">Produk</th><td><?= htmlspecialchars($data['produk']['nama_produk'] ?? '-') ?></td></tr>
        <tr><th>Total Bayar</th><td>Rp <?= number_format($data['total_bayar'], 0, ',', '.') ?></td></tr>
        <tr><th>Alamat Pengiriman</th><td><?= nl2br(htmlspecialchars($data['alamat_pengiriman'])) ?></td></tr>
        <tr><th>Status</th><td><?= getStatusBadge($status) ?></td></tr>
        <?php if ($data['nomor_resi']): ?>
        <tr><th>Nomor Resi</th><td><?= htmlspecialchars($data['nomor_resi']) ?></td></tr>
        <?php endif; ?>
        <?php if ($data['jasa_kirim']): ?>
        <tr><th>Jasa Kirim</th><td><?= htmlspecialchars($data['jasa_kirim']) ?></td></tr>
        <?php endif; ?>
        <?php if ($data['alasan_penolakan']): ?>
        <tr><th>Alasan Penolakan</th><td class="text-danger"><?= htmlspecialchars($data['alasan_penolakan']) ?></td></tr>
        <?php endif; ?>
        <?php if ($data['bukti_pembayaran']): ?>
        <tr><th>Bukti Pembayaran</th>
            <td><img src="<?= getSupabaseImageUrl($data['bukti_pembayaran']) ?>" style="max-width: 150px; border-radius: 8px; cursor: pointer;" onclick="window.open(this.src)">
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- Tombol Aksi sesuai status -->
<div class="d-flex gap-3 mt-4">
    <?php if ($status == 'menunggu'): ?>
        <button class="btn btn-success" onclick="konfirmasiPesanan('<?= $pesananId ?>')">
            <i class="bi bi-check-lg"></i> Konfirmasi
        </button>
        <button class="btn btn-danger" onclick="openTolakModal('<?= $pesananId ?>')">
            <i class="bi bi-x-lg"></i> Tolak
        </button>

    <?php elseif ($status == 'diproses'): ?>
        <button class="btn btn-primary" onclick="openKirimModal('<?= $pesananId ?>')">
            <i class="bi bi-truck"></i> Kirim
        </button>

    <?php elseif ($status == 'dikirim'): ?>
        <button class="btn btn-success" onclick="selesaikanPesanan('<?= $pesananId ?>')">
            <i class="bi bi-check2-circle"></i> Selesai
        </button>
    <?php endif; ?>
</div>

<!-- Modal Tolak -->
<div id="modalTolakContainer" style="display: none;">
    <div class="modal-title">Tolak Pesanan</div>
    <div class="form-group">
        <label class="form-label">Alasan Penolakan</label>
        <textarea id="alasanTolak" class="form-control-custom" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
    </div>
    <div class="modal-actions">
        <button class="btn-cancel" onclick="closeTolakModal()">Batal</button>
        <button class="btn-save" onclick="submitTolak()">Kirim</button>
    </div>
</div>

<!-- Modal Kirim -->
<div id="modalKirimContainer" style="display: none;">
    <div class="modal-title">Input Pengiriman</div>
    <div class="form-group">
        <label class="form-label">Jasa Kirim</label>
        <select id="jasaKirim" class="form-control-custom">
            <option value="">-- Pilih Jasa Kirim --</option>
            <?php foreach ($jasaKirimList as $jk): ?>
            <option value="<?= $jk ?>"><?= $jk ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Nomor Resi</label>
        <input type="text" id="nomorResi" class="form-control-custom" placeholder="Masukkan nomor resi">
    </div>
    <div class="modal-actions">
        <button class="btn-cancel" onclick="closeKirimModal()">Batal</button>
        <button class="btn-save" onclick="submitKirim()">Kirim</button>
    </div>
</div>

<script>
    function konfirmasiPesanan(id) {
        if (!confirm('Konfirmasi pembayaran pesanan ini?')) return;
        fetch('pesanan_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_pesanan=' + encodeURIComponent(id) + '&status=diproses'
        }).then(res => res.json()).then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1000);
        });
    }

    function openTolakModal(id) {
        window.currentPesananId = id;
        document.getElementById('alasanTolak').value = '';
        document.getElementById('modalTolakContainer').style.display = 'block';
    }

    function closeTolakModal() {
        document.getElementById('modalTolakContainer').style.display = 'none';
    }

    function submitTolak() {
        let alasan = document.getElementById('alasanTolak').value;
        if (!alasan) {
            showToast('Masukkan alasan penolakan!', 'error');
            return;
        }
        fetch('pesanan_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_pesanan=' + encodeURIComponent(window.currentPesananId) + '&status=ditolak&alasan=' + encodeURIComponent(alasan)
        }).then(res => res.json()).then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            closeTolakModal();
            if (data.success) setTimeout(() => location.reload(), 1000);
        });
    }

    function openKirimModal(id) {
        window.currentPesananId = id;
        document.getElementById('jasaKirim').value = '';
        document.getElementById('nomorResi').value = '';
        document.getElementById('modalKirimContainer').style.display = 'block';
    }

    function closeKirimModal() {
        document.getElementById('modalKirimContainer').style.display = 'none';
    }

    function submitKirim() {
        let jasaKirim = document.getElementById('jasaKirim').value;
        let nomorResi = document.getElementById('nomorResi').value;
        
        if (!jasaKirim) {
            showToast('Pilih jasa kirim terlebih dahulu!', 'error');
            return;
        }
        if (!nomorResi) {
            showToast('Masukkan nomor resi!', 'error');
            return;
        }
        
        fetch('pesanan_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_pesanan=' + encodeURIComponent(window.currentPesananId) + '&status=dikirim&jasa_kirim=' + encodeURIComponent(jasaKirim) + '&nomor_resi=' + encodeURIComponent(nomorResi)
        }).then(res => res.json()).then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            closeKirimModal();
            if (data.success) setTimeout(() => location.reload(), 1000);
        });
    }

    function selesaikanPesanan(id) {
        if (!confirm('Apakah pesanan sudah diterima oleh pembeli? Tindakan ini tidak dapat dibatalkan.')) return;
        
        fetch('pesanan_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_pesanan=' + encodeURIComponent(id) + '&status=selesai'
        }).then(res => res.json()).then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1000);
        });
    }

    function showToast(msg, type) {
        const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill' };
        const div = document.createElement('div');
        div.className = `toast-item ${type}`;
        div.innerHTML = `<i class="bi ${icons[type]} toast-icon"></i><span>${msg}</span>`;
        document.getElementById('toastContainer').appendChild(div);
        setTimeout(() => div.remove(), 3500);
    }
</script>