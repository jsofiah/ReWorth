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
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Update laporan: status -> ditolak, set alasan_penolakan
$updateData = [
    'status'            => 'ditolak',
    'alasan_penolakan'  => $alasan
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
    // Kirim notifikasi ke pengguna
    $getLaporan = $supabaseUrl . "/rest/v1/lapor_sampah?id_laporan=eq." . urlencode($id) . "&select=id_pengguna";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $getLaporan);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $resLaporan = curl_exec($ch2);
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
        $notifUrl = $supabaseUrl . "/rest/v1/notifikasi";
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, $notifUrl);
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
    }

    echo json_encode(['success' => true, 'message' => 'Laporan berhasil ditolak']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui laporan', 'debug' => $response]);
}