<?php
    session_start();
    header('Content-Type: application/json');

    if (!isset($_SESSION['id_penjual'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

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

    $id_pesanan = $_POST['id'] ?? '';

    if (empty($id_pesanan)) {
        echo json_encode(['success' => false, 'message' => 'ID pesanan kosong']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$id_pesanan&select=bukti_pembayaran");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);


    if (!empty($data[0]['bukti_pembayaran'])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/storage/v1/object/media/" . $data[0]['bukti_pembayaran']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/pesanan?id_pesanan=eq.$id_pesanan");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Prefer: return=minimal"
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 || $httpCode == 204) {
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pesanan']);
    }
?>