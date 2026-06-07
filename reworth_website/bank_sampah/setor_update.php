<?php
session_start();
header('Content-Type: application/json');


set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr"]);
    exit;
});

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$adminId = $_SESSION['id_admin'] ?? '';
$adminName = $_SESSION['nama_admin'] ?? 'Admin';


function sbGet($url, $key, $ep) {
    $ch = curl_init($url . $ep);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [
        "apikey: $key", "Authorization: Bearer $key", "Content-Type: application/json"
    ]]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c === 200 ? (json_decode($r, true) ?: []) : [];
}

function sbPatch($url, $key, $endpoint, $body) {
    $ch = curl_init($url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]
    ]);
    $res      = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'res' => $res, 'err' => $curlErr];
}

function sbPost($url, $key, $endpoint, $body) {
    $ch = curl_init($url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json"
        ]
    ]);
    $res      = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'res' => $res];
}

function sbPostFCM($url, $key, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $key",
            "apikey: $key",
            "Content-Type: application/json"
        ]
    ]);
    $res      = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'res' => $res];
}


$action = trim($_POST['action'] ?? 'status'); 


if ($action === 'detail') {
    $idDetail = trim($_POST['id_detail'] ?? '');
    $berat = (float)($_POST['berat'] ?? 0);
    $idSetor = trim($_POST['id_setor'] ?? '');

    if (empty($idDetail) || $berat <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid. Berat harus lebih dari 0']);
        exit;
    }

    
    
    $detail = sbGet($supabaseUrl, $supabaseKey,
        "/rest/v1/detail_setor?id_detail=eq." . urlencode($idDetail) . "&limit=1");
    
    
    error_log("Detail yang dicari: id_detail = " . $idDetail);
    error_log("Hasil query detail: " . print_r($detail, true));
    
    if (empty($detail)) {
        echo json_encode(['success' => false, 'message' => 'Detail tidak ditemukan', 'debug' => ['id_detail' => $idDetail]]);
        exit;
    }
    
    $hargaPerKg = (float)($detail[0]['harga_per_kg'] ?? 0);
    
    
    error_log("Harga per kg: " . $hargaPerKg);
    error_log("Berat baru: " . $berat);
    
    if ($hargaPerKg <= 0) {
        echo json_encode(['success' => false, 'message' => 'Harga per kg tidak valid (0 atau NULL)', 'debug' => ['harga_per_kg' => $hargaPerKg]]);
        exit;
    }
    
    $subtotal = $berat * $hargaPerKg;
    
    
    error_log("Subtotal baru: " . $subtotal);

    
    $result = sbPatch($supabaseUrl, $supabaseKey,
        "/rest/v1/detail_setor?id_detail=eq." . urlencode($idDetail),  
        [
            'berat' => $berat,
            'subtotal' => $subtotal
        ]
    );

    if ($result['code'] < 200 || $result['code'] >= 300) {
        echo json_encode(['success' => false, 'message' => 'Gagal update detail', 'debug' => $result]);
        exit;
    }

    
    $details = sbGet($supabaseUrl, $supabaseKey,
        "/rest/v1/detail_setor?id_setor=eq." . urlencode($idSetor) . "&select=subtotal");

    $newTotal = 0;
    foreach ($details as $d) {
        $newTotal += (float)($d['subtotal'] ?? 0);
    }

    
    error_log("Total uang baru: " . $newTotal);

    
    $updateTotal = sbPatch($supabaseUrl, $supabaseKey,
        "/rest/v1/setor_sampah?id_setor=eq." . urlencode($idSetor),
        ['total_uang' => $newTotal]
    );

    if ($updateTotal['code'] >= 200 && $updateTotal['code'] < 300) {
        echo json_encode(['success' => true, 'message' => 'Berhasil update berat', 'new_total_uang' => $newTotal]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update total uang', 'debug' => $updateTotal]);
    }
}



elseif ($action === 'status') {
    $id         = trim($_POST['id']            ?? '');
    $status     = trim($_POST['status']        ?? '');
    $idPengguna = trim($_POST['id_pengguna']   ?? '');
    $totalUang  = (float)($_POST['total_uang'] ?? 0);
    $alasan     = trim($_POST['alasan']        ?? '');

    
    $allowedStatus = ['selesai', 'ditolak'];
    if (empty($id) || !in_array($status, $allowedStatus)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.', 'debug' => ['id' => $id, 'status' => $status]]);
        exit;
    }

    
    $setorData = sbGet($supabaseUrl, $supabaseKey,
        "/rest/v1/setor_sampah?id_setor=eq." . urlencode($id) . "&limit=1"
    );
    
    
    error_log("Setor Data: " . print_r($setorData, true));
    
    if (empty($setorData)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Data transaksi tidak ditemukan',
            'debug' => ['id' => $id, 'result' => $setorData]
        ]);
        exit;
    }

    
    $setor = $setorData[0];
    $idPenggunaDb = $setor['id_pengguna'] ?? $idPengguna;
    $totalUangDb = (float)($setor['total_uang'] ?? $totalUang);
    $pengguna = $setor['pengguna'] ?? [];
    $namaPengguna = $pengguna['nama_lengkap'] ?? 'Pengguna';
    $saldoSekarang = (float)($pengguna['saldo_tabungan'] ?? 0);
    $poinSekarang = (int)($pengguna['poin'] ?? 0);
    $poinDidapat = 10; 

    
    $patchBody = ['status' => $status];
    if ($status === 'ditolak' && !empty($alasan)) {
        $patchBody['alasan_penolakan'] = $alasan;
    }

    $result = sbPatch($supabaseUrl, $supabaseKey,
        "/rest/v1/setor_sampah?id_setor=eq." . urlencode($id),
        $patchBody
    );

    if ($result['code'] < 200 || $result['code'] >= 300) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal update status.',
            'debug'   => ['http_code' => $result['code'], 'response' => $result['res']]
        ]);
        exit;
    }

    
    
    if ($status === 'selesai' && !empty($idPenggunaDb) && $totalUangDb > 0) {
        
        
        $penggunaResult = sbGet($supabaseUrl, $supabaseKey,
            "/rest/v1/pengguna?id_pengguna=eq." . urlencode($idPenggunaDb) . "&select=saldo_tabungan,poin&limit=1"
        );
        
        if (empty($penggunaResult)) {
            echo json_encode(['success' => false, 'message' => 'Data pengguna tidak ditemukan']);
            exit;
        }
        
        $saldoSekarang = (float)($penggunaResult[0]['saldo_tabungan'] ?? 0);
        $poinSekarang = (int)($penggunaResult[0]['poin'] ?? 0);  
        
        
        $saldoBaru = $saldoSekarang + $totalUangDb;
        $poinBaru = $poinSekarang + $poinDidapat;
        
        
        $updateUser = sbPatch($supabaseUrl, $supabaseKey,
            "/rest/v1/pengguna?id_pengguna=eq." . urlencode($idPenggunaDb),
            [
                'saldo_tabungan' => $saldoBaru,
                'poin' => $poinBaru   
            ]
        );
        
        if ($updateUser['code'] < 200 || $updateUser['code'] >= 300) {
            echo json_encode([
                'success' => false,
                'message' => 'Status diperbarui, tapi gagal update saldo pengguna.',
                'debug' => $updateUser
            ]);
            exit;
        }
        
        
        error_log("Saldo: $saldoSekarang + $totalUangDb = $saldoBaru");
        error_log("Poin: $poinSekarang + $poinDidapat = $poinBaru");
    }

    
    if ($status === 'selesai') {
        $judul = 'Setor Sampah';
        $deskripsi = "Setor sampah Anda dengan total Rp" . number_format($totalUangDb, 0, ',', '.') . " telah berhasil diverifikasi oleh $adminName dan ditambahkan ke saldo.";
        $perubahanPoin = $poinDidapat;
        $perubahanSaldo = $totalUangDb;
        $statusRiwayat = 'selesai';
    } else {
        $judul = 'Setor Sampah';
        $deskripsi = "Setor sampah Anda dengan total Rp" . number_format($totalUangDb, 0, ',', '.') . " ditolak oleh $adminName. Alasan: $alasan";
        $perubahanPoin = 0;
        $perubahanSaldo = 0;
        $statusRiwayat = 'ditolak';
    }

    
    $existingRiwayat = sbGet($supabaseUrl, $supabaseKey,
        "/rest/v1/riwayat_aktivitas?id_referensi=eq." . urlencode($id) . "&jenis_aktivitas=eq.setor_sampah&select=id_riwayat&limit=1"
    );

    if (!empty($existingRiwayat)) {
        $idRiwayat = $existingRiwayat[0]['id_riwayat'];
        sbPatch($supabaseUrl, $supabaseKey,
            "/rest/v1/riwayat_aktivitas?id_riwayat=eq." . urlencode($idRiwayat),
            [
                'status' => $statusRiwayat,
                'deskripsi' => $deskripsi,
                'perubahan_poin' => $perubahanPoin,
                'perubahan_saldo' => $perubahanSaldo
            ]
        );
    } else {
        $riwayatData = [
            'id_pengguna' => $idPenggunaDb,
            'jenis_aktivitas' => 'setor_sampah',
            'id_referensi' => $id,
            'judul' => $judul,
            'deskripsi' => $deskripsi,
            'status' => $statusRiwayat,
            'perubahan_poin' => $perubahanPoin,
            'perubahan_saldo' => $perubahanSaldo,
            'created_at' => date('c')
        ];
        sbPost($supabaseUrl, $supabaseKey, "/rest/v1/riwayat_aktivitas", $riwayatData);
    }

    
    $notifJudul = $status === 'selesai' ? 'Setor Sampah Berhasil Diverifikasi' : 'Setor Sampah Ditolak';
    $notifDeskripsi = $status === 'selesai' 
        ? "Selamat! Setor sampah Anda senilai Rp" . number_format($totalUangDb, 0, ',', '.') . " telah diverifikasi. Saldo Anda bertambah Rp" . number_format($totalUangDb, 0, ',', '.') . " dan Anda mendapatkan +$poinDidapat poin."
        : "Mohon maaf, setor sampah Anda senilai Rp" . number_format($totalUangDb, 0, ',', '.') . " ditolak. Alasan: $alasan";

    $notifData = [
        'id_pengguna' => $idPenggunaDb,
        'judul' => $notifJudul,
        'deskripsi' => $notifDeskripsi,
        'is_read' => false,
        'created_at' => date('c')
    ];
    sbPost($supabaseUrl, $supabaseKey, "/rest/v1/notifikasi", $notifData);

    
    $fcmSent = false;
    $fcmHttpCode = null;
    $fcmResponse = null;

    if (!empty($idPenggunaDb)) {
        $fcmPayload = [
            'user_id' => $idPenggunaDb,
            'title'   => $notifJudul,
            'body'    => $notifDeskripsi
        ];

        $chFcm = curl_init();

        curl_setopt(
            $chFcm,
            CURLOPT_URL,
            "https://rxzrbyqqhkxemdjbcntc.supabase.co/functions/v1/send-user-notification"
        );

        curl_setopt($chFcm, CURLOPT_POST, true);
        curl_setopt($chFcm, CURLOPT_POSTFIELDS, json_encode($fcmPayload));

        curl_setopt($chFcm, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $supabaseKey",
            "apikey: $supabaseKey",
            "Content-Type: application/json"
        ]);

        curl_setopt($chFcm, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chFcm, CURLOPT_TIMEOUT, 10); 

        $fcmResponse = curl_exec($chFcm);
        $fcmHttpCode = curl_getinfo($chFcm, CURLINFO_HTTP_CODE);
        $fcmError = curl_error($chFcm);
        
        curl_close($chFcm);
        
        $fcmSent = ($fcmHttpCode === 200);
        
        
        error_log("FCM Response Code: " . $fcmHttpCode);
        error_log("FCM Response: " . $fcmResponse);
        if ($fcmError) {
            error_log("FCM Error: " . $fcmError);
        }
    }

    
    $logData = [
        'id_admin' => $adminId,
        'aktivitas' => $status === 'selesai' 
            ? "Memverifikasi setor sampah dari $namaPengguna (Rp" . number_format($totalUangDb, 0, ',', '.') . ")"
            : "Menolak setor sampah dari $namaPengguna (Rp" . number_format($totalUangDb, 0, ',', '.') . ") - Alasan: $alasan",
        'tabel_terkait' => 'setor_sampah',
        'id_data' => $id,
        'created_at' => date('c')
    ];
    sbPost($supabaseUrl, $supabaseKey, "/rest/v1/log_admin", $logData);

    
    $message = $status === 'selesai' 
        ? "Transaksi berhasil diverifikasi. Saldo dan poin telah ditambahkan."
        : "Transaksi berhasil ditolak.";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'status' => $status,
        'fcm_sent' => $fcmSent,
        'fcm_response' => json_decode($fcmResponse, true)
    ]);
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
}
?>