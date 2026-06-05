<?php
session_start();
header('Content-Type: application/json');

// Tangkap semua error PHP sebagai JSON
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

$id         = trim($_POST['id']            ?? '');
$status     = trim($_POST['status']        ?? '');
$idPengguna = trim($_POST['id_pengguna']   ?? '');
$totalUang  = (float)($_POST['total_uang'] ?? 0);
$alasan     = trim($_POST['alasan']        ?? '');

$allowedStatus = ['diproses', 'selesai', 'ditolak'];
if (empty($id) || !in_array($status, $allowedStatus)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.', 'debug' => ['id' => $id, 'status' => $status]]);
    exit;
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

function sbGetSaldo($url, $key, $idPengguna) {
    $ch = curl_init($url . "/rest/v1/pengguna?id_pengguna=eq." . urlencode($idPengguna) . "&select=saldo_tabungan&limit=1");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json"
        ]
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;
    $data = json_decode($res, true);
    return isset($data[0]['saldo_tabungan']) ? (float)$data[0]['saldo_tabungan'] : null;
}

// 1. Update status
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
        'debug'   => ['http_code' => $result['code'], 'response' => $result['res'], 'curl_error' => $result['err']]
    ]);
    exit;
}

// 2. Tambah saldo jika selesai
if ($status === 'selesai' && !empty($idPengguna) && $totalUang > 0) {
    $saldoSekarang = sbGetSaldo($supabaseUrl, $supabaseKey, $idPengguna);
    if ($saldoSekarang === null) {
        echo json_encode(['success' => false, 'message' => 'Gagal ambil saldo pengguna.']);
        exit;
    }
    $saldoBaru = $saldoSekarang + $totalUang;
    $resSaldo  = sbPatch($supabaseUrl, $supabaseKey,
        "/rest/v1/pengguna?id_pengguna=eq." . urlencode($idPengguna),
        ['saldo_tabungan' => $saldoBaru]
    );
    if ($resSaldo['code'] < 200 || $resSaldo['code'] >= 300) {
        echo json_encode([
            'success' => false,
            'message' => 'Status diperbarui, tapi gagal tambah saldo.',
            'debug'   => ['http_code' => $resSaldo['code'], 'response' => $resSaldo['res']]
        ]);
        exit;
    }
}

$messages = [
    'diproses' => 'Transaksi dikonfirmasi dan sedang diproses.',
    'selesai'  => 'Transaksi selesai dan saldo berhasil ditambahkan.',
    'ditolak'  => 'Transaksi berhasil ditolak.',
];

require_once 'log_helper.php'; logAdminActivity($supabaseUrl, $supabaseKey, $_SESSION['id_admin'] ?? '', 'Mengubah status transaksi setor menjadi: ' . $status, 'setor_sampah', $id ?? '');
    echo json_encode(['success' => true, 'message' => $messages[$status]]);