<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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

    if (empty($tab) || empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
    }

    function hapusPengguna($supabaseUrl, $supabaseKey, $id) {
        $getUrl = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $id . "&select=foto_profil";
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
        if (!empty($data) && !empty($data[0]['foto_profil'])) {
            $fotoPath = $data[0]['foto_profil'];
            $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $fotoPath;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $deleteUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $url = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $authUrl = $supabaseUrl . "/auth/v1/admin/users/" . $id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $authUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
        return ($httpCode === 200 || $httpCode === 204);
    }

    function hapusAdmin($supabaseUrl, $supabaseKey, $id) {
        $getUrl = $supabaseUrl . "/rest/v1/admin?id_admin=eq." . $id . "&select=foto_profil";
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
        if (!empty($data) && !empty($data[0]['foto_profil'])) {
            $fotoPath = $data[0]['foto_profil'];
            $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $fotoPath;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $deleteUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $url = $supabaseUrl . "/rest/v1/admin?id_admin=eq." . $id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $authUrl = $supabaseUrl . "/auth/v1/admin/users/" . $id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $authUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
        return ($httpCode === 200 || $httpCode === 204);
    }

    function hapusPenjual($supabaseUrl, $supabaseKey, $id) {
        $getUrl = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq." . $id . "&select=foto_profil";
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
        if (!empty($data) && !empty($data[0]['foto_profil'])) {
            $fotoPath = $data[0]['foto_profil'];
            $deleteUrl = $supabaseUrl . "/storage/v1/object/media/" . $fotoPath;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $deleteUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $url = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq." . $id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Prefer: return=minimal"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $authUrl = $supabaseUrl . "/auth/v1/admin/users/" . $id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $authUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
        return ($httpCode === 200 || $httpCode === 204);
    }

    $success = false;
    $namaData = '';

    switch($tab) {
        case 'pengguna':
            $getUrl = $supabaseUrl . "/rest/v1/pengguna?id_pengguna=eq." . $id . "&select=nama_lengkap";
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
            $namaData = $data[0]['nama_lengkap'] ?? 'Pengguna';
            break;
        case 'admin':
        case 'bank_sampah':
        case 'dlh':
            $getUrl = $supabaseUrl . "/rest/v1/admin?id_admin=eq." . $id . "&select=nama_admin";
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
            $namaData = $data[0]['nama_admin'] ?? 'Admin';
            break;
        case 'penjual':
            $getUrl = $supabaseUrl . "/rest/v1/penjual?id_penjual=eq." . $id . "&select=nama_penjual";
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
            $namaData = $data[0]['nama_penjual'] ?? 'Penjual';
            break;
    }

    switch($tab) {
        case 'pengguna':
            $success = hapusPengguna($supabaseUrl, $supabaseKey, $id);
            break;
        case 'admin':
        case 'bank_sampah':
        case 'dlh':
            $success = hapusAdmin($supabaseUrl, $supabaseKey, $id);
            break;
        case 'penjual':
            $success = hapusPenjual($supabaseUrl, $supabaseKey, $id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tab tidak dikenal']);
            exit;
    }

    if ($success) {
        $logData = [
            'id_admin' => $_SESSION['id_admin'],
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
        
        echo json_encode(['success' => true, 'message' => 'Akun berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus akun']);
    }
?>