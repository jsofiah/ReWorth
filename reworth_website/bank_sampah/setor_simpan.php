<?php
require_once 'role_check.php';
header('Content-Type: application/json');

$supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
$supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";
$adminId     = $_SESSION['id_admin'] ?? null;
$adminName   = $_SESSION['nama_admin'] ?? 'Admin';


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

function sbPatch($url, $key, $endpoint, $body) {
    $ch = curl_init($url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            "apikey: $key",
            "Authorization: Bearer $key",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]
    ]);
    $res      = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}


$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid (JSON parse error).']);
    exit;
}


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


$totalPoin = 10;


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


[$cekCode, $cekData] = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/setor_sampah?id_pengguna=eq.$idPengguna&id_jadwal=eq.$idJadwal&status=in.(menunggu,diproses)&select=id_setor&limit=1"
);
if ($cekCode === 200 && !empty($cekData)) {
    echo json_encode(['success' => false, 'message' => 'Pengguna ini sudah memiliki transaksi aktif pada jadwal yang dipilih.']);
    exit;
}

$setorPayload = [
    'id_pengguna'     => $idPengguna,
    'id_jadwal'       => $idJadwal,
    'alamat'          => $alamat,
    'total_uang'      => $totalUang,
    'status'          => 'selesai',  
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
    
    sbRequest($supabaseUrl, $supabaseKey,
        "/rest/v1/setor_sampah?id_setor=eq.$idSetor", 'DELETE'
    );
    $errMsg = is_array($dataDetail) ? ($dataDetail['message'] ?? 'Gagal menyimpan detail sampah.') : 'Gagal menyimpan detail sampah.';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}


[$getUserCode, $userData] = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/pengguna?id_pengguna=eq.$idPengguna&select=saldo_tabungan,poin&limit=1"
);

$saldoLama = 0;
$poinLama = 0;
if ($getUserCode === 200 && !empty($userData)) {
    $saldoLama = (float)($userData[0]['saldo_tabungan'] ?? 0);
    $poinLama = (int)($userData[0]['poin'] ?? 0);
}

$saldoBaru = $saldoLama + $totalUang;
$poinBaru = $poinLama + $totalPoin;


$updateUser = sbPatch($supabaseUrl, $supabaseKey,
    "/rest/v1/pengguna?id_pengguna=eq.$idPengguna",
    [
        'saldo_tabungan' => $saldoBaru,
        'poin' => $poinBaru
    ]
);

if ($updateUser !== 204) {
    
    error_log("Gagal update saldo pengguna: $idPengguna");
}

$riwayatData = [
    'id_pengguna' => $idPengguna,
    'jenis_aktivitas' => 'setor_sampah',
    'id_referensi' => $idSetor,
    'judul' => 'Setor Sampah Berhasil',
    'deskripsi' => "Setor sampah Anda dengan total Rp" . number_format($totalUang, 0, ',', '.') . " telah berhasil dicatat oleh admin $adminName dan saldo telah ditambahkan.",
    'status' => 'selesai',
    'perubahan_poin' => $totalPoin,
    'perubahan_saldo' => $totalUang,
    'created_at' => date('c')
];

[$codeRiwayat, $dataRiwayat] = sbRequest($supabaseUrl, $supabaseKey,
    "/rest/v1/riwayat_aktivitas", 'POST', $riwayatData
);

$notifData = [
    'id_pengguna' => $idPengguna,
    'judul' => 'Setor Sampah Berhasil',
    'deskripsi' => "Setor sampah Anda senilai Rp" . number_format($totalUang, 0, ',', '.') . " telah berhasil dicatat. Saldo Anda bertambah Rp" . number_format($totalUang, 0, ',', '.') . " dan Anda mendapatkan +$totalPoin poin.",
    'is_read' => false,
    'created_at' => date('c')
];

[$codeNotif, $dataNotif] = sbRequest($supabaseUrl, $supabaseKey,
    "/rest/v1/notifikasi", 'POST', $notifData
);

$fcmSent = false;
$fcmHttpCode = null;

$fcmPayload = [
    'user_id' => $idPengguna,
    'title' => 'Setor Sampah Berhasil',
    'body' => "Setor sampah Anda senilai Rp" . number_format($totalUang, 0, ',', '.') . " telah berhasil dicatat. Saldo Anda bertambah!"
];

$chFcm = curl_init();
curl_setopt($chFcm, CURLOPT_URL, "https://rxzrbyqqhkxemdjbcntc.supabase.co/functions/v1/send-user-notification");
curl_setopt($chFcm, CURLOPT_POST, true);
curl_setopt($chFcm, CURLOPT_POSTFIELDS, json_encode($fcmPayload));
curl_setopt($chFcm, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $supabaseKey",
    "apikey: $supabaseKey",
    "Content-Type: application/json"
]);
curl_setopt($chFcm, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chFcm, CURLOPT_TIMEOUT, 10);

$fcmResponse = curl_exec($chFcm);
$fcmHttpCode = curl_getinfo($chFcm, CURLINFO_HTTP_CODE);
curl_close($chFcm);

$fcmSent = ($fcmHttpCode === 200);


[$cNama, $dNama] = sbGet($supabaseUrl, $supabaseKey,
    "/rest/v1/pengguna?id_pengguna=eq.$idPengguna&select=nama_lengkap&limit=1"
);
$namaPenyetor = (!empty($dNama) && $cNama === 200) ? ($dNama[0]['nama_lengkap'] ?? $idPengguna) : $idPengguna;

require_once 'log_helper.php';
logAdminActivity($supabaseUrl, $supabaseKey, $adminId,
    "Menambahkan setor sampah: $namaPenyetor (Rp" . number_format($totalUang, 0, ',', '.') . ") - Saldo bertambah, Poin +$totalPoin",
    "setor_sampah",
    $idSetor
);

echo json_encode([
    'success'  => true,
    'message'  => 'Transaksi setor sampah berhasil disimpan. Saldo dan poin telah ditambahkan.',
    'id_setor' => $idSetor,
    'fcm_sent' => $fcmSent,
    'saldo_baru' => $saldoBaru,
    'poin_baru' => $poinBaru
]);