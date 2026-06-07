<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_penjual'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

require_once 'subscription_check.php';

if (!hasPremiumAccess($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Langganan Anda telah berakhir. Silakan perpanjang untuk melanjutkan.'
    ]);
    exit;
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['response' => $response, 'httpCode' => $httpCode];
}

function sendFCMNotification($userId, $title, $body, $supabaseUrl, $supabaseKey, $data = []) {
    $fcmPayload = [
        'user_id' => $userId,
        'title' => $title,
        'body' => $body,
        'data' => $data
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/functions/v1/send-user-notification");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $supabaseKey",
        "apikey: $supabaseKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['success' => $httpCode == 200, 'response' => json_decode($response, true)];
}

// Fungsi untuk cek apakah komisi suatu bulan sudah terkunci (sudah dibayar)
function isCommissionLocked($penjualId, $periodeBulan, $supabaseUrl, $supabaseKey) {
    $checkKomisi = curlRequest(
        $supabaseUrl . "/rest/v1/komisi?id_penjual=eq.$penjualId&periode_bulan=eq.$periodeBulan&status_pembayaran=neq.pending",
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );
    
    $existingKomisi = json_decode($checkKomisi['response'], true);
    
    // Jika status sudah 'dibayar' atau 'selesai', berarti terkunci
    return !empty($existingKomisi);
}

// Fungsi untuk update atau insert komisi (dengan skenario pindah bulan jika sudah dibayar)
function updateCommission($penjualId, $totalBayar, $supabaseUrl, $supabaseKey) {
    $komisi = $totalBayar * 0.05;
    $tanggalOrder = date('Y-m-d');
    $periodeBulanAsli = date('Y-m', strtotime($tanggalOrder));
    $periodeBulan = $periodeBulanAsli;
    $isMoved = false;
    
    // CEK APAKAH BULAN INI SUDAH TERKUNCI (SUDAH DIBAYAR)
    $isLocked = isCommissionLocked($penjualId, $periodeBulanAsli, $supabaseUrl, $supabaseKey);
    
    if ($isLocked) {
        // Jika sudah dibayar, pindahkan ke bulan berikutnya
        $periodeBulan = date('Y-m', strtotime('+1 month', strtotime($tanggalOrder)));
        $isMoved = true;
        error_log("Komisi untuk penjual $penjualId dipindah dari $periodeBulanAsli ke $periodeBulan karena bulan sebelumnya sudah dibayar");
    }
    
    // Cek apakah sudah ada record komisi untuk bulan yang dituju
    $checkKomisi = curlRequest(
        $supabaseUrl . "/rest/v1/komisi?id_penjual=eq.$penjualId&periode_bulan=eq.$periodeBulan",
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );
    $existingKomisi = json_decode($checkKomisi['response'], true);
    
    if (!empty($existingKomisi)) {
        $newTotal = $existingKomisi[0]['total_komisi'] + $komisi;
        curlRequest(
            $supabaseUrl . "/rest/v1/komisi?id_komisi=eq." . $existingKomisi[0]['id_komisi'],
            'PATCH',
            json_encode(['total_komisi' => $newTotal]),
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
        );
        return ['action' => 'updated', 'komisi' => $komisi, 'periode' => $periodeBulan, 'is_moved' => $isMoved];
    } else {
        $komisiData = [
            'id_penjual' => $penjualId,
            'periode_bulan' => $periodeBulan,
            'total_komisi' => $komisi,
            'status_pembayaran' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        curlRequest(
            $supabaseUrl . "/rest/v1/komisi",
            'POST',
            json_encode($komisiData),
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
        );
        return ['action' => 'inserted', 'komisi' => $komisi, 'periode' => $periodeBulan, 'is_moved' => $isMoved];
    }
}

$id_pesanan = $_POST['id_pesanan'] ?? '';
$status = $_POST['status'] ?? '';
$nomor_resi = $_POST['nomor_resi'] ?? '';
$jasa_kirim = $_POST['jasa_kirim'] ?? '';
$alasan = $_POST['alasan'] ?? '';

if (empty($id_pesanan) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

if ($status === 'dikirim') {
    if (empty($nomor_resi) || empty($jasa_kirim)) {
        echo json_encode(['success' => false, 'message' => 'Nomor resi dan jasa kirim wajib diisi']);
        exit;
    }
}

// Ambil data pesanan lengkap
$getPesanan = curlRequest(
    $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$id_pesanan&select=*,produk(*)",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);
$pesananData = json_decode($getPesanan['response'], true);
$pesanan = $pesananData[0] ?? null;

if (!$pesanan) {
    echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
    exit;
}

$idPengguna = $pesanan['id_pengguna'] ?? null;
$totalBayar = $pesanan['total_bayar'] ?? 0;

// Ambil nama produk
$namaProduk = '';
if (!empty($pesanan['id_produk'])) {
    $getProduk = curlRequest(
        $supabaseUrl . "/rest/v1/produk?id_produk=eq." . $pesanan['id_produk'],
        'GET',
        null,
        ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
    );
    $produkData = json_decode($getProduk['response'], true);
    $namaProduk = $produkData[0]['nama_produk'] ?? 'Produk';
}

// Siapkan data update
$updateData = ['status' => $status];

if ($status === 'dikirim') {
    if (!empty($nomor_resi)) $updateData['nomor_resi'] = $nomor_resi;
    if (!empty($jasa_kirim)) $updateData['jasa_kirim'] = $jasa_kirim;
}

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
$patchResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$success = ($httpCode == 200 || $httpCode == 204);

if ($success) {
    
    if ($status === 'diproses') {
        // 1. Update riwayat aktivitas
        $deskripsiRiwayat = "Pesanan {$namaProduk} senilai Rp " . number_format($totalBayar, 0, ',', '.') . " sudah dikonfirmasi dan sedang diproses";
        
        $checkRiwayat = curlRequest(
            $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan&jenis_aktivitas=eq.pesanan",
            'GET',
            null,
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
        );
        $existingRiwayat = json_decode($checkRiwayat['response'], true);
        
        $riwayatData = [
            'jenis_aktivitas' => 'pesanan',
            'deskripsi' => $deskripsiRiwayat,
            'status' => 'diproses'
        ];
        
        if (!empty($existingRiwayat)) {
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan",
                'PATCH',
                json_encode($riwayatData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        } else {
            $riwayatInsert = [
                'id_pengguna' => $idPengguna,
                'jenis_aktivitas' => 'pesanan',
                'id_referensi' => $id_pesanan,
                'judul' => "Pesanan Dikonfirmasi - {$namaProduk}",
                'deskripsi' => $deskripsiRiwayat,
                'status' => 'diproses',
                'created_at' => date('Y-m-d H:i:s')
            ];
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas",
                'POST',
                json_encode($riwayatInsert),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        }
        
        // 2. Update komisi (DENGAN SKENARIO PINDAH BULAN)
        $komisiResult = updateCommission($_SESSION['id_penjual'], $totalBayar, $supabaseUrl, $supabaseKey);
        
        if ($komisiResult['is_moved']) {
            error_log("Komisi untuk order $id_pesanan masuk ke bulan {$komisiResult['periode']}");
        }
        
        // 3. Notifikasi ke pembeli
        if ($idPengguna) {
            $notifikasiData = [
                'id_pengguna' => $idPengguna,
                'judul' => '✅ Pembayaran Dikonfirmasi',
                'deskripsi' => "Pembayaran untuk pesanan {$namaProduk} telah dikonfirmasi. Pesanan Anda sedang diproses.",
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            curlRequest(
                $supabaseUrl . "/rest/v1/notifikasi",
                'POST',
                json_encode($notifikasiData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
            
            sendFCMNotification(
                $idPengguna,
                'Pembayaran Dikonfirmasi',
                "Pesanan {$namaProduk} telah dikonfirmasi dan sedang diproses.",
                $supabaseUrl,
                $supabaseKey,
                ['type' => 'order_confirmed', 'order_id' => $id_pesanan]
            );
        }
        
    } elseif ($status === 'dikirim') {
        $deskripsiRiwayat = "Pesanan {$namaProduk} telah dikirim melalui {$jasa_kirim} dengan no resi: {$nomor_resi}";
        
        $checkRiwayat = curlRequest(
            $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan&jenis_aktivitas=eq.pesanan",
            'GET',
            null,
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
        );
        $existingRiwayat = json_decode($checkRiwayat['response'], true);
        
        $riwayatData = [
            'jenis_aktivitas' => 'pesanan',
            'deskripsi' => $deskripsiRiwayat,
            'status' => 'dikirim'
        ];
        
        if (!empty($existingRiwayat)) {
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan",
                'PATCH',
                json_encode($riwayatData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        }
        
        if ($idPengguna) {
            $notifikasiData = [
                'id_pengguna' => $idPengguna,
                'judul' => '📦 Pesanan Dikirim',
                'deskripsi' => "Pesanan {$namaProduk} sedang dalam perjalanan.\nKurir: {$jasa_kirim}\nNo Resi: {$nomor_resi}",
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            curlRequest(
                $supabaseUrl . "/rest/v1/notifikasi",
                'POST',
                json_encode($notifikasiData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
            
            sendFCMNotification(
                $idPengguna,
                'Pesanan Dikirim',
                "Pesanan {$namaProduk} sedang dalam perjalanan. Kurir: {$jasa_kirim}, No Resi: {$nomor_resi}",
                $supabaseUrl,
                $supabaseKey,
                ['type' => 'order_shipped', 'order_id' => $id_pesanan, 'tracking_number' => $nomor_resi]
            );
        }
        
    } elseif ($status === 'ditolak') {
        $deskripsiRiwayat = "Pesanan {$namaProduk} ditolak. Alasan: {$alasan}";
        
        $checkRiwayat = curlRequest(
            $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan&jenis_aktivitas=eq.pesanan",
            'GET',
            null,
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
        );
        $existingRiwayat = json_decode($checkRiwayat['response'], true);
        
        $riwayatData = [
            'jenis_aktivitas' => 'pesanan',
            'deskripsi' => $deskripsiRiwayat,
            'status' => 'ditolak'
        ];
        
        if (!empty($existingRiwayat)) {
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan",
                'PATCH',
                json_encode($riwayatData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        } else {
            $riwayatInsert = [
                'id_pengguna' => $idPengguna,
                'jenis_aktivitas' => 'pesanan',
                'id_referensi' => $id_pesanan,
                'judul' => "Pesanan Ditolak - {$namaProduk}",
                'deskripsi' => $deskripsiRiwayat,
                'status' => 'ditolak',
                'created_at' => date('Y-m-d H:i:s')
            ];
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas",
                'POST',
                json_encode($riwayatInsert),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        }
        
        if ($idPengguna) {
            $notifikasiData = [
                'id_pengguna' => $idPengguna,
                'judul' => '❌ Pesanan Ditolak',
                'deskripsi' => "Pesanan {$namaProduk} ditolak.\nAlasan: {$alasan}\n\nSilakan lakukan pemesanan ulang.",
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            curlRequest(
                $supabaseUrl . "/rest/v1/notifikasi",
                'POST',
                json_encode($notifikasiData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
            
            sendFCMNotification(
                $idPengguna,
                'Pesanan Ditolak',
                "Pesanan {$namaProduk} ditolak. Alasan: {$alasan}",
                $supabaseUrl,
                $supabaseKey,
                ['type' => 'order_rejected', 'order_id' => $id_pesanan]
            );
        }
        
    } elseif ($status === 'selesai') {
        $deskripsiRiwayat = "Pesanan {$namaProduk} telah selesai dan diterima oleh pembeli";
        
        $checkRiwayat = curlRequest(
            $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan&jenis_aktivitas=eq.pesanan",
            'GET',
            null,
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
        );
        $existingRiwayat = json_decode($checkRiwayat['response'], true);
        
        $riwayatData = [
            'jenis_aktivitas' => 'pesanan',
            'deskripsi' => $deskripsiRiwayat,
            'status' => 'selesai'
        ];
        
        if (!empty($existingRiwayat)) {
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq.$id_pesanan",
                'PATCH',
                json_encode($riwayatData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        } else {
            $riwayatInsert = [
                'id_pengguna' => $idPengguna,
                'jenis_aktivitas' => 'pesanan',
                'id_referensi' => $id_pesanan,
                'judul' => "Pesanan Selesai - {$namaProduk}",
                'deskripsi' => $deskripsiRiwayat,
                'status' => 'selesai',
                'created_at' => date('Y-m-d H:i:s')
            ];
            curlRequest(
                $supabaseUrl . "/rest/v1/riwayat_aktivitas",
                'POST',
                json_encode($riwayatInsert),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        }
        
        if ($idPengguna) {
            $notifikasiData = [
                'id_pengguna' => $idPengguna,
                'judul' => '✅ Pesanan Selesai',
                'deskripsi' => "Pesanan {$namaProduk} telah selesai. Terima kasih telah berbelanja!",
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            curlRequest(
                $supabaseUrl . "/rest/v1/notifikasi",
                'POST',
                json_encode($notifikasiData),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
            
            sendFCMNotification(
                $idPengguna,
                'Pesanan Selesai',
                "Pesanan {$namaProduk} telah selesai. Terima kasih telah berbelanja!",
                $supabaseUrl,
                $supabaseKey,
                ['type' => 'order_completed', 'order_id' => $id_pesanan]
            );
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate']);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal update status: HTTP ' . $httpCode . ' - Response: ' . $patchResponse]);
}
?>