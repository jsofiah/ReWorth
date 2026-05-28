<?php
session_start();
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

// Hapus bukti lama di bucket jika ada
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

// Upload file bukti baru
$file     = $_FILES['bukti_penanganan'];
$filename = 'laporan/' . time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '', $file['name']);
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

// Update laporan: bukti_penanganan + status → selesai
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
    // Kirim notifikasi ke pengguna
    $getLaporan2 = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id) . "&select=id_pengguna";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $getLaporan2);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey","Authorization: Bearer $supabaseKey"]);
    $res2 = curl_exec($ch2); curl_close($ch2);
    $data2 = json_decode($res2, true);
    $idPengguna = $data2[0]['id_pengguna'] ?? null;

    if ($idPengguna) {
        $notif = [
            'id_pengguna' => $idPengguna,
            'judul'       => 'Laporan Sampah Selesai Ditangani',
            'deskripsi'   => 'Laporan sampah Anda telah selesai ditangani oleh petugas lapangan.',
            'is_read'     => false
        ];
        $nCh = curl_init();
        curl_setopt($nCh, CURLOPT_URL, $supabaseUrl . "/rest/v1/notifikasi");
        curl_setopt($nCh, CURLOPT_POST, true);
        curl_setopt($nCh, CURLOPT_POSTFIELDS, json_encode($notif));
        curl_setopt($nCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($nCh, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey","Authorization: Bearer $supabaseKey","Content-Type: application/json","Prefer: return=minimal"]);
        curl_exec($nCh); curl_close($nCh);
    }

    echo json_encode(['success'=>true,'message'=>'Penanganan berhasil diperbarui']);
} else {
    echo json_encode(['success'=>false,'message'=>'Gagal memperbarui data','debug'=>$pRes]);
}