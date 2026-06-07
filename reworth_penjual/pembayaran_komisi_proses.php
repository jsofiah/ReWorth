<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_penjual'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

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
    $error = curl_error($ch);
    curl_close($ch);
    return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
}

$userId = $_SESSION['id_penjual'];

// Upload bukti pembayaran ke storage (pakai metode POST seperti upload event)
function uploadBukti($file, $userId) {
    global $supabaseUrl, $supabaseKey;
    
    // Validasi file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File tidak valid'];
    }
    
    // Validasi tipe file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipe file tidak didukung. Gunakan JPG, PNG, atau WEBP'];
    }
    
    // Validasi ukuran file (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB'];
    }
    
    // Generate filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'komisi/' . $userId . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    
    $storageUrl = $supabaseUrl . "/storage/v1/object/media/" . $filename;
    $fileData = file_get_contents($file['tmp_name']);
    
    if ($fileData === false) {
        return ['success' => false, 'message' => 'Gagal membaca file'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $storageUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: " . $fileType,
        "x-upsert: true"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("Upload Bukti - URL: $storageUrl");
    error_log("Upload Bukti - HTTP Code: $httpCode");
    error_log("Upload Bukti - Response: $response");
    error_log("Upload Bukti - Error: $error");
    
    if ($httpCode == 200 || $httpCode == 201) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Gagal upload bukti pembayaran. HTTP: ' . $httpCode . ' - ' . $error];
    }
}

if (isset($_POST['bayar_semua'])) {
    // Bayar semua komisi yang pending
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Harap upload bukti pembayaran']);
        exit;
    }
    
    $uploadResult = uploadBukti($_FILES['bukti_pembayaran'], $userId);
    if (!$uploadResult['success']) {
        echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
        exit;
    }
    
    $buktiPath = $uploadResult['filename'];
    
    // Update semua komisi dengan status pending
    $updateData = [
        'status_pembayaran' => 'dibayar',
        'tanggal_pembayaran' => date('Y-m-d'),
        'bukti_pembayaran' => $buktiPath
    ];
    
    $result = curlRequest(
        $supabaseUrl . "/rest/v1/komisi?id_penjual=eq.$userId&status_pembayaran=eq.pending",
        'PATCH',
        json_encode($updateData),
        [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]
    );
    
    if ($result['httpCode'] == 200 || $result['httpCode'] == 204) {
        echo json_encode(['success' => true, 'message' => 'Semua komisi berhasil dibayar, menunggu konfirmasi admin']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui komisi: ' . $result['response']]);
    }
    
} else {
    // Bayar satu komisi berdasarkan ID
    $komisiId = $_POST['komisi_id'] ?? '';
    
    if (empty($komisiId)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Harap upload bukti pembayaran']);
        exit;
    }
    
    $uploadResult = uploadBukti($_FILES['bukti_pembayaran'], $userId);
    if (!$uploadResult['success']) {
        echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
        exit;
    }
    
    $buktiPath = $uploadResult['filename'];
    
    $updateData = [
        'status_pembayaran' => 'dibayar',
        'tanggal_pembayaran' => date('Y-m-d'),
        'bukti_pembayaran' => $buktiPath
    ];
    
    $result = curlRequest(
        $supabaseUrl . "/rest/v1/komisi?id_komisi=eq.$komisiId",
        'PATCH',
        json_encode($updateData),
        [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]
    );
    
    if ($result['httpCode'] == 200 || $result['httpCode'] == 204) {
        echo json_encode(['success' => true, 'message' => 'Komisi berhasil dibayar, menunggu konfirmasi admin']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui komisi: ' . $result['response']]);
    }
}
?>