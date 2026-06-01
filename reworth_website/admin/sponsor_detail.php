<?php
    session_start();
    header('Content-Type: application/json');

    if (!isset($_SESSION['role'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $id = isset($_GET['id']) ? $_GET['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    function getSponsorDetail($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/sponsor?id_sponsor=eq." . $id . "&select=*";
        
        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if (!empty($data) && isset($data[0])) {
                return $data[0];
            }
        }
        return null;
    }

    $sponsor = getSponsorDetail($supabaseUrl, $supabaseKey, $id);

    if ($sponsor) {
        $result = [
            'success' => true,
            'data' => [
                'nama_sponsor' => $sponsor['nama_sponsor'] ?? '-',
                'kontak' => $sponsor['kontak'] ?? '-',
                'jenis_sponsor' => $sponsor['jenis_sponsor'] ?? '-',
                'created_at' => $sponsor['created_at'] ?? '-',
                'id_sponsor' => $sponsor['id_sponsor'] ?? ''
            ]
        ];
        
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data sponsor tidak ditemukan'
        ]);
    }
?>