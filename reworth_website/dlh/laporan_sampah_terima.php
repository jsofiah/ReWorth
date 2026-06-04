<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$id         = trim($_POST['id'] ?? '');
$id_petugas = trim($_POST['id_petugas'] ?? '');

if (empty($id) || empty($id_petugas)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$updateData = [
    'status'     => 'diproses',
    'id_petugas' => $id_petugas
];

$url = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json",
    "Prefer: return=minimal"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 || $httpCode === 204) {

    $getLaporanForLog = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id)
                      . "&select=jenis_sampah,lokasi";
    $chLog = curl_init();
    curl_setopt($chLog, CURLOPT_URL, $getLaporanForLog);
    curl_setopt($chLog, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chLog, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resLog = curl_exec($chLog);
    curl_close($chLog);
    $logDataLaporan = json_decode($resLog, true);
    $laporanInfo = $logDataLaporan[0] ?? null;
    $jenisSampah = $laporanInfo['jenis_sampah'] ?? 'Tidak diketahui';
    $lokasiLaporan = $laporanInfo['lokasi'] ?? 'Tidak diketahui';

    $getPetugasForLog = $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($id_petugas)
                      . "&select=nama_petugas";
    $chPetugasLog = curl_init();
    curl_setopt($chPetugasLog, CURLOPT_URL, $getPetugasForLog);
    curl_setopt($chPetugasLog, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPetugasLog, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resPetugasLog = curl_exec($chPetugasLog);
    curl_close($chPetugasLog);
    $petugasDataLog = json_decode($resPetugasLog, true);
    $namaPetugasLog = $petugasDataLog[0]['nama_petugas'] ?? 'Petugas';

    $logData = [
        'id_admin' => $_SESSION['id_admin'] ?? '',
        'aktivitas' => 'Mengkonfirmasi laporan sampah - Lokasi: ' . $lokasiLaporan . ', Diteruskan ke: ' . $namaPetugasLog,
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
    $logResponse = curl_exec($chLogInsert);
    $logHttpCode = curl_getinfo($chLogInsert, CURLINFO_HTTP_CODE);
    curl_close($chLogInsert);

    $getLaporanForRiwayat = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id)
                          . "&select=jenis_sampah,lokasi,deskripsi,id_pengguna";
    $chRiwayat = curl_init();
    curl_setopt($chRiwayat, CURLOPT_URL, $getLaporanForRiwayat);
    curl_setopt($chRiwayat, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chRiwayat, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resRiwayat = curl_exec($chRiwayat);
    curl_close($chRiwayat);
    $riwayatData = json_decode($resRiwayat, true);
    $laporanRiwayat = $riwayatData[0] ?? null;
    $idPenggunaRiwayat = $laporanRiwayat['id_pengguna'] ?? null;

    if ($idPenggunaRiwayat) {
        // Cek apakah sudah ada riwayat dengan id_referensi dan jenis_aktivitas ini
        $checkRiwayat = $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_referensi=eq." . urlencode($id) 
                      . "&jenis_aktivitas=eq.lapor_sampah&select=id_riwayat";
        $chCheck = curl_init();
        curl_setopt($chCheck, CURLOPT_URL, $checkRiwayat);
        curl_setopt($chCheck, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chCheck, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        $resCheck = curl_exec($chCheck);
        curl_close($chCheck);
        $existingRiwayat = json_decode($resCheck, true);
        
        $riwayatUpdateData = [
            'status' => 'diproses',
            'deskripsi' => 'Laporan sampah ' . ($laporanRiwayat['jenis_sampah'] ?? '') . ' di ' . ($laporanRiwayat['lokasi'] ?? '') . ' telah diterima dan sedang dalam proses penanganan oleh petugas lapangan.',
        ];
        
        if (!empty($existingRiwayat)) {
            $idRiwayat = $existingRiwayat[0]['id_riwayat'];
            $updateRiwayatUrl = $supabaseUrl . "/rest/v1/riwayat_aktivitas?id_riwayat=eq." . $idRiwayat;
            $chRiwayatUpdate = curl_init();
            curl_setopt($chRiwayatUpdate, CURLOPT_URL, $updateRiwayatUrl);
            curl_setopt($chRiwayatUpdate, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($chRiwayatUpdate, CURLOPT_POSTFIELDS, json_encode($riwayatUpdateData));
            curl_setopt($chRiwayatUpdate, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey",
                "Content-Type: application/json",
                "Prefer: return=minimal"
            ]);
            curl_setopt($chRiwayatUpdate, CURLOPT_RETURNTRANSFER, true);
            curl_exec($chRiwayatUpdate);
            curl_close($chRiwayatUpdate);
        } else {
            $riwayatInsertData = [
                'id_pengguna' => $idPenggunaRiwayat,
                'jenis_aktivitas' => 'lapor_sampah',
                'id_referensi' => $id,
                'judul' => 'Laporan Sampah ' . ($laporanRiwayat['jenis_sampah'] ?? ''),
                'deskripsi' => 'Laporan sampah ' . ($laporanRiwayat['jenis_sampah'] ?? '') . ' di ' . ($laporanRiwayat['lokasi'] ?? '') . ' telah diterima dan sedang dalam proses penanganan oleh petugas lapangan.',
                'status' => 'diproses',
                'perubahan_poin' => 0,
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
                "Prefer: return=minimal"
            ]);
            curl_setopt($chRiwayatInsert, CURLOPT_RETURNTRANSFER, true);
            curl_exec($chRiwayatInsert);
            curl_close($chRiwayatInsert);
        }
    }


    $getLaporan = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id)
                . "&select=id_pengguna,lokasi,jenis_sampah,deskripsi,created_at,pengguna!lapor_sampah_id_pengguna_fkey(nama_lengkap)";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $getLaporan);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resLaporan  = curl_exec($ch2);
    curl_close($ch2);
    $laporanData = json_decode($resLaporan, true);
    $laporan     = $laporanData[0] ?? null;
    $idPengguna  = $laporan['id_pengguna'] ?? null;

    $getPetugas = $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($id_petugas)
                . "&select=nama_petugas,no_telepon";
    $ch3 = curl_init();
    curl_setopt($ch3, CURLOPT_URL, $getPetugas);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resPetugas  = curl_exec($ch3);
    curl_close($ch3);
    $petugasData = json_decode($resPetugas, true);
    $petugas     = $petugasData[0] ?? null;

    if ($idPengguna) {
        $notifData = [
            'id_pengguna' => $idPengguna,
            'judul'       => 'Laporan Sampah Diterima',
            'deskripsi'   => 'Laporan sampah Anda telah diterima dan sedang dalam proses penanganan oleh petugas lapangan.',
            'is_read'     => false
        ];
        $ch4 = curl_init();
        curl_setopt($ch4, CURLOPT_URL, $supabaseUrl . "/rest/v1/notifikasi");
        curl_setopt($ch4, CURLOPT_POST, true);
        curl_setopt($ch4, CURLOPT_POSTFIELDS, json_encode($notifData));
        curl_setopt($ch4, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch4);
        curl_close($ch4);

        $fcmPayload = [
            'user_id' => $idPengguna,
            'title'   => 'Laporan Sampah Diterima',
            'body'    => 'Laporan sampah Anda telah diterima dan sedang dalam proses penanganan oleh petugas lapangan.'
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

        $fcmResponse = curl_exec($chFcm);

        $fcmHttpCode = curl_getinfo(
            $chFcm,
            CURLINFO_HTTP_CODE
        );

        curl_close($chFcm);

    }

    $waLink = null;
    if ($petugas && !empty($petugas['no_telepon'])) {
        $noWa = preg_replace('/[^0-9]/', '', $petugas['no_telepon']);

        if (substr($noWa, 0, 1) === '0') {
            $noWa = '62' . substr($noWa, 1);
        }

        $namaLengkap = $laporan['pengguna']['nama_lengkap'] ?? '-';
        $lokasi      = $laporan['lokasi'] ?? '-';
        $jenis       = $laporan['jenis_sampah'] ?? '-';
        $deskripsi   = $laporan['deskripsi'] ?? '-';
        $tglLapor    = !empty($laporan['created_at'])
                       ? date('d F Y', strtotime($laporan['created_at']))
                       : '-';
        $namaPetugas = $petugas['nama_petugas'] ?? '-';

        $pesan = "Halo *{$namaPetugas}*, Anda mendapat tugas penanganan laporan sampah baru.\n\n"
               . "*Detail Laporan:*\n"
               . "Pelapor     : {$namaLengkap}\n"
               . "Jenis       : {$jenis}\n"
               . "Lokasi      : {$lokasi}\n"
               . "Deskripsi   : {$deskripsi}\n"
               . "Tgl Lapor   : {$tglLapor}\n\n"
               . "Harap segera ditangani. Terima kasih.";

        $waLink = "https://wa.me/{$noWa}?text=" . rawurlencode($pesan);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Laporan berhasil diterima',
        'wa_link' => $waLink,

        'fcm_http_code' => $fcmHttpCode,
        'fcm_response' => json_decode($fcmResponse, true)

    ]);

} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui laporan', 'debug' => $response]);
}