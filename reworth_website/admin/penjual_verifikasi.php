<?php
session_start();

if (!isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$id_penjual = isset($_POST['id']) ? $_POST['id'] : (isset($_GET['id']) ? $_GET['id'] : null);

if (!$id_penjual) {
    echo json_encode(['success' => false, 'message' => 'ID penjual tidak ditemukan']);
    exit;
}

$urlUpdate = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq." . $id_penjual;

$data = ['status' => 'verified'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlUpdate);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Authorization: Bearer $supabaseKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 || $httpCode === 204) {
    echo json_encode(['success' => true, 'message' => 'Penjual berhasil diverifikasi']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal verifikasi penjual. HTTP: ' . $httpCode]);
}
?>