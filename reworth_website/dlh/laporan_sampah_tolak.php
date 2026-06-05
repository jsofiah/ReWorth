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

$id     = trim($_POST['id'] ?? '');
$alasan = trim($_POST['alasan'] ?? '');

if (empty($id) || empty($alasan)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap', 'fcm_http_code' => $fcmHttpCode ?? null, 'fcm_response' => $fcmResponse ?? '{}', true]);
    exit;
}

// Update laporan: status -> ditolak, set alasan_penolakan
$updateData = [
    'status'           => 'ditolak',
    'alasan_penolakan' => $alasan
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

    $logData = [
        'id_admin' => $_SESSION['id_admin'] ?? '',
        'aktivitas' => 'Menolak laporan sampah - Jenis: ' . $jenisSampah . ', Lokasi: ' . $lokasiLaporan . ', Alasan: ' . $alasan,
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

    if ($idPenggunaRiwayat) {
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
            'status' => 'ditolak',
            'deskripsi' => 'Laporan sampah ' . $jenisSampah . ' di ' . $lokasiLaporan . ' ditolak. Alasan: ' . $alasan,
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
                'judul' => 'Laporan Sampah ' . $jenisSampah,
                'deskripsi' => 'Laporan sampah ' . $jenisSampah . ' di ' . $lokasiLaporan . ' ditolak. Alasan: ' . $alasan,
                'status' => 'ditolak',
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
    // Ambil id_pengguna untuk notifikasi
    $getLaporan = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id) . "&select=id_pengguna";
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
    $idPengguna  = $laporanData[0]['id_pengguna'] ?? null;

    if ($idPengguna) {
        $notifData = [
            'id_pengguna' => $idPengguna,
            'judul'       => 'Laporan Sampah Ditolak',
            'deskripsi'   => 'Laporan sampah Anda ditolak dengan alasan: ' . $alasan,
            'is_read'     => false
        ];
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, $supabaseUrl . "/rest/v1/notifikasi");
        curl_setopt($ch3, CURLOPT_POST, true);
        curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($notifData));
        curl_setopt($ch3, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch3);
        curl_close($ch3);

        $fcmPayload = [
            'user_id' => $idPengguna,
            'title'   => 'Laporan Sampah Ditolak',
            'body'    => 'Laporan sampah Anda ditolak. Alasan: ' . $alasan
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

    echo json_encode(['success' => true, 'message' => 'Laporan berhasil ditolak']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui laporan', 'debug' => $response]);
}