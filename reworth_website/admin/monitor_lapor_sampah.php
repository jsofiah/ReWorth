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

    $conditions = [];
    $params = [];

    if (!empty($status)) {
        $conditions[] = "status = :status";
        $params[':status'] = $status;
    }

    if (!empty($dateFrom)) {
        $conditions[] = "DATE(created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $conditions[] = "DATE(created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    $orderBy = ($sort == 'terlama') ? "ORDER BY created_at ASC" : "ORDER BY created_at DESC";

    try {
        $url = $supabaseUrl . "/rest/v1/lapor_sampah?select=*,pengguna!id_pengguna(nama_lengkap,email,foto_profil)&order=created_at." . ($sort == 'terlama' ? 'asc' : 'desc') . "&limit=$limit&offset=$offset";
        
        if (!empty($status)) {
            $url .= "&status=eq.$status";
        }
        if (!empty($dateFrom)) {
            $url .= "&created_at=gte.$dateFrom";
        }
        if (!empty($dateTo)) {
            $url .= "&created_at=lte.$dateTo";
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
        
        $laporanList = [];
        if ($response) {
            $laporanList = json_decode($response, true);
            if (!is_array($laporanList)) {
                $laporanList = [];
            }
        }
        
        $countUrl = $supabaseUrl . "/rest/v1/lapor_sampah?select=id_laporan";
        if (!empty($status)) {
            $countUrl .= "&status=eq.$status";
        }
        if (!empty($dateFrom)) {
            $countUrl .= "&created_at=gte.$dateFrom";
        }
        if (!empty($dateTo)) {
            $countUrl .= "&created_at=lte.$dateTo";
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
        $laporanList = [];
        $total = 0;
        $totalPages = 0;
        $start_number = 0;
        $end_number = 0;
    }

    function getStatusBadge($status) {
        $status = strtolower($status);
        if ($status == 'selesai' || $status == 'verified') {
            return '<span class="status-badge status-selesai">Selesai</span>';
        } elseif ($status == 'diproses' || $status == 'processing') {
            return '<span class="status-badge status-berlangsung">Diproses</span>';
        } elseif ($status == 'ditolak' || $status == 'rejected') {
            return '<span class="status-badge status-akan_datang">Ditolak</span>';
        } else {
            return '<span class="status-badge status-akan_datang">Menunggu Verifikasi</span>';
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
        $jam = date('H:i', $timestamp);
        
        return "$tanggal " . $bulan[$bulanNum] . " $tahun, $jam";
    }
?>

<div class="table-wrap">
    <div class="table-scroll-wrapper">
        <table class="responsive-table" id="dynamicTable">
            <colgroup>
                <col style="width: 60px;">
                <col style="width: 180px;">
                <col style="width: 120px;">
                <col style="width: 200px;">
                <col style="width: 150px;">
                <col style="width: 120px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Pelapor</th>
                    <th>Jenis Sampah</th>
                    <th>Lokasi</th>
                    <th>Tanggal Lapor</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laporanList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5" style="color: #6B8A7E;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                            Belum ada data laporan
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1;
                    foreach ($laporanList as $laporan): 
                        $namaPelapor = '-';
                        if (isset($laporan['pengguna']) && is_array($laporan['pengguna'])) {
                            $namaPelapor = $laporan['pengguna']['nama_lengkap'] ?? '-';
                        } elseif (isset($laporan['nama_pelapor'])) {
                            $namaPelapor = $laporan['nama_pelapor'];
                        }
                        
                        $jenisSampah = $laporan['jenis_sampah'] ?? '-';
                        $lokasi = $laporan['lokasi'] ?? '-';
                        $tanggal = $laporan['created_at'] ?? null;
                        $status = $laporan['status'] ?? 'pending';
                        $idLaporan = $laporan['id_laporan'] ?? '';
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="pelapor-info">
                                    <?= htmlspecialchars($namaPelapor) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($jenisSampah) ?></td>
                            <td><?= htmlspecialchars($lokasi) ?></td>
                            <td><?= formatTanggalIndonesia($tanggal) ?></td>
                            <td><?= getStatusBadge($status) ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-aksi btn-lihat" onclick="lihatDetail('<?= $idLaporan ?>', 'lapor_sampah')">
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