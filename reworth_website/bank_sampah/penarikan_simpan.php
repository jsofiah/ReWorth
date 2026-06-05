<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

$id_pengguna      = $input['id_pengguna'] ?? '';
$jumlah_penarikan = floatval($input['jumlah'] ?? 0);
$saldo_lama       = floatval($input['saldo_lama'] ?? 0);

if (empty($id_pengguna)) {
    echo json_encode(['success' => false, 'message' => 'Nasabah tidak dipilih']);
    exit;
}

if ($jumlah_penarikan <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jumlah penarikan harus lebih dari 0']);
    exit;
}

if ($jumlah_penarikan > $saldo_lama) {
    echo json_encode(['success' => false, 'message' => 'Jumlah penarikan melebihi saldo']);
    exit;
}

$saldo_baru = $saldo_lama - $jumlah_penarikan;

// 1. Update saldo di tabel pengguna
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . urlencode($id_pengguna),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_HTTPHEADER     => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode(['saldo_tabungan' => $saldo_baru])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal update saldo. HTTP: ' . $httpCode
    ]);
    exit;
}

// 2. Insert ke tabel penarikan_saldo
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL            => $supabaseUrl . "/rest/v1/penarikan_saldo",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'id_pengguna' => $id_pengguna,
        'jumlah'      => $jumlah_penarikan
    ])
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($httpCode2 < 200 || $httpCode2 >= 300) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan data penarikan. HTTP: ' . $httpCode2
    ]);
    exit;
}

$responseArray = json_decode($response2, true);
$idPenarikan = $responseArray[0]['id_penarikan'] ?? null;

require_once 'log_helper.php';
logAdminActivity($supabaseUrl, $supabaseKey, $_SESSION['id_admin'] ?? '', "Menambahkan penarikan saldo baru sebesar Rp " . number_format($jumlah_penarikan, 0, ',', '.'), 'penarikan_saldo', $idPenarikan);

echo json_encode([
    'success' => true,
    'message' => 'Penarikan berhasil! Saldo baru: Rp ' . number_format($saldo_baru, 0, ',', '.')
]);
?>