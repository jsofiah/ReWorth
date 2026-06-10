<?php
    require_once 'role_check.php';

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';

    $orderBy = ($sort == 'terlama') ? "created_at.asc" : "created_at.desc";

    try {
        $url = $supabaseUrl . "/rest/v1/event?select=*,admin!id_pembuat(id_admin,nama_admin,email,id_role,role!id_role(nama_role))&order=" . $orderBy . "&limit=$limit&offset=$offset";
        
        if (!empty($status)) {
            $url .= "&status=eq.$status";
        }
        
        if (!empty($dateFrom)) {
            $url .= "&tanggal=gte.$dateFrom";
        }
        if (!empty($dateTo)) {
            $url .= "&tanggal=lte.$dateTo";
        }
        
        $chData = curl_init();
        curl_setopt_array($chData, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        
        $response = curl_exec($chData);
        curl_close($chData);
        
        $eventList = [];
        if ($response) {
            $eventList = json_decode($response, true);
            if (!is_array($eventList)) {
                $eventList = [];
            }
        }
        
        foreach ($eventList as $key => $event) {
            $idEvent = $event['id_event'] ?? '';
            if (!empty($idEvent)) {
                $countUrl = $supabaseUrl . "/rest/v1/pendaftar_event?select=id_pendaftar_event&id_event=eq.$idEvent";
                
                $chCountPeserta = curl_init();
                curl_setopt_array($chCountPeserta, [
                    CURLOPT_URL => $countUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "apikey: $supabaseKey",
                        "Authorization: Bearer $supabaseKey"
                    ]
                ]);
                
                $countResponse = curl_exec($chCountPeserta);
                curl_close($chCountPeserta);
                
                $jumlahPeserta = 0;
                if ($countResponse) {
                    $pesertaData = json_decode($countResponse, true);
                    $jumlahPeserta = is_array($pesertaData) ? count($pesertaData) : 0;
                }
                
                $eventList[$key]['jumlah_peserta'] = $jumlahPeserta;
            } else {
                $eventList[$key]['jumlah_peserta'] = 0;
            }
        }
        
        $countUrl = $supabaseUrl . "/rest/v1/event?select=id_event";
        if (!empty($status)) {
            $countUrl .= "&status=eq.$status";
        }
        if (!empty($dateFrom)) {
            $countUrl .= "&tanggal=gte.$dateFrom";
        }
        if (!empty($dateTo)) {
            $countUrl .= "&tanggal=lte.$dateTo";
        }
        
        $chCount = curl_init();
        curl_setopt_array($chCount, [
            CURLOPT_URL => $countUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $supabaseKey",
                "Authorization: Bearer $supabaseKey"
            ]
        ]);
        
        $countResponse = curl_exec($chCount);
        curl_close($chCount);
        
        $total = 0;
        if ($countResponse) {
            $countData = json_decode($countResponse, true);
            $total = is_array($countData) ? count($countData) : 0;
        }
        
        $totalPages = ceil($total / $limit);
        $start_number = $total > 0 ? $offset + 1 : 0;
        $end_number = min($offset + $limit, $total);
        
    } catch (Exception $e) {
        $eventList = [];
        $total = 0;
        $totalPages = 0;
        $start_number = 0;
        $end_number = 0;
    }

    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'completed') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'berlangsung' || $status == 'ongoing') {
            return '<span class="status-badge status-berlangsung">Berlangsung</span>';
        } elseif ($status == 'dibatalkan' || $status == 'cancelled') {
            return '<span class="status-badge status-ditolak">Dibatalkan</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Akan Datang</span>';
        }
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
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun";
    }

    function getPenyelenggara($admin) {
        if (empty($admin)) return '-';
        
        $namaAdmin = $admin['nama_admin'] ?? '-';
        $role = isset($admin['role']) && is_array($admin['role']) ? $admin['role']['nama_role'] ?? '' : '';
        
        if ($role == 'dlh') {
            return "DLH - " . $namaAdmin;
        } elseif ($role == 'bank sampah') {
            return "Bank Sampah - " . $namaAdmin;
        } elseif ($role == 'admin') {
            return "Admin - " . $namaAdmin;
        } else {
            return $namaAdmin;
        }
    }
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 200px;">
                <col style="width: 180px;">
                <col style="width: 120px;">
                <col style="width: 150px;">
                <col style="width: 120px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Event</th>
                    <th>Diselenggarakan Oleh</th>
                    <th>Jumlah Peserta</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($eventList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-calendar-event" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada data event
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1;
                    foreach ($eventList as $event): 
                        $namaEvent = $event['nama_event'] ?? '-';
                        $idEvent = $event['id_event'] ?? '';
                        $tanggal = $event['tanggal'] ?? null;
                        $status = $event['status'] ?? 'akan_datang';
                        $jumlahPeserta = $event['jumlah_peserta'] ?? 0;
                        $maxPartisipan = $event['max_partisipan'] ?? 0;
                        $admin = $event['admin'] ?? null;
                        $penyelenggara = getPenyelenggara($admin);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="event-info">
                                    <?= htmlspecialchars($namaEvent) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($penyelenggara) ?></td>
                            <td>
                                <div class="peserta-info">
                                    <?= $jumlahPeserta ?> / <?= $maxPartisipan ?> peserta
                                    <?php if ($maxPartisipan > 0 && $jumlahPeserta >= $maxPartisipan): ?>
                                        <small class="text-full">(Penuh)</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= formatTanggalIndonesia($tanggal) ?></td>
                            <td><?= getStatusBadge($status) ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-aksi btn-lihat" onclick="window.parent.lihatDetail('<?= $idEvent ?>', 'event')">
                                        <i class="bi bi-file-earmark-text"></i> Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="table-footer">
        <div class="showing-text">
            Showing <b><?= $start_number ?></b> to <b><?= $end_number ?></b> of <b><?= $total ?></b> entries
        </div>
        <div class="pagination-custom">
            <?php if ($page > 1): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $page - 1 ?>)" class="page-btn page-btn-text">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="javascript:void(0)" onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
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

    function showToast(message, type) {
        alert(message);
    }
</script>