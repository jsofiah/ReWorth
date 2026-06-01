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

    function formatTanggal($dateString) {
        if (empty($dateString)) return '-';
        try {
            $date = new DateTime($dateString);
            $bulan = [
                1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];
            $tahun = $date->format('Y');
            $hari = $date->format('d');
            $bulanIndex = (int)$date->format('n');
            return $hari . ' ' . $bulan[$bulanIndex] . ' ' . $tahun;
        } catch (Exception $e) {
            return '-';
        }
    }

    function formatRupiah($angka) {
        if (empty($angka)) return '0';
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    function getKontribusiDetail($supabaseUrl, $supabaseKey, $id) {
        $url = $supabaseUrl . "/rest/v1/kontribusi_sponsor?id_kontribusi=eq." . $id . "&select=*,sponsor(nama_sponsor,kontak,jenis_sponsor)";
        
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

    $kontribusi = getKontribusiDetail($supabaseUrl, $supabaseKey, $id);

    if ($kontribusi) {
        $namaSponsor = '-';
        if (isset($kontribusi['sponsor']) && is_array($kontribusi['sponsor'])) {
            $namaSponsor = $kontribusi['sponsor']['nama_sponsor'] ?? '-';
        } elseif (isset($kontribusi['sponsor'])) {
            $namaSponsor = $kontribusi['sponsor'] ?? '-';
        }
        
        $result = [
            'success' => true,
            'data' => [
                'id_kontribusi' => $kontribusi['id_kontribusi'] ?? '',
                'nama_sponsor' => $namaSponsor,
                'jenis_kontribusi' => $kontribusi['jenis_kontribusi'] ?? '-',
                'nama_barang' => $kontribusi['nama_barang'] ?? '-',
                'jumlah_barang' => $kontribusi['jumlah_barang'] ?? 0,
                'nominal_uang' => $kontribusi['nominal_uang'] ?? 0,
                'nominal_uang_format' => formatRupiah($kontribusi['nominal_uang'] ?? 0),
                'keterangan' => $kontribusi['keterangan'] ?? '-',
                'tanggal' => $kontribusi['tanggal'] ?? '-',
                'tanggal_format' => formatTanggal($kontribusi['tanggal'] ?? ''),
                'created_at' => $kontribusi['created_at'] ?? '-'
            ]
        ];
        
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data kontribusi sponsor tidak ditemukan'
        ]);
    }
?>