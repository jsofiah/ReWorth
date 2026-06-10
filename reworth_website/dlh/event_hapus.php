<?php
    require_once 'role_check.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized"
        ]);
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        echo json_encode([
            "success" => false,
            "message" => "ID event kosong"
        ]);
        exit;
    }

    $headers = [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ];

    $getUrl = $supabaseUrl . "/rest/v1/event?id_event=eq." . urlencode($id) . "&select=foto_event";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $getUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode([
            "success" => false,
            "message" => "Gagal mengambil data event"
        ]);
        exit;
    }

    $data = json_decode($response, true);

    $fotoLama = $data[0]['foto_event'] ?? '';


    if (!empty($fotoLama)) {
        $deleteImageUrl =
            $supabaseUrl . "/storage/v1/object/media";

        $deleteBody = json_encode([
            "prefixes" => [$fotoLama]
        ]);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $deleteImageUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $deleteBody);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);

        $deleteResponse = curl_exec($ch);

        $deleteHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
    }


    $deleteUrl =
        $supabaseUrl . "/rest/v1/event?id_event=eq." . urlencode($id);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $deleteUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Prefer: return=minimal"
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $error = curl_error($ch);

    curl_close($ch);

    if ($httpCode == 204) {
        $logData = [
            'id_admin' => $_SESSION['id_admin'],
            'aktivitas' => 'Menghapus event dengan ID: ' . $id,
            'tabel_terkait' => 'event',
            'id_data' => $id,
            'created_at' => date('c')
        ];

        $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
        curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt(
            $logCh,
            CURLOPT_POSTFIELDS,
            json_encode($logData)
        );
        curl_setopt($logCh, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
        curl_exec($logCh);
        curl_close($logCh);
        echo json_encode([
            "success" => true
        ]);

    } else {
        echo json_encode([
            "success" => false,
            "message" => "Gagal hapus event",
            "http_code" => $httpCode,
            "response" => $response,
            "curl_error" => $error
        ]);
    }
?>