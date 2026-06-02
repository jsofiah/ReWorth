<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_penjual'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

// ========== FUNGSI CURLREQUEST ==========
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
    return ['response' => $response, 'httpCode' => 200];
}
// ========================================

$id_pesanan = $_POST['id_pesanan'] ?? '';
$status = $_POST['status'] ?? '';
$nomor_resi = $_POST['nomor_resi'] ?? '';
$jasa_kirim = $_POST['jasa_kirim'] ?? '';
$alasan = $_POST['alasan'] ?? '';

if (empty($id_pesanan) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$updateData = ['status' => $status];

// Jika status dikirim, tambah nomor resi dan jasa kirim
if ($status === 'dikirim') {
    if (!empty($nomor_resi)) $updateData['nomor_resi'] = $nomor_resi;
    if (!empty($jasa_kirim)) $updateData['jasa_kirim'] = $jasa_kirim;
}

// Jika status ditolak, tambah alasan penolakan
if ($status === 'ditolak' && !empty($alasan)) {
    $updateData['alasan_penolakan'] = $alasan;
}

// Update status pesanan
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$id_pesanan");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json",
    "Prefer: return=minimal"
]);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$success = ($httpCode == 200 || $httpCode == 204);

// Jika konfirmasi (status diproses), catat transaksi dan hitung komisi
if ($success && $status === 'diproses') {
    // Ambil data pesanan untuk mendapatkan total_bayar dan id_pengguna
    $getPesanan = curlRequest(
        $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$id_pesanan",
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );
    $pesanan = json_decode($getPesanan['response'], true)[0] ?? null;
    
    if ($pesanan) {
        $totalBayar = $pesanan['total_bayar'];
        $idPengguna = $pesanan['id_pengguna'];
        $komisi = $totalBayar * 0.05; // 5% komisi
        $periodeBulan = date('Y-m');
        
        // Catat ke riwayat_aktivitas
        $riwayatData = [
            'jenis_aktivitas' => 'pesanan_selesai',
            'id_referensi' => $id_pesanan,
            'judul' => 'Pesanan Dikonfirmasi',
            'deskripsi' => 'Pesanan telah dikonfirmasi dan diproses',
            'status' => 'sukses',
            'id_pengguna' => $idPengguna
        ];
        
        curlRequest(
            $supabaseUrl . "/rest/v1/riwayat_aktivitas",
            'POST',
            json_encode($riwayatData),
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
        );
        
        // Catat komisi
        $komisiData = [
            'id_penjual' => $_SESSION['id_penjual'],
            'periode_bulan' => $periodeBulan,
            'total_komisi' => $komisi,
            'status_pembayaran' => 'menunggu'
        ];
        
        curlRequest(
            $supabaseUrl . "/rest/v1/komisi",
            'POST',
            json_encode($komisiData),
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
        );
        
        // Kirim notifikasi ke pembeli (hanya jika id_pengguna tidak kosong)
        if (!empty($idPengguna)) {
            $notifikasiData = [
                'id_pengguna' => $idPengguna,
                'judul' => 'Pesanan Diproses',
                'deskripsi' => 'Pesanan Anda telah dikonfirmasi dan sedang diproses oleh penjual.',
                'is_read' => false
            ];
            
            curlRequest(
                $supabaseUrl . "/rest/v1/notifikasi",
                'POST',
                json_encode($notifikasiData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        }
    }
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal update status']);
}
?>