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
        'wa_link' => $waLink
    ]);

} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui laporan', 'debug' => $response]);
}