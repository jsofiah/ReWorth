<?php
    session_start();

    if (!isset($_SESSION['role'])) {
        header("Location: ../login.php");
        exit;
    }

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;

    function getLogBankSampah($supabaseUrl, $supabaseKey, $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        
        // Ambil log_admin dengan join ke admin dan role, filter untuk bank sampah
        $url = $supabaseUrl . "/rest/v1/log_admin?select=*,admin!id_admin(nama_admin,email,id_role,role!id_role(nama_role))&order=created_at.desc&limit=$per_page&offset=$offset";
        
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
            $data = json_decode($response, true) ?: [];
            
            // Filter hanya untuk role bank sampah
            $filtered = [];
            foreach ($data as $item) {
                $roleName = '';
                if (isset($item['admin']['role']['nama_role'])) {
                    $roleName = strtolower($item['admin']['role']['nama_role']);
                }
                if ($roleName == 'bank sampah' || $roleName == 'banksampah') {
                    $filtered[] = $item;
                }
            }
            return $filtered;
        }
        return [];
    }
    
    function getTotalLogBankSampah($supabaseUrl, $supabaseKey) {
        $url = $supabaseUrl . "/rest/v1/log_admin?select=id_log";
        
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
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                $count = 0;
                foreach ($data as $item) {
                    $detailUrl = $supabaseUrl . "/rest/v1/log_admin?id_log=eq." . $item['id_log'] . "&select=*,admin!id_admin(id_role,role!id_role(nama_role))";
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, $detailUrl);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                        "apikey: $supabaseKey",
                        "Authorization: Bearer $supabaseKey"
                    ]);
                    $detailResponse = curl_exec($ch2);
                    curl_close($ch2);
                    
                    $detailData = json_decode($detailResponse, true);
                    if (!empty($detailData) && isset($detailData[0])) {
                        $roleName = '';
                        if (isset($detailData[0]['admin']['role']['nama_role'])) {
                            $roleName = strtolower($detailData[0]['admin']['role']['nama_role']);
                        }
                        if ($roleName == 'bank sampah' || $roleName == 'banksampah') {
                            $count++;
                        }
                    }
                }
                return $count;
            }
            return 0;
        }
        return 0;
    }

    function formatTanggalIndonesia($date) {
        if (empty($date)) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $tanggal = date('d', $timestamp);
        $bulanNum = (int)date('m', $timestamp);
        $tahun = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun, $jam";
    }

    $allLogs = getLogBankSampah($supabaseUrl, $supabaseKey, $page, $per_page);
    $total = getTotalLogBankSampah($supabaseUrl, $supabaseKey);
    $total_pages = ceil($total / $per_page);
    $start_number = $total > 0 ? (($page - 1) * $per_page) + 1 : 0;
    $end_number = min($page * $per_page, $total);
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 200px;">
                <col style="width: 120px;">
                <col style="width: 250px;">
                <col style="width: 200px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Bank Sampah</th>
                    <th>Role</th>
                    <th>Aktivitas</th>
                    <th>Tabel Terkait</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allLogs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada log aktivitas bank sampah
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $start_number;
                    foreach ($allLogs as $log): 
                        $namaAdmin = '-';
                        $roleAdmin = '-';
                        
                        if (isset($log['admin']) && is_array($log['admin'])) {
                            $adminData = $log['admin'];
                            $namaAdmin = $adminData['nama_admin'] ?? '-';
                            
                            if (isset($adminData['role']) && is_array($adminData['role'])) {
                                $roleAdmin = $adminData['role']['nama_role'] ?? '-';
                            }
                        }
                        
                        $aktivitas = $log['aktivitas'] ?? '-';
                        $tabelTerkait = $log['tabel_terkait'] ?? '-';
                        $tanggal = $log['created_at'] ?? null;
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="pelapor-info">
                                    <?= htmlspecialchars($namaAdmin) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($roleAdmin) ?></td>
                            <td class="table-cell-content"><?= htmlspecialchars($aktivitas) ?> </td>
                            <td><?= htmlspecialchars($tabelTerkait) ?> </td>
                            <td><?= formatTanggalIndonesia($tanggal) ?> </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="table-footer">
        <div class="showing-text">
            Showing <b><?= $start_number ?></b> to <b><?= $end_number ?></b> of <b><?= $total ?></b> entries
        </div>
        <div class="pagination-custom">
            <?php if ($page > 1): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page - 1 ?>)" class="page-btn page-btn-text">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page + 1 ?>)" class="page-btn page-btn-text">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function changePage(page) {
        if (typeof window.parent.changePage === 'function') {
            window.parent.changePage(page);
        } else {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.href;
        }
    }
</script>