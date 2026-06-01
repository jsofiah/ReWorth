<?php
session_start();

if (!isset($_SESSION['id_penjual'])) {
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$userId = $_SESSION['id_penjual'] ?? '';
$id_langganan = $_GET['id'] ?? '';

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

// Ambil detail langganan
$getDetail = curlRequest(
    $supabaseUrl . "/rest/v1/langganan?id_langganan=eq.$id_langganan",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$data = $getDetail[0] ?? null;

if (!$data) {
    echo "<p class='text-muted'>Data tidak ditemukan</p>";
    exit;
}

// Tentukan status
if ($data['status'] == 'aktif') {
    if ($data['tanggal_selesai'] < date('Y-m-d')) {
        $statusClass = 'bg-secondary';
        $statusText = 'Kadaluarsa';
    } else {
        $statusClass = 'bg-success';
        $statusText = 'Aktif';
    }
} elseif ($data['status'] == 'menunggu_verifikasi') {
    $statusClass = 'bg-warning text-dark';
    $statusText = 'Menunggu Verifikasi';
} else {
    $statusClass = 'bg-secondary';
    $statusText = ucfirst($data['status']);
}
?>

<div class="table-responsive">
    <table class="table table-borderless">
        <tr>
            <th width="40%">Tanggal Mulai</th>
            <td><?= date('d F Y', strtotime($data['tanggal_mulai'])) ?></td>
        </tr>
        <tr>
            <th>Tanggal Selesai</th>
            <td><?= date('d F Y', strtotime($data['tanggal_selesai'])) ?></td>
        </tr>
        <tr>
            <th>Jumlah Bayar</th>
            <td>Rp <?= number_format($data['jumlah_bayar'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
        </tr>
        <tr>
            <th>Tanggal Pengajuan</th>
            <td><?= date('d F Y H:i:s', strtotime($data['created_at'])) ?></td>
        </tr>
        <?php if (!empty($data['bukti_pembayaran'])): ?>
        <tr>
            <th>Bukti Pembayaran</th>
            <td>
                <img src="<?= getSupabaseImageUrl($data['bukti_pembayaran']) ?>" 
                     style="max-width: 150px; border-radius: 8px; cursor: pointer;" 
                     onclick="window.open(this.src)">
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>