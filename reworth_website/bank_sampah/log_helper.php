<?php
function logAdminActivity($supabaseUrl, $supabaseKey, $idAdmin, $aktivitas, $tabelTerkait, $idData) {
    if (empty($idAdmin)) return;
    $logData = [
        'id_admin' => $idAdmin,
        'aktivitas' => $aktivitas,
        'tabel_terkait' => $tabelTerkait,
        'id_data' => $idData,
        'created_at' => date('c')
    ];
    $ch = curl_init($supabaseUrl . "/rest/v1/log_admin");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($logData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey", 
        "Authorization: Bearer $supabaseKey", 
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
