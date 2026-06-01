<?php
    session_start();


    error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));

    if (!isset($_SESSION['role'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Silakan login kembali']);
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $tab = $_POST['tab'] ?? '';
    $id = $_POST['id'] ?? '';

    error_log("Tab: $tab, ID: $id");

    if (empty($tab) || empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
    }


    function executeDelete($url, $headers) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Delete URL: $url, HTTP Code: $httpCode, Error: $error");
        
        return ($httpCode === 200 || $httpCode === 204);
    }


    function hapusLangganan($supabaseUrl, $supabaseKey, $id) {

        $getUrl = $supabaseUrl . "/rest/v1/langganan?id_langganan=eq." . $id . "&select=bukti_pembayaran";
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
        if (!empty($data) && !empty($data[0]['bukti_pembayaran'])) {
            $fotoPath = $data[0]['bukti_pembayaran'];
            $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $fotoPath;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $deleteUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $url = $supabaseUrl . "/rest/v1/langganan?id_langganan=eq." . $id;
        return executeDelete($url, $headers);
    }


    function hapusRole($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/role?id_role=eq." . $id;
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ];
        return executeDelete($url, $headers);
    }

    function hapusWilayah($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/wilayah?id_wilayah=eq." . $id;
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ];
        return executeDelete($url, $headers);
    }

    function hapusReward($supabaseUrl, $supabaseKey, $id) {
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ];
        
        $getUrl = $supabaseUrl . "/rest/v1/reward?id_reward=eq." . $id . "&select=foto_reward";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (!empty($data) && !empty($data[0]['foto_reward'])) {
            $fotoPath = $data[0]['foto_reward'];
            $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $fotoPath;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $deleteUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $url = $supabaseUrl . "/rest/v1/reward?id_reward=eq." . $id;
        $headers[] = "Prefer: return=minimal";
        return executeDelete($url, $headers);
    }

    function hapusPengeluaran($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/pengeluaran?id_pengeluaran=eq." . $id;
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ];
        return executeDelete($url, $headers);
    }

    $namaData = ucfirst($tab) . " ID: $id";

    $success = false;
    switch($tab) {
        case 'langganan':
            $success = hapusLangganan($supabaseUrl, $supabaseKey, $id);
            break;
        case 'role':
            $success = hapusRole($supabaseUrl, $supabaseKey, $id);
            break;
        case 'wilayah':
            $success = hapusWilayah($supabaseUrl, $supabaseKey, $id);
            break;
        case 'reward':
            $success = hapusReward($supabaseUrl, $supabaseKey, $id);
            break;
        case 'pengeluaran':
            $success = hapusPengeluaran($supabaseUrl, $supabaseKey, $id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tab tidak dikenal: ' . $tab]);
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
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data. Periksa koneksi atau ID data.']);
    }
?>