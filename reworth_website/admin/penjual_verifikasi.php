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

function curlRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['response' => $response, 'httpCode' => $httpCode];
}

$getPenjual = curlRequest(
    $supabaseUrl . "/rest/v1/penjual?id_penjual=eq.$id_penjual",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$penjualData = json_decode($getPenjual['response'], true);
$penjual = $penjualData[0] ?? null;

if (!$penjual) {
    echo json_encode(['success' => false, 'message' => 'Data penjual tidak ditemukan']);
    exit;
}

$updatePenjual = curlRequest(
    $supabaseUrl . "/rest/v1/penjual?id_penjual=eq.$id_penjual",
    'PATCH',
    json_encode(['status' => 'verified']),
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
);

if (!($updatePenjual['httpCode'] == 200 || $updatePenjual['httpCode'] == 204)) {
    echo json_encode(['success' => false, 'message' => 'Gagal verifikasi penjual']);
    exit;
}

$getLangganan = curlRequest(
    $supabaseUrl . "/rest/v1/langganan?id_penjual=eq.$id_penjual&order=created_at.asc",
    'GET',
    null,
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey"]
);

$langgananList = json_decode($getLangganan['response'], true) ?? [];
$updatedLangganan = false;

foreach ($langgananList as $l) {
    $statusLangganan = strtolower($l['status'] ?? '');
    
    if ($statusLangganan == 'menunggu_verifikasi') {
        $updateLangganan = curlRequest(
            $supabaseUrl . "/rest/v1/langganan?id_langganan=eq." . $l['id_langganan'],
            'PATCH',
            json_encode(['status' => 'aktif']),
            ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
        );
        
        if ($updateLangganan['httpCode'] == 200 || $updateLangganan['httpCode'] == 204) {
            $updatedLangganan = true;
            
            $logDataLangganan = [
                'id_admin' => $_SESSION['id_admin'] ?? '',
                'aktivitas' => 'Mengkonfirmasi langganan penjual: ' . ($penjual['nama_penjual'] ?? '-'),
                'tabel_terkait' => 'langganan',
                'id_data' => $l['id_langganan'],
                'created_at' => date('c')
            ];
            
            curlRequest(
                $supabaseUrl . "/rest/v1/log_admin",
                'POST',
                json_encode($logDataLangganan),
                ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
            );
        }
        break;
    }
}

$logData = [
    'id_admin' => $_SESSION['id_admin'] ?? '',
    'aktivitas' => 'Memverifikasi akun penjual: ' . ($penjual['nama_penjual'] ?? '-'),
    'tabel_terkait' => 'penjual',
    'id_data' => $id_penjual,
    'created_at' => date('c')
];

curlRequest(
    $supabaseUrl . "/rest/v1/log_admin",
    'POST',
    json_encode($logData),
    ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]
);

$emailData = [
    'nama_penjual' => $penjual['nama_penjual'] ?? 'Penjual',
    'email' => $penjual['email'] ?? '',
    'login_url' => 'https://reworth-penjual.freedev.app'
];

$message = 'Penjual berhasil diverifikasi';
if ($updatedLangganan) {
    $message .= ' dan langganan pertama telah diaktifkan';
} else {
    $message .= ' (Tidak ada langganan yang perlu diaktifkan)';
}

echo json_encode([
    'success' => true,
    'message' => $message,
    'email_data' => $emailData,
    'langganan_activated' => $updatedLangganan
]);
?>