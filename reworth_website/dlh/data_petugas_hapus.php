<?php
    require_once 'role_check.php';

    header('Content-Type: application/json');

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode(["success" => false, "message" => "ID petugas kosong"]);
        exit;
    }


    $getCh = curl_init();
    curl_setopt($getCh, CURLOPT_URL,
        $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($id) . "&select=foto_profil,nama_petugas"
    );
    curl_setopt($getCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($getCh, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $getResp = curl_exec($getCh);
    $getCode = curl_getinfo($getCh, CURLINFO_HTTP_CODE);
    curl_close($getCh);

    if ($getCode !== 200) {
        echo json_encode(["success" => false, "message" => "Gagal mengambil data petugas"]);
        exit;
    }

    $rowData      = json_decode($getResp, true);
    $fotoLama     = $rowData[0]['foto_profil']  ?? '';
    $namaPetugas  = $rowData[0]['nama_petugas'] ?? '';


    if (!empty($fotoLama)) {
        $delBody = json_encode(["prefixes" => [$fotoLama]]);
        $delCh   = curl_init();
        curl_setopt($delCh, CURLOPT_URL, $supabaseUrl . "/storage/v1/object/media");
        curl_setopt($delCh, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($delCh, CURLOPT_POSTFIELDS, $delBody);
        curl_setopt($delCh, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($delCh, CURLOPT_RETURNTRANSFER, true);
        curl_exec($delCh);
        curl_close($delCh);
    }


    $delRecCh = curl_init();
    curl_setopt($delRecCh, CURLOPT_URL,
        $supabaseUrl . "/rest/v1/petugas_lapangan?id_petugas=eq." . urlencode($id)
    );
    curl_setopt($delRecCh, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($delRecCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($delRecCh, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Prefer: return=minimal"
    ]);
    $delResp  = curl_exec($delRecCh);
    $delCode  = curl_getinfo($delRecCh, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($delRecCh);
    curl_close($delRecCh);

    if ($delCode == 204) {

        $logData = [
            'id_admin'      => $_SESSION['id_admin'],
            'aktivitas'     => 'Menghapus petugas: ' . $namaPetugas,
            'tabel_terkait' => 'petugas_lapangan',
            'created_at'    => date('c')
        ];
        $logCh = curl_init($supabaseUrl . "/rest/v1/log_admin");
        curl_setopt($logCh, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($logCh, CURLOPT_POSTFIELDS, json_encode($logData));
        curl_setopt($logCh, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($logCh, CURLOPT_RETURNTRANSFER, true);
        curl_exec($logCh);
        curl_close($logCh);

        echo json_encode(["success" => true]);
    } else {
        echo json_encode([
            "success"    => false,
            "message"    => "Gagal hapus petugas",
            "http_code"  => $delCode,
            "response"   => $delResp,
            "curl_error" => $curlErr
        ]);
    }
?>