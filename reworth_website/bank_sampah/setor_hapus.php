<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$allowedRoles = ['bank sampah', 'admin', 'dlh'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$id       = trim($_POST['id']   ?? '');
$nama     = trim($_POST['nama'] ?? '');
$idAdmin  = $_SESSION['id_admin'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}

/* ───────── helper request ───────── */
function supabaseRequest($url, $key, $endpoint, $method = 'GET', $body = null, $extra = []) {
    $headers = array_merge([
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ], $extra);

    $ch = curl_init($url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true)];
}

/* ───────── hapus setor_sampah (detail_setor terhapus cascade) ───────── */
$deleteResult = supabaseRequest(
    $supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?id_setor=eq." . urlencode($id),
    'DELETE',
    null,
    ['Prefer: return=minimal']
);

// 204 = No Content = sukses DELETE
if (!in_array($deleteResult['code'], [200, 204])) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus transaksi dari database.'
    ]);
    exit;
}

/* ───────── tulis log_admin ───────── */
if (!empty($idAdmin)) {
    supabaseRequest(
        $supabaseUrl, $supabaseKey,
        "/rest/v1/log_admin",
        'POST',
        [
            'id_admin'      => $idAdmin,
            'aktivitas'     => 'hapus setor sampah: ' . $nama,
            'tabel_terkait' => 'setor_sampah',
            'id_data'       => null,
            'created_at'    => date('c')
        ],
        ['Prefer: return=minimal']
    );
}

require_once 'log_helper.php'; logAdminActivity($supabaseUrl, $supabaseKey, $_SESSION['id_admin'] ?? '', 'Menghapus transaksi setor', 'setor_sampah', $id ?? '');
    echo json_encode(['success' => true, 'message' => 'Transaksi berhasil dihapus.']);