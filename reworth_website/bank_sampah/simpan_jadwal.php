<?php
require_once 'role_check.php';
header('Content-Type: application/json');

$userRole = $_SESSION['role'] ?? '';
$adminId  = $_SESSION['id_admin'] ?? null;

if (!in_array($userRole, ['bank sampah', 'admin', 'dlh'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']); exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

$input = json_decode(file_get_contents('php://input'), true);
$aksi  = $input['aksi'] ?? $_POST['aksi'] ?? '';

function supabaseRequest($supabaseUrl, $supabaseKey, $endpoint, $method, $payload = null, $extraHeaders = []) {
    $ch = curl_init($supabaseUrl . "/rest/v1/" . $endpoint);
    $headers = array_merge([
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey",
        "Content-Type: application/json",
        "Prefer: return=representation",
    ], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, json_decode($resp, true)];
}

function logAdmin($supabaseUrl, $supabaseKey, $adminId, $aksiLog) {
    $ch = curl_init($supabaseUrl . "/rest/v1/log_admin");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'id_admin'   => $adminId,
            'aksi'       => $aksiLog,
            'created_at' => date('c'),
        ]),
        CURLOPT_HTTPHEADER => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json",
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}


if ($aksi === 'tambah') {
    $tanggal       = trim($input['tanggal']       ?? '');
    $waktu_mulai   = trim($input['waktu_mulai']   ?? '');
    $waktu_selesai = trim($input['waktu_selesai'] ?? '');
    $kuota         = (int)($input['kuota']         ?? 0);
    $catatan       = trim($input['catatan']        ?? '') ?: null;

    if (!$tanggal || !$waktu_mulai || !$waktu_selesai || $kuota < 1) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']); exit;
    }
    if ($waktu_mulai >= $waktu_selesai) {
        echo json_encode(['success' => false, 'message' => 'Waktu mulai harus sebelum waktu selesai.']); exit;
    }
    if ($tanggal < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Tanggal tidak boleh di masa lalu.']); exit;
    }

    [$code, $result] = supabaseRequest($supabaseUrl, $supabaseKey, 'jadwal_ambil', 'POST', [
        'tanggal'       => $tanggal,
        'waktu_mulai'   => $waktu_mulai . ':00',
        'waktu_selesai' => $waktu_selesai . ':00',
        'kuota'         => $kuota,
        'catatan'       => $catatan,
    ]);

    if ($code === 201) {
        logAdmin($supabaseUrl, $supabaseKey, $adminId, "tambah jadwal ambil sampah: $tanggal $waktu_mulai");
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil ditambahkan.', 'data' => $result]);
    } else {
        $msg = $result['message'] ?? 'Gagal menambahkan jadwal.';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
    exit;
}


if ($aksi === 'edit') {
    $id            = trim($input['id']            ?? '');
    $tanggal       = trim($input['tanggal']       ?? '');
    $waktu_mulai   = trim($input['waktu_mulai']   ?? '');
    $waktu_selesai = trim($input['waktu_selesai'] ?? '');
    $kuota         = (int)($input['kuota']         ?? 0);
    $catatan       = trim($input['catatan']        ?? '') ?: null;

    if (!$id || !$tanggal || !$waktu_mulai || !$waktu_selesai || $kuota < 1) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']); exit;
    }
    if ($waktu_mulai >= $waktu_selesai) {
        echo json_encode(['success' => false, 'message' => 'Waktu mulai harus sebelum waktu selesai.']); exit;
    }

    [$code, $result] = supabaseRequest($supabaseUrl, $supabaseKey,
        "jadwal_ambil?id_jadwal=eq." . urlencode($id), 'PATCH', [
        'tanggal'       => $tanggal,
        'waktu_mulai'   => $waktu_mulai . ':00',
        'waktu_selesai' => $waktu_selesai . ':00',
        'kuota'         => $kuota,
        'catatan'       => $catatan,
    ]);

    if ($code === 200) {
        logAdmin($supabaseUrl, $supabaseKey, $adminId, "edit jadwal ambil sampah: $tanggal $waktu_mulai");
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui.', 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jadwal.']);
    }
    exit;
}


if ($aksi === 'hapus') {
    $id = trim($input['id'] ?? '');
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']); exit;
    }


    $ch = curl_init($supabaseUrl . "/rest/v1/jadwal_ambil?id_jadwal=eq." . urlencode($id) . "&select=tanggal,waktu_mulai&limit=1");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
        ],
    ]);
    $existResp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($existResp)) {
        echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']); exit;
    }


    $today = date('Y-m-d');
    if ($existResp[0]['tanggal'] < $today) {
        echo json_encode(['success' => false, 'message' => 'Jadwal yang sudah selesai tidak dapat dihapus.']); exit;
    }

    [$code] = supabaseRequest($supabaseUrl, $supabaseKey,
        "jadwal_ambil?id_jadwal=eq." . urlencode($id), 'DELETE', null, []);

    if ($code === 204 || $code === 200) {
        $tgl  = $existResp[0]['tanggal'];
        $wkt  = substr($existResp[0]['waktu_mulai'], 0, 5);
        logAdmin($supabaseUrl, $supabaseKey, $adminId, "hapus jadwal ambil sampah: $tgl $wkt");
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus jadwal.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);