<?php
function checkSubscriptionStatus($userId, $supabaseUrl, $supabaseKey) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . "/rest/v1/langganan?id_penjual=eq.$userId&status=eq.aktif&order=created_at.desc&limit=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey",
        "Authorization: Bearer $supabaseKey"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Subscription check failed: HTTP $httpCode - $response");
        return [
            'is_premium' => false,
            'is_expired' => true,
            'has_active_subscription' => false,
            'has_any_subscription' => false,
            'current_subscription' => null,
            'message' => 'Gagal memeriksa status langganan'
        ];
    }
    
    $langgananList = json_decode($response, true);
    $today = date('Y-m-d');
    

    if (empty($langgananList)) {
        return [
            'is_premium' => false,
            'is_expired' => true,
            'has_active_subscription' => false,
            'has_any_subscription' => false,
            'current_subscription' => null,
            'message' => 'Belum pernah berlangganan'
        ];
    }
    

    $latestSubscription = $langgananList[0];
    

    if ($latestSubscription['tanggal_selesai'] >= $today) {
        return [
            'is_premium' => true,
            'is_expired' => false,
            'has_active_subscription' => true,
            'has_any_subscription' => true,
            'current_subscription' => $latestSubscription,
            'all_subscriptions' => $langgananList,
            'message' => 'Langganan aktif hingga ' . date('d F Y', strtotime($latestSubscription['tanggal_selesai']))
        ];
    } else {
        return [
            'is_premium' => false,
            'is_expired' => true,
            'has_active_subscription' => false,
            'has_any_subscription' => true,
            'current_subscription' => $latestSubscription,
            'all_subscriptions' => $langgananList,
            'message' => 'Langganan sudah berakhir pada ' . date('d F Y', strtotime($latestSubscription['tanggal_selesai']))
        ];
    }
}


function requirePremium($userId, $supabaseUrl, $supabaseKey) {
    $subscription = checkSubscriptionStatus($userId, $supabaseUrl, $supabaseKey);
    
    if (!$subscription['is_premium']) {
        $_SESSION['subscription_error'] = "Akses ditolak! " . $subscription['message'];
        header("Location: langganan.php");
        exit;
    }
    
    return $subscription;
}


function hasPremiumAccess($userId, $supabaseUrl, $supabaseKey) {
    $subscription = checkSubscriptionStatus($userId, $supabaseUrl, $supabaseKey);
    return $subscription['is_premium'];
}


function getSubscriptionStatus($userId, $supabaseUrl, $supabaseKey) {
    return checkSubscriptionStatus($userId, $supabaseUrl, $supabaseKey);
}


function getRemainingDays($userId, $supabaseUrl, $supabaseKey) {
    $subscription = checkSubscriptionStatus($userId, $supabaseUrl, $supabaseKey);
    
    if (!$subscription['is_premium'] || !$subscription['current_subscription']) {
        return 0;
    }
    
    $today = new DateTime();
    $endDate = new DateTime($subscription['current_subscription']['tanggal_selesai']);
    $diff = $today->diff($endDate);
    
    return $diff->days;
}


function getSidebarMenu($currentPage, $isPremium) {
    $menu = [
        // Menu yang selalu tampil (tidak perlu premium)
        ['nama' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'link' => 'dashboard.php', 'premium_only' => false],
        
        // Menu premium only (hanya tampil jika langganan aktif)
        ['nama' => 'Manajemen Produk', 'icon' => 'bi-box-seam-fill', 'link' => 'produk.php', 'premium_only' => true],
        ['nama' => 'Manajemen Pesanan', 'icon' => 'bi-bag-check-fill', 'link' => 'pesanan.php', 'premium_only' => true],
        ['nama' => 'Laporan dan Keuangan', 'icon' => 'bi-bar-chart-line-fill', 'link' => 'laporan_keuangan.php', 'premium_only' => true],
        ['nama' => 'Pengaturan Toko', 'icon' => 'bi-shop-window', 'link' => 'pengaturan_toko.php', 'premium_only' => true],
        ['nama' => 'Pengaturan Premium', 'icon' => 'bi-gem', 'link' => 'pengaturan_premium.php', 'premium_only' => true],
        
        // Menu yang tetap tampil meskipun expired (seperti langganan dan pembayaran komisi)
        ['nama' => 'Langganan', 'icon' => 'bi-stars', 'link' => 'langganan.php', 'premium_only' => false],
        ['nama' => 'Pembayaran Komisi', 'icon' => 'bi-cash-coin', 'link' => 'pembayaran_komisi.php', 'premium_only' => false],
    ];
    
    $html = '';
    foreach ($menu as $item) {
        // Jika menu premium_only dan user tidak premium, skip
        if ($item['premium_only'] && !$isPremium) {
            continue;
        }
        
        $active = ($currentPage == $item['link']) ? 'active' : '';
        $html .= <<<HTML
        <div class="nav-item">
            <a href="{$item['link']}" class="nav-link-custom {$active}">
                <i class="bi {$item['icon']}"></i>
                <span>{$item['nama']}</span>
            </a>
        </div>
HTML;
    }
    
    return $html;
}
?>