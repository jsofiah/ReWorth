<?php
    header('Content-Type: application/json');
    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    require_once 'subscription_check.php';

    if (!hasPremiumAccess($_SESSION['id_penjual'], $supabaseUrl, $supabaseKey)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Langganan Anda telah berakhir. Silakan perpanjang untuk melanjutkan.'
        ]);
        exit;
    }

    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode([
            "success" => false,
            "message" => "ID produk kosong"
        ]);
        exit;
    }

    $headers = [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json"
    ];


    $getFotoUrl =
        $supabaseUrl .
        "/rest/v1/foto_produk?id_produk=eq." .
        urlencode($id) .
        "&select=*";

    $getCh = curl_init();

    curl_setopt($getCh, CURLOPT_URL, $getFotoUrl);
    curl_setopt($getCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($getCh, CURLOPT_HTTPHEADER, $headers);

    $getResponse = curl_exec($getCh);

    $getHttpCode =
        curl_getinfo($getCh, CURLINFO_HTTP_CODE);

    curl_close($getCh);

    if ($getHttpCode !== 200) {

        echo json_encode([
            "success" => false,
            "message" => "Gagal mengambil data foto produk"
        ]);

        exit;
    }

    $fotoProduk =
        json_decode($getResponse, true) ?? [];


    foreach ($fotoProduk as $foto) {

        $fotoPath =
            $foto['path_foto'] ?? '';

        if (empty($fotoPath)) {
            continue;
        }

        $deleteStorageUrl =
            $supabaseUrl .
            "/storage/v1/object/media/" .
            $fotoPath;

        $deleteStorageCh = curl_init();

        curl_setopt(
            $deleteStorageCh,
            CURLOPT_URL,
            $deleteStorageUrl
        );

        curl_setopt(
            $deleteStorageCh,
            CURLOPT_CUSTOMREQUEST,
            "DELETE"
        );

        curl_setopt(
            $deleteStorageCh,
            CURLOPT_RETURNTRANSFER,
            true
        );

        curl_setopt(
            $deleteStorageCh,
            CURLOPT_HTTPHEADER,
            [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        );

        curl_exec($deleteStorageCh);

        curl_close($deleteStorageCh);
    }



    $deleteFotoUrl =
        $supabaseUrl .
        "/rest/v1/foto_produk?id_produk=eq." .
        urlencode($id);

    $deleteFotoCh = curl_init();

    curl_setopt(
        $deleteFotoCh,
        CURLOPT_URL,
        $deleteFotoUrl
    );

    curl_setopt(
        $deleteFotoCh,
        CURLOPT_CUSTOMREQUEST,
        "DELETE"
    );

    curl_setopt(
        $deleteFotoCh,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $deleteFotoCh,
        CURLOPT_HTTPHEADER,
        [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ]
    );

    curl_exec($deleteFotoCh);

    $deleteFotoHttpCode =
        curl_getinfo(
            $deleteFotoCh,
            CURLINFO_HTTP_CODE
        );

    curl_close($deleteFotoCh);



    $deleteProdukUrl =
        $supabaseUrl .
        "/rest/v1/produk?id_produk=eq." .
        urlencode($id);

    $deleteProdukCh = curl_init();

    curl_setopt(
        $deleteProdukCh,
        CURLOPT_URL,
        $deleteProdukUrl
    );

    curl_setopt(
        $deleteProdukCh,
        CURLOPT_CUSTOMREQUEST,
        "DELETE"
    );

    curl_setopt(
        $deleteProdukCh,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $deleteProdukCh,
        CURLOPT_HTTPHEADER,
        [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ]
    );

    $deleteProdukResponse =
        curl_exec($deleteProdukCh);

    $deleteProdukHttpCode =
        curl_getinfo(
            $deleteProdukCh,
            CURLINFO_HTTP_CODE
        );

    $deleteProdukError =
        curl_error($deleteProdukCh);

    curl_close($deleteProdukCh);



    if (
        $deleteProdukHttpCode == 200 ||
        $deleteProdukHttpCode == 204
    ) {

        echo json_encode([
            "success" => true,
            "message" => "Produk berhasil dihapus"
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => "Gagal hapus produk",
            "http_code" => $deleteProdukHttpCode,
            "response" => $deleteProdukResponse,
            "curl_error" => $deleteProdukError
        ]);
    }
?>