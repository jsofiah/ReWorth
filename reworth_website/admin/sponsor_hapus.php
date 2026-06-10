<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $tab = $_POST['tab'] ?? '';
    $id = $_POST['id'] ?? '';

    if (empty($tab) || empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
    }

    function hapusSponsor($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/sponsor?id_sponsor=eq." . $id;
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200 || $httpCode === 204);
    }

    function hapusKontribusi($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/kontribusi_sponsor?id_kontribusi=eq." . $id;
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200 || $httpCode === 204);
    }

    $namaData = '';
    if ($tab === 'sponsor') {
        $getUrl = $supabaseUrl . "/rest/v1/sponsor?id_sponsor=eq." . $id . "&select=nama_sponsor";
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        $namaData = $data[0]['nama_sponsor'] ?? 'Sponsor';
    } elseif ($tab === 'kontribusi') {
        $getUrl = $supabaseUrl . "/rest/v1/kontribusi_sponsor?id_kontribusi=eq." . $id . "&select=jenis_kontribusi";
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        $jenis = $data[0]['jenis_kontribusi'] ?? 'Kontribusi';
        $namaData = 'Kontribusi ' . $jenis;
    }

    $success = false;
    switch($tab) {
        case 'sponsor':
            $success = hapusSponsor($supabaseUrl, $supabaseKey, $id);
            break;
        case 'kontribusi':
            $success = hapusKontribusi($supabaseUrl, $supabaseKey, $id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tab tidak dikenal']);
            exit;
    }

    if ($success) {
        $logData = [
            'id_admin' => $_SESSION['id_admin'] ?? '',
            'aktivitas' => 'Menghapus ' . $tab . ': ' . $namaData,
            'tabel_terkait' => $tab,
            'id_data' => $id,
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
        
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
?>