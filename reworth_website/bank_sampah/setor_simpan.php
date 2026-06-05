<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$adminId     = $_SESSION['id_admin'] ?? null;

/* ── helper cURL ── */
function sbRequest($url, $key, $endpoint, $method, $payload = null, $extraHeaders = []) {
    $ch = curl_init($url . $endpoint);
    $headers = array_merge([
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=representation",
    ], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, json_decode($resp, true)];
}

function sbGet($url, $key, $endpoint) {
    $ch = curl_init($url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json",
        ],
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, json_decode($resp, true)];
}

function logAdmin($url, $key, $adminId, $aksi) {
    $ch = curl_init($url . "/rest/v1/log_admin");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'id_admin'   => $adminId,
            'aksi'       => $aksi,
            'created_at' => date('c'),
        ]),
        CURLOPT_HTTPHEADER => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json",
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/* ── Baca input JSON ── */
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid (JSON parse error).']);
    exit;
}

/* ── Validasi field wajib ── */
$idPengguna = trim($input['id_pengguna'] ?? '');
$idJadwal   = trim($input['id_jadwal']   ?? '');
$alamat     = trim($input['alamat']      ?? '');
$totalUang  = (float)($input['total_uang'] ?? 0);
$details    = $input['details'] ?? [];

if (!$idPengguna) {
    echo json_encode(['success' => false, 'message' => 'Nama penyetor wajib dipilih.']);
    exit;
}
if (!$idJadwal) {
    echo json_encode(['success' => false, 'message' => 'Jadwal ambil wajib dipilih.']);
    exit;
}
if (!$alamat) {
    echo json_encode(['success' => false, 'message' => 'Alamat setor wajib diisi.']);
    exit;
}
if (empty($details)) {
    echo json_encode(['success' => false, 'message' => 'Tambahkan minimal 1 item sampah.']);
    exit;
}

/* ── Validasi setiap detail row ── */
foreach ($details as $i => $d) {
    if (empty($d['id_jenis'])) {
        echo json_encode(['success' => false, 'message' => 'Jenis sampah pada baris ' . ($i + 1) . ' belum dipilih.']);
        exit;
    }
    if ((float)($d['berat'] ?? 0) <= 0) {
        echo json_encode(['success' => false, 'message' => 'Berat pada baris ' . ($i + 1) . ' harus lebih dari 0.']);
        exit;
    }
}

/* ── Cek apakah pengguna sudah punya setor aktif di jadwal yang sama ── */
[$cekCode, $cekData] = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?id_pengguna=eq.$idPengguna&id_jadwal=eq.$idJadwal&status=in.(menunggu,diproses)&select=id_setor&limit=1"
);
if ($cekCode === 200 && !empty($cekData)) {
    echo json_encode(['success' => false, 'message' => 'Pengguna ini sudah memiliki transaksi aktif pada jadwal yang dipilih.']);
    exit;
}

/* ════════════════════════════════════════
   STEP 1 – Insert ke tabel setor_sampah
   ════════════════════════════════════════ */
$setorPayload = [
    'id_pengguna'     => $idPengguna,
    'id_jadwal'       => $idJadwal,
    'alamat'          => $alamat,
    'total_uang'      => $totalUang,
    'status'          => 'menunggu',
    'created_at'      => date('c'),
];

[$codeSetor, $dataSetor] = sbRequest($supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah", 'POST', $setorPayload
);

if ($codeSetor !== 201 || empty($dataSetor)) {
    $errMsg = is_array($dataSetor) ? ($dataSetor['message'] ?? 'Gagal menyimpan transaksi.') : 'Gagal menyimpan transaksi.';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

$idSetor = $dataSetor[0]['id_setor'] ?? null;

if (!$idSetor) {
    echo json_encode(['success' => false, 'message' => 'Gagal mendapatkan ID transaksi yang baru dibuat.']);
    exit;
}

/* ════════════════════════════════════════
   STEP 2 – Insert detail_setor_sampah
   ════════════════════════════════════════ */
$detailRows = [];
foreach ($details as $d) {
    $detailRows[] = [
        'id_setor'    => $idSetor,
        'id_jenis'    => $d['id_jenis'],
        'berat'       => (float)$d['berat'],
        'harga_per_kg'=> (float)($d['harga_per_kg'] ?? 0),
        'subtotal'    => (float)($d['subtotal']     ?? 0),
    ];
}

[$codeDetail, $dataDetail] = sbRequest($supabaseUrl, $supabaseKey,
    "/rest/v1/detail_setor", 'POST', $detailRows
);

if ($codeDetail !== 201) {
    // Rollback: hapus setor_sampah yang sudah dibuat
    sbRequest($supabaseUrl, $supabaseKey,
        "/rest/v1/setor_sampah?id_setor=eq.$idSetor", 'DELETE'
    );
    $errMsg = is_array($dataDetail) ? ($dataDetail['message'] ?? 'Gagal menyimpan detail sampah.') : 'Gagal menyimpan detail sampah.';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

/* ════════════════════════════════════════
   STEP 3 – Log admin
   ════════════════════════════════════════ */
// Ambil nama pengguna untuk log
[$cNama, $dNama] = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/pengguna?id_pengguna=eq.$idPengguna&select=nama_lengkap&limit=1"
);
$namaPenyetor = (!empty($dNama) && $cNama === 200) ? ($dNama[0]['nama_lengkap'] ?? $idPengguna) : $idPengguna;

require_once 'log_helper.php';
logAdminActivity($supabaseUrl, $supabaseKey, $adminId,
    "Menambahkan setor sampah: $namaPenyetor (Rp" . number_format($totalUang, 0, ',', '.') . ")",
    "setor_sampah",
    $idSetor
);

/* ════════════════════════════════════════
   SUKSES
   ════════════════════════════════════════ */
echo json_encode([
    'success'  => true,
    'message'  => 'Transaksi setor sampah berhasil disimpan.',
    'id_setor' => $idSetor,
]);