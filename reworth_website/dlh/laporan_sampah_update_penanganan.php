<?php
require_once 'role_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$id = trim($_POST['id'] ?? '');
if (empty($id)) { echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }

if (!isset($_FILES['bukti_penanganan']) || $_FILES['bukti_penanganan']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'File bukti penanganan tidak valid']); exit;
}


$getLaporan = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id) . "&select=bukti_penanganan";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $getLaporan);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey","Authorization: Bearer $supabaseKey"]);
$resLaporan = curl_exec($ch); curl_close($ch);
$laporanData = json_decode($resLaporan, true);
$oldBukti    = $laporanData[0]['bukti_penanganan'] ?? '';

if (!empty($oldBukti)) {
    $delCh = curl_init();
    curl_setopt($delCh, CURLOPT_URL, $supabaseUrl . "/storage/v1/object/media/" . $oldBukti);
    curl_setopt($delCh, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($delCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($delCh, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey","Authorization: Bearer $supabaseKey"]);
    curl_exec($delCh); curl_close($delCh);
}


$file     = $_FILES['bukti_penanganan'];
$filename = 'lapor_sampah/' . time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);
$uploadUrl = $supabaseUrl . "/storage/v1/object/media/" . $filename;

$upCh = curl_init();
curl_setopt($upCh, CURLOPT_URL, $uploadUrl);
curl_setopt($upCh, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($upCh, CURLOPT_POSTFIELDS, file_get_contents($file['tmp_name']));
curl_setopt($upCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($upCh, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: " . $file['type'],
    "x-upsert: true",
    "Content-Length: " . filesize($file['tmp_name'])
]);
$upRes  = curl_exec($upCh);
$upCode = curl_getinfo($upCh, CURLINFO_HTTP_CODE);
curl_close($upCh);

if ($upCode !== 200 && $upCode !== 201) {
    echo json_encode(['success'=>false,'message'=>'Upload foto gagal','debug'=>$upRes]); exit;
}


$updateData = ['bukti_penanganan' => $filename, 'status' => 'selesai'];
$patchUrl   = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id);

$pCh = curl_init();
curl_setopt($pCh, CURLOPT_URL, $patchUrl);
curl_setopt($pCh, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($pCh, CURLOPT_POSTFIELDS, json_encode($updateData));
curl_setopt($pCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($pCh, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json",
    "Prefer: return=minimal"
]);
$pRes  = curl_exec($pCh);
$pCode = curl_getinfo($pCh, CURLINFO_HTTP_CODE);
curl_close($pCh);

if ($pCode === 200 || $pCode === 204) {
    $getLaporanDetail = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id)
                      . "&select=jenis_sampah,lokasi,id_pengguna";
    $chDetail = curl_init();
    curl_setopt($chDetail, CURLOPT_URL, $getLaporanDetail);
    curl_setopt($chDetail, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chDetail, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resDetail = curl_exec($chDetail);
    curl_close($chDetail);
    $detailData = json_decode($resDetail, true);
    $laporanDetail = $detailData[0] ?? null;
    $jenisSampah = $laporanDetail['jenis_sampah'] ?? 'Tidak diketahui';
    $lokasiLaporan = $laporanDetail['lokasi'] ?? 'Tidak diketahui';
    $idPenggunaRiwayat = $laporanDetail['id_pengguna'] ?? null;
    $idPetugas = $laporanDetail['id_petugas'] ?? null;

    $namaPetugas = 'Petugas Lapangan';
    if ($idPetugas) {
        $getPetugas = $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($idPetugas) . "&select=nama_petugas";
        $chPetugas = curl_init();
        curl_setopt($chPetugas, CURLOPT_URL, $getPetugas);
        curl_setopt($chPetugas, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chPetugas, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $resPetugas = curl_exec($chPetugas);
        curl_close($chPetugas);
        $petugasData = json_decode($resPetugas, true);
        $namaPetugas = $petugasData[0]['nama_petugas'] ?? 'Petugas Lapangan';
    }


    $logData = [
        'id_admin' => $_SESSION['id_admin'] ?? '',
        'aktivitas' => 'Menyelesaikan laporan sampah - Jenis: ' . $jenisSampah . ', Lokasi: ' . $lokasiLaporan . ' (Poin +20 untuk pengguna)',
        'tabel_terkait' => 'lapor_sampah',
        'id_data' => $id,
        'created_at' => date('c')
    ];

    $chLogInsert = curl_init($supabaseUrl . "/rest/v1/log_admin");
    curl_setopt($chLogInsert, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($chLogInsert, CURLOPT_POSTFIELDS, json_encode($logData));
    curl_setopt($chLogInsert, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]);
    curl_setopt($chLogInsert, CURLOPT_RETURNTRANSFER, true);
    curl_exec($chLogInsert);
    curl_close($chLogInsert);


    if ($idPenggunaRiwayat) {

        $checkRiwayat = $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq." . urlencode($id) 
                    . "&select=id_riwayat,status,deskripsi";
        $chCheck = curl_init();
        curl_setopt($chCheck, CURLOPT_URL, $checkRiwayat);
        curl_setopt($chCheck, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chCheck, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $resCheck = curl_exec($chCheck);
        $httpCodeCheck = curl_getinfo($chCheck, CURLINFO_HTTP_CODE);
        curl_close($chCheck);
        
        error_log("CHECK RIWAYAT - HTTP Code: " . $httpCodeCheck);
        error_log("CHECK RIWAYAT - Response: " . $resCheck);
        
        $existingRiwayat = json_decode($resCheck, true);
        
        $newDeskripsi = 'Laporan sampah ' . $jenisSampah . ' di ' . $lokasiLaporan . ' telah selesai ditangani oleh petugas lapangan (' . $namaPetugas . ').';
        
        if (!empty($existingRiwayat)) {

            $idRiwayat = $existingRiwayat[0]['id_riwayat'];
            

            $riwayatUpdateData = [
                'status' => 'selesai',
                'deskripsi' => $newDeskripsi,
                'perubahan_poin' => 20
            ];
            

            $updateRiwayatUrl = $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_riwayat=eq." . $idRiwayat;
            
            $chRiwayatUpdate = curl_init();
            curl_setopt($chRiwayatUpdate, CURLOPT_URL, $updateRiwayatUrl);
            curl_setopt($chRiwayatUpdate, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($chRiwayatUpdate, CURLOPT_POSTFIELDS, json_encode($riwayatUpdateData));
            curl_setopt($chRiwayatUpdate, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ]);
            curl_setopt($chRiwayatUpdate, CURLOPT_RETURNTRANSFER, true);
            $updateResult = curl_exec($chRiwayatUpdate);
            $updateHttpCode = curl_getinfo($chRiwayatUpdate, CURLINFO_HTTP_CODE);
            $updateError = curl_error($chRiwayatUpdate);
            curl_close($chRiwayatUpdate);
            
            error_log("UPDATE RIWAYAT - URL: " . $updateRiwayatUrl);
            error_log("UPDATE RIWAYAT - Data: " . json_encode($riwayatUpdateData));
            error_log("UPDATE RIWAYAT - HTTP Code: " . $updateHttpCode);
            error_log("UPDATE RIWAYAT - Response: " . $updateResult);
            error_log("UPDATE RIWAYAT - Error: " . $updateError);
            

            $verifyUrl = $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_riwayat=eq." . $idRiwayat;
            $chVerify = curl_init();
            curl_setopt($chVerify, CURLOPT_URL, $verifyUrl);
            curl_setopt($chVerify, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chVerify, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);
            $verifyResult = curl_exec($chVerify);
            curl_close($chVerify);
            error_log("VERIFY AFTER UPDATE: " . $verifyResult);
            
        } else {
            error_log("RIWAYAT TIDAK DITEMUKAN untuk id_referensi: " . $id);
            error_log("Mencoba INSERT riwayat baru...");
            

            $riwayatInsertData = [
                'id_pengguna' => $idPenggunaRiwayat,
                'jenis_aktivitas' => 'lapor_sampah',
                'id_referensi' => $id,
                'judul' => 'Laporan Sampah ' . $jenisSampah,
                'deskripsi' => $newDeskripsi,
                'status' => 'selesai',
                'perubahan_poin' => 20,
                'perubahan_saldo' => null,
                'created_at' => date('c')
            ];
            
            $chRiwayatInsert = curl_init($supabaseUrl . "/rest/v1/riwayat_aktivitas");
            curl_setopt($chRiwayatInsert, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($chRiwayatInsert, CURLOPT_POSTFIELDS, json_encode($riwayatInsertData));
            curl_setopt($chRiwayatInsert, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ]);
            curl_setopt($chRiwayatInsert, CURLOPT_RETURNTRANSFER, true);
            $insertResult = curl_exec($chRiwayatInsert);
            $insertHttpCode = curl_getinfo($chRiwayatInsert, CURLINFO_HTTP_CODE);
            curl_close($chRiwayatInsert);
            
            error_log("INSERT RIWAYAT - HTTP Code: " . $insertHttpCode);
            error_log("INSERT RIWAYAT - Response: " . $insertResult);
        }
        


        $getPoinUrl = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $idPenggunaRiwayat . "&select=poin";
        $chGetPoin = curl_init();
        curl_setopt($chGetPoin, CURLOPT_URL, $getPoinUrl);
        curl_setopt($chGetPoin, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chGetPoin, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $poinResult = curl_exec($chGetPoin);
        $poinHttpCode = curl_getinfo($chGetPoin, CURLINFO_HTTP_CODE);
        curl_close($chGetPoin);
        
        error_log("GET POIN - HTTP Code: " . $poinHttpCode);
        error_log("GET POIN - Response: " . $poinResult);
        
        $poinData = json_decode($poinResult, true);
        $poinSekarang = $poinData[0]['poin'] ?? 0;
        $poinBaru = $poinSekarang + 20;
        
        $updatePoinUrl = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $idPenggunaRiwayat;
        $chPoin = curl_init();
        curl_setopt($chPoin, CURLOPT_URL, $updatePoinUrl);
        curl_setopt($chPoin, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($chPoin, CURLOPT_POSTFIELDS, json_encode(['poin' => $poinBaru]));
        curl_setopt($chPoin, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);
        curl_setopt($chPoin, CURLOPT_RETURNTRANSFER, true);
        $poinUpdateResult = curl_exec($chPoin);
        $poinUpdateCode = curl_getinfo($chPoin, CURLINFO_HTTP_CODE);
        $poinUpdateError = curl_error($chPoin);
        curl_close($chPoin);
        
        error_log("UPDATE POIN - URL: " . $updatePoinUrl);
        error_log("UPDATE POIN - Data: " . json_encode(['poin' => $poinBaru]));
        error_log("UPDATE POIN - HTTP Code: " . $poinUpdateCode);
        error_log("UPDATE POIN - Response: " . $poinUpdateResult);
        error_log("UPDATE POIN - Error: " . $poinUpdateError);
        

        $verifyPoinUrl = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $idPenggunaRiwayat . "&select=poin";
        $chVerifyPoin = curl_init();
        curl_setopt($chVerifyPoin, CURLOPT_URL, $verifyPoinUrl);
        curl_setopt($chVerifyPoin, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chVerifyPoin, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $verifyPoinResult = curl_exec($chVerifyPoin);
        curl_close($chVerifyPoin);
        error_log("VERIFY POIN AFTER UPDATE: " . $verifyPoinResult);
    }
    

    $getLaporan2 = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id) . "&select=id_pengguna,lokasi,jenis_sampah";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $getLaporan2);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey","Authorization: Bearer $supabaseKey"]);
    $res2 = curl_exec($ch2); curl_close($ch2);
    $data2 = json_decode($res2, true);
    $idPengguna = $data2[0]['id_pengguna'] ?? null;
    $lokasiLaporanNotif = $data2[0]['lokasi'] ?? 'lokasi Anda';
    $jenisSampahNotif = $data2[0]['jenis_sampah'] ?? 'sampah';

    if ($idPengguna) {

        $notif = [
            'id_pengguna' => $idPengguna,
            'judul'       => '+20 Poin!',
            'deskripsi'   => 'Laporan sampah ' . $jenisSampahNotif . ' di ' . $lokasiLaporanNotif . ' telah selesai ditangani oleh petugas lapangan (' . $namaPetugas . ').',
            'is_read'     => false
        ];
        $nCh = curl_init();
        curl_setopt($nCh, CURLOPT_URL, $supabaseUrl . "/rest/v1/notifikasi");
        curl_setopt($nCh, CURLOPT_POST, true);
        curl_setopt($nCh, CURLOPT_POSTFIELDS, json_encode($notif));
        curl_setopt($nCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($nCh, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey","Authorization: Bearer $supabaseKey","Content-Type: application/json","Prefer: return=minimal"]);
        curl_exec($nCh); 
        curl_close($nCh);


        $fcmPayload = [
            'user_id' => $idPengguna,
            'title'   => '+20 Poin!',
            'body'    => 'Laporan sampah ' . $jenisSampahNotif . ' di ' . $lokasiLaporanNotif . ' telah selesai ditangani oleh petugas lapangan (' . $namaPetugas . ').'
        ];

        $chFcm = curl_init();
        curl_setopt($chFcm, CURLOPT_URL, "https://rxzrbyqqhkxemdjbcntc.supabase.co/functions/v1/send-user-notification");
        curl_setopt($chFcm, CURLOPT_POST, true);
        curl_setopt($chFcm, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
        curl_setopt($chFcm, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $supabaseKey",
            "apikey: $supabaseKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($chFcm, CURLOPT_RETURNTRANSFER, true);
        $fcmResponse = curl_exec($chFcm);
        $fcmHttpCode = curl_getinfo($chFcm, CURLINFO_HTTP_CODE);
        curl_close($chFcm);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Penanganan berhasil diperbarui',
        'fcm_http_code' => $fcmHttpCode ?? null,
        'fcm_response' => json_decode($fcmResponse ?? '{}', true)
    ]);
} else {
    echo json_encode(['success'=>false,'message'=>'Gagal memperbarui data','debug'=>$pRes]);
}
?>