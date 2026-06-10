<?php
    require_once 'role_check.php';

    header('Content-Type: application/json');

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $id = trim($_POST['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $getUrl = $supabaseUrl . "/rest/v1/apresiasi?id_apresiasi=eq." . urlencode($id);
    $getCh = curl_init();
    curl_setopt($getCh, CURLOPT_URL, $getUrl);
    curl_setopt($getCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($getCh, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $getResponse = curl_exec($getCh);
    $getHttpCode = curl_getinfo($getCh, CURLINFO_HTTP_CODE);
    curl_close($getCh);
    
    $apresiasiData = json_decode($getResponse, true);
    $namaApresiasi = $apresiasiData[0]['nama_apresiasi'] ?? 'Apresiasi';
    $rwName = $apresiasiData[0]['rw_name'] ?? '';

    $deleteUrl = $supabaseUrl . "/rest/v1/apresiasi?id_apresiasi=eq." . urlencode($id);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $deleteUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 || $code === 204) {
        $logData = [
            'id_admin' => $_SESSION['id_admin'] ?? '',
            'aktivitas' => 'Menghapus apresiasi: ' . $namaApresiasi . ' - RW ' . $rwName,
            'tabel_terkait' => 'apresiasi',
            'id_data' => $id,
            'created_at' => date('c')
        ];

        $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
        curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($logCh, CURLOPT_POSTFIELDS, json_encode($logData));
        curl_setopt($logCh, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);
        curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
        $logResponse = curl_exec($logCh);
        $logHttpCode = curl_getinfo($logCh, CURLINFO_HTTP_CODE);
        curl_close($logCh);

        echo json_encode([
            'success' => true, 
            'message' => 'Apresiasi berhasil dihapus',
            'debug_log' => [
                'log_http_code' => $logHttpCode,
                'log_response' => $logResponse
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data', 'debug' => $response]);
    }
?>