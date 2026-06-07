<?php
session_start();

if (!isset($_SESSION['id_penjual'])) {
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

require_once 'subscription_check.php';

$subscription = getSubscriptionStatus($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey);
$isPremium = $subscription['is_premium'];
$isExpired = $subscription['is_expired'];

$userId = $_SESSION['id_penjual'] ?? '';
$komisiId = $_GET['id'] ?? '';

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

// Ambil data komisi
$getKomisi = curlRequest(
    $supabaseUrl . "/rest/v1/komisi?id_komisi=eq.$komisiId",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$data = $getKomisi[0] ?? null;

if (!$data) {
    echo "<p class='text-muted'>Data tidak ditemukan</p>";
    exit;
}

// Cek apakah komisi milik penjual yang login
if ($data['id_penjual'] != $userId) {
    echo "<p class='text-muted'>Akses ditolak</p>";
    exit;
}

function getStatusBadge($status) {
    $status = strtolower($status);
    
    if ($status == 'selesai') {
        return '<span class="status-badge status-selesai">Selesai</span>';
    } elseif ($status == 'dibayar') {
        return '<span class="status-badge status-dibayar">Menunggu Konfirmasi</span>';
    } elseif ($status == 'pending') {
        return '<span class="status-badge status-pending">Belum Dibayar</span>';
    } else {
        return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

function formatRupiah($angka) {
    if (empty($angka)) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<style>
    .detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    .detail-table tr {
        border-bottom: 1px solid #E2EDE8;
    }
    .detail-table td {
        padding: 12px 0;
        vertical-align: top;
    }
    .detail-table td:first-child {
        width: 40%;
        font-weight: 600;
        color: #2D6A4F;
    }
    .detail-table td:last-child {
        color: #2D3748;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        min-width: 140px;
    }
    .status-selesai {
        background: #D4EDDA;
        color: #155724;
        border: 1px solid #C3E6CB;
    }
    .status-dibayar {
        background: #D1ECF1;
        color: #0C5460;
        border: 1px solid #BEE5EB;
    }
    .status-pending {
        background: #FFF3CD;
        color: #856404;
        border: 1px solid #FFEEBA;
    }
    .bukti-image {
        max-width: 200px;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    .bukti-image:hover {
        transform: scale(1.02);
    }
</style>

<table class="detail-table">
    <tr>
        <td>ID Komisi</td>
        <td><code><?= htmlspecialchars($data['id_komisi']) ?></code></td>
    </tr>
    <tr>
        <td>Periode Bulan</td>
        <td><strong><?= htmlspecialchars($data['periode_bulan'] ?? '-') ?></strong></td>
    </tr>
    <tr>
        <td>Total Komisi</td>
        <td><strong style="font-size: 18px; color: #00A86B;"><?= formatRupiah($data['total_komisi'] ?? 0) ?></strong></td>
    </tr>
    <tr>
        <td>Status Pembayaran</td>
        <td><?= getStatusBadge($data['status_pembayaran'] ?? 'pending') ?></td>
    </tr>
    <?php if (!empty($data['tanggal_pembayaran'])): ?>
    <tr>
        <td>Tanggal Pembayaran</td>
        <td><?= date('d F Y', strtotime($data['tanggal_pembayaran'])) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td>Tanggal Dibuat</td>
        <td><?= date('d F Y H:i', strtotime($data['created_at'])) ?></td>
    </tr>
    <?php if (!empty($data['bukti_pembayaran'])): ?>
    <tr>
        <td>Bukti Pembayaran</td>
        <td>
            <img src="<?= getSupabaseImageUrl($data['bukti_pembayaran']) ?>" 
                 class="bukti-image" 
                 onclick="window.open(this.src)">
        </td>
    </tr>
    <?php endif; ?>
</table>

<?php if (($data['status_pembayaran'] ?? '') == 'pending'): ?>
<div class="alert alert-info mt-3" style="background: #E8F5E9; border: 1px solid #C8E6C9; border-radius: 12px; padding: 12px;">
    <i class="bi bi-info-circle-fill" style="color: #00A86B;"></i>
    Komisi ini belum dibayar. Silakan lakukan pembayaran melalui menu Pembayaran Komisi.
</div>
<?php elseif (($data['status_pembayaran'] ?? '') == 'dibayar'): ?>
<div class="alert alert-warning mt-3" style="background: #FFF3CD; border: 1px solid #FFEEBA; border-radius: 12px; padding: 12px;">
    <i class="bi bi-clock-history" style="color: #856404;"></i>
    Bukti pembayaran telah diupload. Menunggu konfirmasi dari admin.
</div>
<?php elseif (($data['status_pembayaran'] ?? '') == 'selesai'): ?>
<div class="alert alert-success mt-3" style="background: #D4EDDA; border: 1px solid #C3E6CB; border-radius: 12px; padding: 12px;">
    <i class="bi bi-check-circle-fill" style="color: #155724;"></i>
    Komisi ini sudah selesai dibayar.
</div>
<?php endif; ?>